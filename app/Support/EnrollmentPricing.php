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
     * 月費基準堂數：每週上課日數 × 4（例：週三＋週六＝8；當月實際有 9 堂仍以 8 堂收全月費）。
     *
     * @param  list<int>  $weekdays
     */
    public static function billingBaselineSessions(array $weekdays): int
    {
        $count = count(WeekdayDates::normalize($weekdays));

        return max(1, $count * 4);
    }

    /**
     * 單月學費精確值（未四捨五入）。
     */
    public static function proratedMonthTuitionExact(int $unitPrice, int $attended, int $baseline): float
    {
        if ($unitPrice <= 0 || $attended <= 0) {
            return 0.0;
        }
        $baseline = max(1, $baseline);

        return min((float) $unitPrice, $unitPrice * ($attended / $baseline));
    }

    /**
     * 單月學費整數（單科四捨五入）。多科同月請先加總精確值再 round。
     */
    public static function proratedMonthTuition(int $unitPrice, int $attended, int $baseline): int
    {
        return (int) round(self::proratedMonthTuitionExact($unitPrice, $attended, $baseline));
    }

    /**
     * 將精確金額分配成整數，使合計等於 round(sum(exacts))（最大餘數法）。
     *
     * @param  list<float>  $exacts
     * @return list<int>
     */
    public static function allocateRoundedAmounts(array $exacts): array
    {
        if ($exacts === []) {
            return [];
        }

        $target = (int) round(array_sum($exacts));
        $floors = [];
        $fracs = [];
        foreach ($exacts as $i => $exact) {
            $floor = (int) floor($exact + 1e-9);
            $floors[$i] = $floor;
            $fracs[$i] = $exact - $floor;
        }

        $need = $target - array_sum($floors);
        $order = array_keys($exacts);
        usort($order, function (int|string $a, int|string $b) use ($fracs): int {
            return $fracs[$b] <=> $fracs[$a] ?: $a <=> $b;
        });

        for ($i = 0; $i < $need; $i++) {
            $floors[$order[$i]]++;
        }

        return array_map(fn ($i) => (int) $floors[$i], array_keys($exacts));
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
     *   month_breakdown:list<array{y:int,m:int,tuition:int,material:int,subtotal:int}>,
     *   lines:list<array{
     *     course_id:int,
     *     course_name:string,
     *     unit_price:int,
     *     months:int,
     *     tuition:int,
     *     material:int,
     *     material_unit:string,
     *     material_months:array<string, array{amount:int, days:int}>,
     *     tuition_months:array<string, array{amount:int, attended:int, baseline:int}>,
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
        $materialTotal = 0;
        /** @var array<string, array{y:int,m:int,tuition:int,material:int}> $monthAgg */
        $monthAgg = [];
        foreach ($months as $month) {
            $key = ((int) $month['y']).'-'.((int) $month['m']);
            $monthAgg[$key] = [
                'y' => (int) $month['y'],
                'm' => (int) $month['m'],
                'tuition' => 0,
                'material' => 0,
            ];
        }

        /** @var list<array<string, mixed>> $draftLines */
        $draftLines = [];
        /** @var array<string, list<array{line:int, exact:float, attended:int, baseline:int}>> $exactByMonth */
        $exactByMonth = [];

        foreach ($selected as $subject) {
            $unitPrice = match ($payCycle) {
                'monthly' => (int) $subject['list'],
                'annual' => (int) $subject['q_double'],
                default => $subject['pricing_group'] === PricingGroup::CORE && $coreCount >= 2
                    ? (int) $subject['q_double']
                    : (int) $subject['q_single'],
            };

            $subjectDates = $datesByCourse[(int) $subject['id']] ?? [];
            $baseline = self::billingBaselineSessions($subject['weekdays'] ?? []);
            /** @var array<string, array{amount:int, attended:int, baseline:int}> $tuitionMonths */
            $tuitionMonths = [];
            $isBlock = ($subject['unit'] ?? 'month') === 'session_block';

            if ($isBlock) {
                $blocks = max(1, (int) ceil(max(1, $monthCount) / 3));
                $tuition = $unitPrice * $blocks;
                $monthsUsed = $blocks;
                if ($months !== []) {
                    $per = intdiv($tuition, $monthCount);
                    $rem = $tuition % $monthCount;
                    foreach ($months as $index => $month) {
                        $key = ((int) $month['y']).'-'.((int) $month['m']);
                        $attended = self::countDatesInMonth($subjectDates, (int) $month['y'], (int) $month['m']);
                        $amount = $per + ($index === 0 ? $rem : 0);
                        $tuitionMonths[$key] = [
                            'amount' => $amount,
                            'attended' => $attended,
                            'baseline' => $baseline,
                        ];
                    }
                }
            } else {
                $monthsUsed = $monthCount;
                foreach ($months as $month) {
                    $y = (int) $month['y'];
                    $m = (int) $month['m'];
                    $key = $y.'-'.$m;
                    $attended = self::countDatesInMonth($subjectDates, $y, $m);
                    $exact = self::proratedMonthTuitionExact($unitPrice, $attended, $baseline);
                    $tuitionMonths[$key] = [
                        'amount' => 0,
                        'attended' => $attended,
                        'baseline' => $baseline,
                    ];
                    $exactByMonth[$key][] = [
                        'line' => count($draftLines),
                        'exact' => $exact,
                        'attended' => $attended,
                        'baseline' => $baseline,
                    ];
                }
            }

            [$material, $materialMonths, $materialNote] = self::materialForSubject(
                $subject,
                $subjectDates,
                $months,
            );

            $materialTotal += $material;

            foreach ($materialMonths as $key => $row) {
                if (isset($monthAgg[$key])) {
                    $monthAgg[$key]['material'] += (int) ($row['amount'] ?? 0);
                }
            }

            $draftLines[] = [
                'course_id' => (int) $subject['id'],
                'course_name' => (string) $subject['name'],
                'unit_price' => $unitPrice,
                'months' => $monthsUsed,
                'tuition' => $isBlock ? ($unitPrice * max(1, (int) ceil(max(1, $monthCount) / 3))) : 0,
                'material' => $material,
                'material_unit' => (string) ($subject['material_unit'] ?? 'term'),
                'material_months' => $materialMonths,
                'tuition_months' => $tuitionMonths,
                'material_note' => $materialNote,
                'is_block' => $isBlock,
            ];
        }

        foreach ($exactByMonth as $key => $rows) {
            $exacts = array_map(fn (array $row): float => (float) $row['exact'], $rows);
            $amounts = self::allocateRoundedAmounts($exacts);
            foreach ($rows as $index => $row) {
                $lineIndex = (int) $row['line'];
                $amount = (int) $amounts[$index];
                $draftLines[$lineIndex]['tuition_months'][$key]['amount'] = $amount;
                $draftLines[$lineIndex]['tuition'] = (int) $draftLines[$lineIndex]['tuition'] + $amount;
                if (isset($monthAgg[$key])) {
                    $monthAgg[$key]['tuition'] += $amount;
                }
            }
        }

        foreach ($draftLines as $line) {
            if (! empty($line['is_block'])) {
                foreach ($line['tuition_months'] as $key => $row) {
                    if (isset($monthAgg[$key])) {
                        $monthAgg[$key]['tuition'] += (int) $row['amount'];
                    }
                }
            }
            unset($line['is_block']);
            $lines[] = $line;
        }

        $tuitionTotal = (int) array_sum(array_column($lines, 'tuition'));
        $grandTotal = max(0, $tuitionTotal + $materialTotal - max(0, $allowance));

        $monthBreakdown = array_values(array_map(
            fn (array $row): array => [
                'y' => $row['y'],
                'm' => $row['m'],
                'tuition' => $row['tuition'],
                'material' => $row['material'],
                'subtotal' => $row['tuition'] + $row['material'],
            ],
            $monthAgg
        ));

        return [
            'tuition_total' => $tuitionTotal,
            'material_total' => $materialTotal,
            'grand_total' => $grandTotal,
            'core_count' => $coreCount,
            'months' => $months,
            'month_breakdown' => $monthBreakdown,
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<string>  $dates
     */
    private static function countDatesInMonth(array $dates, int $year, int $month): int
    {
        $count = 0;
        foreach ($dates as $date) {
            $d = Carbon::parse($date);
            if ((int) $d->year === $year && (int) $d->month === $month) {
                $count++;
            }
        }

        return $count;
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
     * 教材年費換算每月金額（÷12）。
     */
    public static function monthlyMaterialFee(int $annualOrTermFee): int
    {
        if ($annualOrTermFee <= 0) {
            return 0;
        }

        return (int) round($annualOrTermFee / 12);
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
            $monthly = self::monthlyMaterialFee($fee);
            if ($monthly <= 0) {
                return [0, [], null];
            }

            $materialMonths = [];
            $total = 0;
            foreach ($months as $month) {
                $key = ((int) $month['y']).'-'.((int) $month['m']);
                $materialMonths[$key] = ['amount' => $monthly, 'days' => 0];
                $total += $monthly;
            }

            $note = sprintf('教材月費 %s（年 %s ÷ 12）', number_format($monthly), number_format($fee));

            return [$total, $materialMonths, $note];
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
