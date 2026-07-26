<?php

namespace App\Support;

use App\Models\Course;
use App\Models\FeePlan;
use App\Models\Student;
use Carbon\Carbon;

final class EnrollmentPricing
{
    /**
     * @return list<array{
     *   id:int,
     *   name:string,
     *   category:string|null,
     *   pricing_group:string,
     *   pricing_group_label:string,
     *   unit:string,
     *   list:int,
     *   q_single:int,
     *   q_double:int,
     *   material:int,
     *   material_unit:string,
     *   weekdays:list<int>,
     *   fee_plan_id:int|null,
     *   group_name:string|null,
     *   color:string|null
     * }>
     */
    public static function subjectsForStudent(Student $student): array
    {
        if ($student->grade_level_id === null) {
            return [];
        }

        $gradeName = $student->gradeLevel?->name;

        $courses = Course::query()
            ->with([
                'courseCategory:id,name',
                'coursePrices:id,course_id,level',
                'feePlans' => function ($query) use ($student): void {
                    $query
                        ->where('grade_level_id', $student->grade_level_id)
                        ->where('is_active', true)
                        ->where(function ($builder) use ($student): void {
                            $builder->whereNull('academic_year_id');
                            if ($student->academic_year_id !== null) {
                                $builder->orWhere('academic_year_id', $student->academic_year_id);
                            }
                        });

                    if ($student->academic_year_id !== null) {
                        $query->orderByRaw(
                            'CASE WHEN academic_year_id = ? THEN 0 ELSE 1 END',
                            [$student->academic_year_id]
                        );
                    }

                    $query->orderBy('sort_order')->orderBy('fee_plans.id');
                },
            ])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->filter(function (Course $course) use ($gradeName): bool {
                $levels = $course->coursePrices->pluck('level')->filter()->values();
                if ($levels->isEmpty()) {
                    return true;
                }

                return $gradeName !== null && $levels->contains($gradeName);
            });

        return $courses->map(function (Course $course) use ($gradeName): array {
            /** @var FeePlan|null $plan */
            $plan = $course->feePlans->first();

            $list = (int) ($plan?->list_price ?? 0);
            $qSingle = (int) ($plan?->quarter_single_price ?? $plan?->quarter_price ?? $list);
            $qDouble = (int) ($plan?->quarter_double_price ?? $plan?->quarter_price ?? $qSingle);
            $material = (int) ($plan?->material_fee ?? 0);
            $materialUnit = (string) ($plan?->material_unit ?? 'term');

            $allSchedules = WeekdayDates::normalizeSchedules($course->schedules);
            if ($allSchedules === [] && is_array($course->weekdays)) {
                $allSchedules = collect(WeekdayDates::normalize($course->weekdays))
                    ->map(fn (int $d) => [
                        'level' => null,
                        'weekday' => $d,
                        'start_time' => null,
                        'end_time' => null,
                    ])
                    ->values()
                    ->all();
            }
            $gradeSchedules = WeekdayDates::schedulesForLevel($allSchedules, $gradeName);
            $weekdays = WeekdayDates::weekdaysFromSchedules($gradeSchedules);

            return [
                'id' => $course->id,
                'name' => $course->name,
                'color' => $course->color,
                'category' => $course->courseCategory?->name,
                'pricing_group' => $course->pricing_group,
                'pricing_group_label' => PricingGroup::label($course->pricing_group),
                'unit' => $plan?->unit ?? 'month',
                'list' => $list,
                'q_single' => $qSingle,
                'q_double' => $qDouble,
                'material' => $material,
                'material_unit' => $materialUnit,
                'weekdays' => $weekdays,
                'schedules' => $gradeSchedules,
                'fee_plan_id' => $plan?->id,
                'group_name' => $plan?->group_name,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{date?:string,course_id?:int}|string>  $sessions  堂次（日期＋科目）；相容舊版純日期字串
     * @param  list<int>  $courseIds
     * @return array{
     *   tuition_total:int,
     *   material_total:int,
     *   grand_total:int,
     *   core_count:int,
     *   months:list<array{y:int,m:int}>,
     *   lines:list<array{
     *     course_id:int,
     *     course_name:string,
     *     unit_price:int,
     *     months:int,
     *     tuition:int,
     *     material:int,
     *     material_unit:string,
     *     material_months:array<string, array{amount:int, days:int}>,
     *     material_note:string|null
     *   }>
     * }
     */
    public static function quote(
        Student $student,
        array $courseIds,
        string $payCycle,
        array $sessions,
        int $allowance = 0,
        ?string $startDate = null,
    ): array {
        $subjects = collect(self::subjectsForStudent($student))->keyBy('id');
        $selected = collect($courseIds)
            ->map(fn ($id) => $subjects->get((int) $id))
            ->filter(fn (?array $subject): bool => $subject !== null && ! empty($subject['fee_plan_id']))
            ->values();

        $selectedIds = $selected->pluck('id')->map(fn ($id) => (int) $id)->all();
        $normalizedSessions = self::normalizeSessions($sessions, $startDate, $selectedIds);
        $allDates = array_values(array_unique(array_column($normalizedSessions, 'date')));
        sort($allDates);
        $months = self::monthsFromDates($allDates);
        $coreCount = $selected->where('pricing_group', PricingGroup::CORE)->count();
        $monthCount = count($months);

        $datesByCourse = [];
        foreach ($normalizedSessions as $session) {
            $cid = (int) $session['course_id'];
            $datesByCourse[$cid][] = $session['date'];
        }

        $lines = [];
        $tuitionTotal = 0;
        $materialTotal = 0;

        foreach ($selected as $subject) {
            $unitPrice = match ($payCycle) {
                'monthly' => (int) $subject['list'],
                'annual' => (int) $subject['q_double'],
                default => $subject['pricing_group'] === PricingGroup::CORE && $coreCount >= 2
                    ? (int) $subject['q_double']
                    : (int) $subject['q_single'],
            };

            if (($subject['unit'] ?? 'month') === 'session_block') {
                $blocks = max(1, (int) ceil(max(1, $monthCount) / 3));
                $tuition = $unitPrice * $blocks;
                $monthsUsed = $blocks;
            } else {
                $tuition = $unitPrice * max(0, $monthCount);
                $monthsUsed = $monthCount;
            }

            $subjectDates = $datesByCourse[(int) $subject['id']] ?? [];
            [$material, $materialMonths, $materialNote] = self::materialForSubject(
                $subject,
                $subjectDates,
                $months,
            );

            $tuitionTotal += $tuition;
            $materialTotal += $material;

            $lines[] = [
                'course_id' => (int) $subject['id'],
                'course_name' => (string) $subject['name'],
                'unit_price' => $unitPrice,
                'months' => $monthsUsed,
                'tuition' => $tuition,
                'material' => $material,
                'material_unit' => (string) ($subject['material_unit'] ?? 'term'),
                'material_months' => $materialMonths,
                'material_note' => $materialNote,
            ];
        }

        $grandTotal = max(0, $tuitionTotal + $materialTotal - max(0, $allowance));

        return [
            'tuition_total' => $tuitionTotal,
            'material_total' => $materialTotal,
            'grand_total' => $grandTotal,
            'core_count' => $coreCount,
            'months' => $months,
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array{date?:string,course_id?:int}|string>  $sessions
     * @param  list<int>  $allowedCourseIds
     * @return list<array{date:string,course_id:int}>
     */
    private static function normalizeSessions(array $sessions, ?string $startDate, array $allowedCourseIds): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $allowed = array_flip($allowedCourseIds);
        $out = [];

        foreach ($sessions as $row) {
            if (is_string($row)) {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $dateRaw = $row['date'] ?? null;
            $courseId = isset($row['course_id']) ? (int) $row['course_id'] : 0;
            if (! is_string($dateRaw) || $courseId <= 0 || ! isset($allowed[$courseId])) {
                continue;
            }
            try {
                $d = Carbon::parse($dateRaw)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if ($start !== null && $d->lt($start)) {
                continue;
            }
            $key = $d->toDateString().'|'.$courseId;
            $out[$key] = ['date' => $d->toDateString(), 'course_id' => $courseId];
        }

        $values = array_values($out);
        usort(
            $values,
            fn (array $a, array $b): int => $a['date'] <=> $b['date'] ?: $a['course_id'] <=> $b['course_id']
        );

        return $values;
    }

    /**
     * @param  list<string>  $dates
     * @return list<array{y:int,m:int}>
     */
    private static function monthsFromDates(array $dates): array
    {
        $map = [];
        foreach ($dates as $date) {
            $d = Carbon::parse($date);
            $key = $d->year.'-'.$d->month;
            $map[$key] = ['y' => (int) $d->year, 'm' => (int) $d->month];
        }
        ksort($map);

        return array_values($map);
    }

    /**
     * @param  array{
     *   material:int,
     *   material_unit:string,
     *   weekdays:list<int>,
     *   name?:string
     * }  $subject
     * @param  list<string>  $dates  已歸屬此科的堂次日期
     * @param  list<array{y:int,m:int}>  $months
     * @return array{0:int,1:array<string, array{amount:int, days:int}>,2:?string}
     */
    private static function materialForSubject(array $subject, array $dates, array $months): array
    {
        $fee = (int) ($subject['material'] ?? 0);
        $unit = (string) ($subject['material_unit'] ?? 'term');

        if ($fee <= 0) {
            return [0, [], null];
        }

        if ($unit !== 'class_day') {
            if ($months === []) {
                return [0, [], null];
            }
            $first = $months[0];
            $key = ((int) $first['y']).'-'.((int) $first['m']);

            return [
                $fee,
                [$key => ['amount' => $fee, 'days' => 0]],
                null,
            ];
        }

        if ($dates === []) {
            return [0, [], null];
        }

        $byMonth = [];
        foreach ($dates as $date) {
            $d = Carbon::parse($date);
            $key = $d->year.'-'.$d->month;
            $byMonth[$key] = ($byMonth[$key] ?? 0) + 1;
        }

        $materialMonths = [];
        $total = 0;
        $parts = [];
        foreach ($byMonth as $key => $days) {
            $amount = $fee * $days;
            $materialMonths[$key] = ['amount' => $amount, 'days' => $days];
            $total += $amount;
            $parts[] = sprintf('%s %d天×%s', str_replace('-', '/', (string) $key), $days, number_format($fee));
        }

        $note = $parts !== [] ? '耗材 '.implode('；', $parts) : null;

        return [$total, $materialMonths, $note];
    }
}
