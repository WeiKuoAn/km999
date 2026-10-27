<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\FeePlan;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class EnrollmentPricing
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $subjectsByGradeYearCache = [];

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
     *   material:int,
     *   material_unit:string,
     *   weekdays:list<int>,
     *   start_date:?string,
     *   end_date:?string,
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

        $cacheKey = ((int) $student->grade_level_id).'|'.((int) ($student->academic_year_id ?? 0));
        if (isset(self::$subjectsByGradeYearCache[$cacheKey])) {
            return self::$subjectsByGradeYearCache[$cacheKey];
        }

        $gradeName = $student->gradeLevel?->name;

        $courses = Course::query()
            ->with([
                'courseCategory:id,name',
                'coursePrices:id,course_id,level',
                'feePlans' => function ($query) use ($student): void {
                    $query
                        ->with('periods.coursePrices')
                        ->where('is_active', true)
                        ->where(function ($builder) use ($student): void {
                            $builder
                                ->whereNull('grade_level_id')
                                ->orWhere('grade_level_id', $student->grade_level_id);
                        })
                        ->where(function ($builder) use ($student): void {
                            $builder->whereNull('academic_year_id');
                            if ($student->academic_year_id !== null) {
                                $builder->orWhere('academic_year_id', $student->academic_year_id);
                            }
                        });

                    // 同年級優先於全年級；同學年優先於不限學年
                    $query->orderByRaw(
                        'CASE WHEN grade_level_id = ? THEN 0 ELSE 1 END',
                        [$student->grade_level_id]
                    );

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

        $result = $courses->map(function (Course $course) use ($gradeName): array {
            /** @var FeePlan|null $plan */
            $plan = $course->feePlans->first();

            $list = (int) ($plan?->list_price ?? 0);
            $qSingle = (int) ($plan?->quarter_single_price ?? $plan?->quarter_price ?? $list);
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
                'session_block_size' => $plan?->unit === 'session_block'
                    ? max(1, (int) ($plan->session_block_size ?? 3))
                    : null,
                'periods' => $plan?->unit === 'custom_period'
                    ? $plan->periods->map(function ($period) use ($course): array {
                        $coursePrice = $period->relationLoaded('coursePrices')
                            ? $period->coursePrices->firstWhere('course_id', $course->id)
                            : null;

                        return [
                            'start_month' => (int) $period->start_month,
                            'end_month' => (int) $period->end_month,
                            // 該科一期價（無分科價時退回期別統一價）
                            'total_price' => $coursePrice !== null
                                ? (int) $coursePrice->total_price
                                : (int) $period->total_price,
                            'double_price' => $coursePrice !== null
                                ? ($coursePrice->double_price !== null ? (int) $coursePrice->double_price : null)
                                : ($period->double_price !== null ? (int) $period->double_price : null),
                            'triple_price' => $coursePrice !== null
                                ? ($coursePrice->triple_price !== null ? (int) $coursePrice->triple_price : null)
                                : ($period->triple_price !== null ? (int) $period->triple_price : null),
                        ];
                    })->values()->all()
                    : [],
                'list' => $list,
                'q_single' => $qSingle,
                'material' => $material,
                'material_unit' => $materialUnit,
                'weekdays' => $weekdays,
                'schedules' => $gradeSchedules,
                'start_date' => $course->start_date?->toDateString(),
                'end_date' => $course->end_date?->toDateString(),
                'fee_plan_id' => $plan?->id,
                'group_name' => $plan?->group_name,
            ];
        })->values()->all();

        return self::$subjectsByGradeYearCache[$cacheKey] = $result;
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
     * 單月學費：固定月費，該月有任一堂課即收整月，不依堂數比例計算。
     */
    public static function proratedMonthTuitionExact(int $unitPrice, int $attended, int $baseline): float
    {
        if ($unitPrice <= 0 || $attended <= 0) {
            return 0.0;
        }

        return (float) $unitPrice;
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
        /** @var list<int>|null 要收取「耗材 class_day」的科目；null=不收 */
        ?array $chargeMaterialCourseIds = null,
        /**
         * 半年教材明細（可同時收 H1＋H2，金額可手動）。
         *
         * @var list<array{course_id:int, period_year:int, period_half:string, amount:int}>|null
         */
        ?array $materialHalfCharges = null,
        /**
         * 各科帳期月份（key=course_id，值為 "Y-n"）；未給的科目沿用全部帳期月份。
         *
         * @var array<int, list<string>>|null
         */
        ?array $courseMonths = null,
    ): array {
        $chargeMaterialSet = array_fill_keys(
            array_map('intval', $chargeMaterialCourseIds ?? []),
            true
        );
        /** @var array<int, list<array{period_year:int, period_half:string, amount:int}>> $halfChargesByCourse */
        $halfChargesByCourse = [];
        foreach ($materialHalfCharges ?? [] as $charge) {
            $cid = (int) ($charge['course_id'] ?? 0);
            $amount = (int) ($charge['amount'] ?? 0);
            $half = (string) ($charge['period_half'] ?? '');
            $year = (int) ($charge['period_year'] ?? 0);
            if ($cid <= 0 || $amount <= 0 || ! in_array($half, ['H1', 'H2'], true) || $year <= 0) {
                continue;
            }
            $halfChargesByCourse[$cid][] = [
                'period_year' => $year,
                'period_half' => $half,
                'amount' => $amount,
            ];
        }
        $subjects = collect(self::subjectsForStudent($student))->keyBy('id');
        $selected = collect($courseIds)
            ->map(fn ($id) => $subjects->get((int) $id))
            ->filter(fn (?array $subject): bool => $subject !== null && ! empty($subject['fee_plan_id']))
            ->values();

        $selectedIds = $selected->pluck('id')->map(fn ($id) => (int) $id)->all();
        $normalizedSessions = self::normalizeSessions($sessions, $startDate, $selectedIds);

        /** @var array<int, array<string, true>> $courseMonthSet */
        $courseMonthSet = [];
        foreach ($courseMonths ?? [] as $cid => $keys) {
            if (! is_array($keys) || ! in_array((int) $cid, $selectedIds, true)) {
                continue;
            }
            foreach ($keys as $key) {
                if (is_string($key) && preg_match('/^(\d{4})-(\d{1,2})$/', $key, $match)) {
                    $courseMonthSet[(int) $cid][((int) $match[1]).'-'.((int) $match[2])] = true;
                }
            }
        }
        if ($courseMonthSet !== []) {
            $normalizedSessions = array_values(array_filter(
                $normalizedSessions,
                function (array $session) use ($courseMonthSet): bool {
                    $set = $courseMonthSet[(int) $session['course_id']] ?? null;
                    if ($set === null) {
                        return true;
                    }
                    $d = Carbon::parse($session['date']);

                    return isset($set[$d->year.'-'.$d->month]);
                }
            ));
        }

        $allDates = array_values(array_unique(array_column($normalizedSessions, 'date')));
        sort($allDates);
        $months = self::monthsFromDates($allDates);
        foreach ($courseMonthSet as $set) {
            foreach (array_keys($set) as $key) {
                [$y, $m] = array_map('intval', explode('-', $key));
                $months[] = ['y' => $y, 'm' => $m];
            }
        }
        $months = collect($months)
            ->unique(fn (array $m): string => $m['y'].'-'.$m['m'])
            ->sortBy(fn (array $m): int => $m['y'] * 12 + $m['m'])
            ->values()
            ->all();

        $bundlePrices = self::bundlePrices($student, $subjects->all(), $selectedIds);

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

        $subjectsById = $subjects->all();

        foreach ($selected as $subject) {
            $isCustom = ($subject['unit'] ?? 'month') === 'custom_period';
            $planSubjectCount = self::feePlanSubjectCount(
                $subject,
                $subjectsById,
                $selectedIds,
                $student,
            );
            $bundle = $isCustom || $payCycle === 'monthly' ? null : ($bundlePrices[(int) $subject['id']] ?? null);
            if ($bundle !== null && $bundle['price'] >= (int) $subject['q_single']) {
                $bundle = null;
            }
            // list / q_single / bundle 皆為「一個月」單價；學費＝月價 × 帳期月數
            $unitPrice = match (true) {
                $payCycle === 'monthly' => (int) $subject['list'],
                $bundle !== null => $bundle['price'],
                default => (int) $subject['q_single'],
            };

            $subjectDates = $datesByCourse[(int) $subject['id']] ?? [];
            $ownMonthSet = $courseMonthSet[(int) $subject['id']] ?? null;
            $subjectMonths = $ownMonthSet === null
                ? $months
                : array_values(array_filter(
                    $months,
                    fn (array $m): bool => isset($ownMonthSet[$m['y'].'-'.$m['m']])
                ));
            $monthCount = count($subjectMonths);
            $baseline = self::billingBaselineSessions($subject['weekdays'] ?? []);
            /** @var array<string, array{amount:int, attended:int, baseline:int}> $tuitionMonths */
            $tuitionMonths = [];
            $isBlock = ($subject['unit'] ?? 'month') === 'session_block';
            $monthsUsed = $monthCount;
            $tuition = 0;

            if ($isCustom) {
                // 自訂期別：依報科數取每月單價；一期應收＝月價×月數（期中＝剩餘月）
                $periods = is_array($subject['periods'] ?? null) ? $subject['periods'] : [];
                /** @var array<string, array{monthly:int, keys:list<string>}> $groups */
                $groups = [];
                foreach ($subjectMonths as $month) {
                    $y = (int) $month['y'];
                    $m = (int) $month['m'];
                    $key = $y.'-'.$m;
                    $attended = self::countDatesInMonth($subjectDates, $y, $m);
                    $tuitionMonths[$key] = [
                        'amount' => 0,
                        'attended' => $attended,
                        'baseline' => $baseline,
                    ];
                    $period = CustomBillingPeriod::periodForMonth($periods, $y, $m);
                    if ($period === null) {
                        continue;
                    }
                    $monthly = CustomBillingPeriod::priceForSubjectCount($period, $planSubjectCount);
                    $groupKey = $period['start_key'].'-'.$period['end_key'];
                    $groups[$groupKey] ??= [
                        'monthly' => $monthly,
                        'keys' => [],
                    ];
                    $groups[$groupKey]['keys'][] = $key;
                    if ($unitPrice === (int) $subject['list'] || $groups[$groupKey]['keys'] === [$key]) {
                        $unitPrice = $monthly;
                    }
                }
                foreach ($groups as $group) {
                    if ($group['keys'] === []) {
                        continue;
                    }
                    $amounts = self::allocateRoundedAmounts(
                        array_fill(0, count($group['keys']), (float) $group['monthly'])
                    );
                    foreach ($group['keys'] as $index => $key) {
                        $tuitionMonths[$key]['amount'] = (int) $amounts[$index];
                        $tuition += (int) $amounts[$index];
                    }
                }
            } elseif ($isBlock) {
                // 按期：不依出席比例，每月收滿額月價
                $tuition = $unitPrice * max(0, $monthCount);
                foreach ($subjectMonths as $month) {
                    $key = ((int) $month['y']).'-'.((int) $month['m']);
                    $attended = self::countDatesInMonth($subjectDates, (int) $month['y'], (int) $month['m']);
                    $tuitionMonths[$key] = [
                        'amount' => $unitPrice,
                        'attended' => $attended,
                        'baseline' => $baseline,
                    ];
                }
            } else {
                foreach ($subjectMonths as $month) {
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

            $unit = (string) ($subject['material_unit'] ?? 'term');
            if ($unit === 'class_day') {
                [$material, $materialMonths, $materialNote] = self::materialForSubject(
                    $subject,
                    $subjectDates,
                    $subjectMonths,
                    isset($chargeMaterialSet[(int) $subject['id']]),
                );
            } else {
                [$material, $materialMonths, $materialNote] = self::materialForHalfCharges(
                    $subject,
                    $subjectMonths,
                    $halfChargesByCourse[(int) $subject['id']] ?? [],
                );
            }

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
                'tuition' => $isBlock || $isCustom ? $tuition : 0,
                'material' => $material,
                'material_unit' => (string) ($subject['material_unit'] ?? 'term'),
                'material_months' => $materialMonths,
                'tuition_months' => $tuitionMonths,
                'material_note' => $materialNote,
                'is_block' => $isBlock || $isCustom,
                'billing_months' => $subjectMonths,
                'bundle_name' => $bundle['name'] ?? null,
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
            'months' => $months,
            'month_breakdown' => $monthBreakdown,
            'lines' => $lines,
        ];
    }

    /**
     * 適用此學生（年級、學年）的啟用中組合優惠。
     *
     * @return list<array{id:int, name:string, min_courses:int, sort_order:int, course_ids:list<int>, course_prices:array<int, int|null>}>
     */
    public static function bundlesForStudent(Student $student): array
    {
        if (! Schema::hasTable('course_bundles')) {
            return [];
        }

        return CourseBundle::query()
            ->with('courses:id')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('grade_level_id')->orWhere('grade_level_id', $student->grade_level_id))
            ->where(function ($q) use ($student): void {
                $q->whereNull('academic_year_id');
                if ($student->academic_year_id !== null) {
                    $q->orWhere('academic_year_id', $student->academic_year_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (CourseBundle $bundle): array {
                $courseIds = $bundle->courses->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $coursePrices = [];
                foreach ($bundle->courses as $course) {
                    $id = (int) $course->id;
                    $raw = $course->pivot->price ?? null;
                    $coursePrices[$id] = $raw === null ? null : (int) $raw;
                }

                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'min_courses' => $bundle->min_courses,
                    'sort_order' => (int) $bundle->sort_order,
                    'course_ids' => $courseIds,
                    'course_prices' => $coursePrices,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 各科適用的組合優惠價。
     * 是否「符合門檻」仍看報名中科目（本次＋已報），以便分科繳費維持雙科價。
     * 多個組合同時符合時優先序：
     * 1) 本次勾選命中科數較多（專屬組合優於「已報其他科」觸發的通用雙科）
     * 2) 報名中命中科數較多 → 組合課全報齊 → 組合較小 → sort_order
     * 該組合若將此科留白，則維持原價。
     *
     * @param  array<int, array<string, mixed>>  $subjectsById
     * @param  list<int>  $selectedIds
     * @return array<int, array{price:int, name:string}>
     */
    public static function bundlePrices(Student $student, array $subjectsById, array $selectedIds): array
    {
        $selectedSet = [];
        foreach ($selectedIds as $id) {
            $selectedSet[(int) $id] = true;
        }

        $enrolled = $selectedSet;
        foreach (PromotionCourses::intendedOrSnapshotCourseIds($student) as $id) {
            $subject = $subjectsById[(int) $id] ?? null;
            if (is_array($subject) && ! empty($subject['fee_plan_id'])) {
                $enrolled[(int) $id] = true;
            }
        }

        /** @var list<array{bundle:array<string, mixed>, selected_matched:int, matched:int, exact:bool, size:int}> $qualified */
        $qualified = [];
        foreach (self::bundlesForStudent($student) as $bundle) {
            $matchedIds = array_values(array_filter(
                $bundle['course_ids'],
                fn (int $id): bool => isset($enrolled[$id])
            ));
            $matched = count($matchedIds);
            if ($matched < max(1, (int) $bundle['min_courses'])) {
                continue;
            }
            $selectedMatched = count(array_filter(
                $bundle['course_ids'],
                fn (int $id): bool => isset($selectedSet[$id])
            ));
            $size = count($bundle['course_ids']);
            $qualified[] = [
                'bundle' => $bundle,
                'selected_matched' => $selectedMatched,
                'matched' => $matched,
                'exact' => $matched === $size,
                'size' => $size,
            ];
        }

        $out = [];
        foreach ($selectedIds as $courseId) {
            $courseId = (int) $courseId;
            $candidates = array_values(array_filter(
                $qualified,
                fn (array $row): bool => in_array($courseId, $row['bundle']['course_ids'], true)
            ));
            if ($candidates === []) {
                continue;
            }

            usort($candidates, function (array $a, array $b): int {
                // 本次收款勾到的科數最重要：避免已報數學讓「英數雙科」蓋過「高瞻＋英文」
                if ($a['selected_matched'] !== $b['selected_matched']) {
                    return $b['selected_matched'] <=> $a['selected_matched'];
                }
                if ($a['matched'] !== $b['matched']) {
                    return $b['matched'] <=> $a['matched'];
                }
                // 報科數門檻較高者優先（三科價優於雙科價）
                $minA = (int) ($a['bundle']['min_courses'] ?? 0);
                $minB = (int) ($b['bundle']['min_courses'] ?? 0);
                if ($minA !== $minB) {
                    return $minB <=> $minA;
                }
                if ($a['exact'] !== $b['exact']) {
                    return $a['exact'] ? -1 : 1;
                }
                if ($a['size'] !== $b['size']) {
                    return $a['size'] <=> $b['size'];
                }
                $orderA = (int) ($a['bundle']['sort_order'] ?? 0);
                $orderB = (int) ($b['bundle']['sort_order'] ?? 0);
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }

                return ((int) $a['bundle']['id']) <=> ((int) $b['bundle']['id']);
            });

            $best = $candidates[0]['bundle'];
            $price = $best['course_prices'][$courseId]
                ?? $best['course_prices'][(string) $courseId]
                ?? null;
            if ($price === null) {
                continue;
            }
            $out[$courseId] = [
                'price' => (int) $price,
                'name' => (string) $best['name'],
            ];
        }

        return $out;
    }

    /**
     * 同一收費設定下，學生報名中（本次勾選＋已報）的科數，供單／雙／三科價使用。
     *
     * @param  array<string, mixed>  $subject
     * @param  array<int, array<string, mixed>>  $subjectsById
     * @param  list<int>  $selectedIds
     */
    private static function feePlanSubjectCount(
        array $subject,
        array $subjectsById,
        array $selectedIds,
        Student $student,
    ): int {
        $planId = $subject['fee_plan_id'] ?? null;
        if ($planId === null) {
            return 1;
        }

        $ids = [];
        foreach ($selectedIds as $id) {
            $peer = $subjectsById[(int) $id] ?? null;
            if (is_array($peer) && ($peer['fee_plan_id'] ?? null) == $planId) {
                $ids[(int) $id] = true;
            }
        }
        foreach (PromotionCourses::intendedOrSnapshotCourseIds($student) as $id) {
            $peer = $subjectsById[(int) $id] ?? null;
            if (is_array($peer) && ($peer['fee_plan_id'] ?? null) == $planId) {
                $ids[(int) $id] = true;
            }
        }

        return max(1, count($ids));
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
            $key = $d->year.'-'.str_pad((string) $d->month, 2, '0', STR_PAD_LEFT);
            $map[$key] = ['y' => (int) $d->year, 'm' => (int) $d->month];
        }
        ksort($map);

        return array_values($map);
    }

    /**
     * 教材年費換算半年金額（÷2）。1–6、7–12 各收一次。
     */
    public static function semiAnnualMaterialFee(int $annualOrTermFee): int
    {
        if ($annualOrTermFee <= 0) {
            return 0;
        }

        return (int) round($annualOrTermFee / 2);
    }

    /**
     * @deprecated 改用 semiAnnualMaterialFee；保留給舊文案／相容。
     */
    public static function monthlyMaterialFee(int $annualOrTermFee): int
    {
        if ($annualOrTermFee <= 0) {
            return 0;
        }

        return (int) round($annualOrTermFee / 12);
    }

    /**
     * 曆月所屬半年：H1=1–6、H2=7–12。
     *
     * @return array{0:int,1:string} [year, 'H1'|'H2']
     */
    public static function halfYearOf(int $year, int $month): array
    {
        return [$year, $month <= 6 ? 'H1' : 'H2'];
    }

    public static function halfYearLabel(int $year, string $half): string
    {
        return $half === 'H1'
            ? sprintf('%d上半年', $year)
            : sprintf('%d下半年', $year);
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
    private static function materialForSubject(
        array $subject,
        array $dates,
        array $months,
        bool $chargeTermMaterial = false,
    ): array {
        $fee = (int) ($subject['material'] ?? 0);
        $unit = (string) ($subject['material_unit'] ?? 'term');

        if ($fee <= 0) {
            return [0, [], null];
        }

        if ($unit === 'class_day') {
            // 耗材按日：需打勾才收
            if (! $chargeTermMaterial || $dates === []) {
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

        // 半年教材：需明確打勾才收；金額＝年費÷2，掛在帳期第一個月
        // （保留給舊呼叫；新流程請用 materialForHalfCharges）
        if (! $chargeTermMaterial || $months === []) {
            return [0, [], null];
        }
        $semi = self::semiAnnualMaterialFee($fee);
        if ($semi <= 0) {
            return [0, [], null];
        }

        $first = $months[0];
        $y = (int) $first['y'];
        $m = (int) $first['m'];
        [$hy, $half] = self::halfYearOf($y, $m);
        $monthKey = $y.'-'.$m;
        $note = sprintf(
            '教材 %s %s（年 %s ÷ 2，打勾收取）',
            self::halfYearLabel($hy, $half),
            number_format($semi),
            number_format($fee)
        );

        return [
            $semi,
            [$monthKey => ['amount' => $semi, 'days' => 0]],
            $note,
        ];
    }

    /**
     * 半年教材（可同時收多段、金額可手動），合計掛在帳期第一個月。
     *
     * @param  array{material:int, name?:string}  $subject
     * @param  list<array{y:int,m:int}>  $months
     * @param  list<array{period_year:int, period_half:string, amount:int}>  $charges
     * @return array{0:int,1:array<string, array{amount:int, days:int}>,2:?string}
     */
    private static function materialForHalfCharges(
        array $subject,
        array $months,
        array $charges,
    ): array {
        if ($charges === [] || $months === []) {
            return [0, [], null];
        }

        $total = 0;
        $parts = [];
        $byMonth = [];
        $first = $months[0];
        foreach ($charges as $charge) {
            $amount = (int) ($charge['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $total += $amount;
            $year = (int) $charge['period_year'];
            $half = (string) $charge['period_half'];
            $parts[] = sprintf('%s %s', self::halfYearLabel($year, $half), number_format($amount));

            // 掛在帳期中第一個落在該半年的月份；帳期不含該半年則掛第一個月
            $target = $first;
            foreach ($months as $month) {
                [$my, $mh] = self::halfYearOf((int) $month['y'], (int) $month['m']);
                if ($my === $year && $mh === $half) {
                    $target = $month;
                    break;
                }
            }
            $key = ((int) $target['y']).'-'.((int) $target['m']);
            $byMonth[$key] = ['amount' => ($byMonth[$key]['amount'] ?? 0) + $amount, 'days' => 0];
        }

        if ($total <= 0) {
            return [0, [], null];
        }

        $annual = (int) ($subject['material'] ?? 0);
        $note = sprintf(
            '教材 %s（年費 %s，半年收取）',
            implode('＋', $parts),
            number_format($annual)
        );

        return [$total, $byMonth, $note];
    }
}
