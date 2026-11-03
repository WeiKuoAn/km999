<?php

namespace App\Support;

use App\Models\FeePlan;
use App\Models\Student;
use Carbon\Carbon;

/**
 * 年繳固定方案：讀取收費設定 plan_kind=annual_package。
 * 條件：年繳＋起算月＝方案起始月＋勾選科目剛好等於方案科目。
 * （國三全科／全科科研／國社等皆在收費設定建立，不再寫死課名。）
 */
final class Grade9AnnualPackage
{
    /** @deprecated 保留相容 */
    public const TOTAL = 120_000;

    /** @deprecated */
    public const MONTHLY_TOTAL = 20_000;

    public const START_MONTH = 7;

    public const END_MONTH = 12;

    public const PERIOD_COUNT = 6;

    public static function isGrade9(Student $student): bool
    {
        $grade = $student->gradeLevel;
        if ($grade === null) {
            return false;
        }

        return $grade->name === '國三' || (int) $grade->code === 9;
    }

    /**
     * @return list<FeePlan>
     */
    public static function packagePlansForStudent(Student $student): array
    {
        return FeePlan::query()
            ->with('courses:id,name')
            ->where('is_active', true)
            ->where('plan_kind', 'annual_package')
            ->where(function ($builder) use ($student): void {
                $builder->whereNull('grade_level_id')
                    ->orWhere('grade_level_id', $student->grade_level_id);
            })
            ->where(function ($builder) use ($student): void {
                $builder->whereNull('academic_year_id');
                if ($student->academic_year_id !== null) {
                    $builder->orWhere('academic_year_id', $student->academic_year_id);
                }
            })
            ->orderByRaw(
                'CASE WHEN grade_level_id = ? THEN 0 ELSE 1 END',
                [$student->grade_level_id]
            )
            ->when(
                $student->academic_year_id !== null,
                fn ($q) => $q->orderByRaw(
                    'CASE WHEN academic_year_id = ? THEN 0 ELSE 1 END',
                    [$student->academic_year_id]
                )
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return list<array{
     *   code:string,
     *   label:string,
     *   total:int,
     *   monthly_total:int,
     *   required_course_ids:list<int>,
     *   required_count:int,
     *   ready:bool,
     *   start_month:int,
     *   end_month:int,
     *   fee_plan_id:int
     * }>
     */
    public static function packagesForStudent(Student $student): array
    {
        $out = [];
        foreach (self::packagePlansForStudent($student) as $plan) {
            $ids = $plan->courses->pluck('id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $total = max(1, (int) ($plan->package_total ?? 0));
            $monthCount = max(1, CustomBillingPeriod::monthCount(
                $plan->annualStartMonth(),
                $plan->annualEndMonth()
            ));
            $out[] = [
                'code' => 'fee_plan_'.$plan->id,
                'label' => $plan->group_name,
                'total' => $total,
                'monthly_total' => (int) intdiv($total, $monthCount),
                'required_course_ids' => $ids,
                'required_count' => count($ids),
                'ready' => $ids !== [] && $total > 0,
                'start_month' => $plan->annualStartMonth(),
                'end_month' => $plan->annualEndMonth(),
                'fee_plan_id' => (int) $plan->id,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $courseIds
     * @return array{code:string, label:string, total:int, monthly_total:int, start_month:int, end_month:int, fee_plan_id:int}|null
     */
    public static function matchPackage(
        Student $student,
        array $courseIds,
        string $payCycle,
        ?string $startDate,
    ): ?array {
        if ($payCycle !== 'annual' || $startDate === null || $startDate === '') {
            return null;
        }

        $startMonth = (int) Carbon::parse($startDate)->month;
        $selected = array_values(array_unique(array_map('intval', $courseIds)));
        sort($selected);

        foreach (self::packagesForStudent($student) as $pack) {
            if (! $pack['ready']) {
                continue;
            }
            if ((int) $pack['start_month'] !== $startMonth) {
                continue;
            }
            if ($pack['required_course_ids'] === $selected) {
                return [
                    'code' => $pack['code'],
                    'label' => $pack['label'],
                    'total' => $pack['total'],
                    'monthly_total' => $pack['monthly_total'],
                    'start_month' => $pack['start_month'],
                    'end_month' => $pack['end_month'],
                    'fee_plan_id' => $pack['fee_plan_id'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $courseIds
     */
    public static function qualifies(
        Student $student,
        array $courseIds,
        string $payCycle,
        ?string $startDate,
    ): bool {
        return self::matchPackage($student, $courseIds, $payCycle, $startDate) !== null;
    }

    /**
     * @return array{
     *   eligible:bool,
     *   packages:list<array<string,mixed>>,
     *   required_course_ids:list<int>,
     *   required_count:int,
     *   total:int,
     *   monthly_total:int,
     *   months_label:string,
     *   reason:?string
     * }
     */
    public static function metaForStudent(Student $student): array
    {
        $packages = self::packagesForStudent($student);
        $ready = array_values(array_filter($packages, fn (array $p): bool => $p['ready']));
        $eligible = $ready !== [];
        $first = $ready[0] ?? null;

        return [
            'eligible' => $eligible,
            'packages' => $packages,
            'required_course_ids' => $first['required_course_ids'] ?? [],
            'required_count' => $first['required_count'] ?? 0,
            'total' => $first['total'] ?? self::TOTAL,
            'monthly_total' => $first['monthly_total'] ?? self::MONTHLY_TOTAL,
            'months_label' => $first !== null
                ? $first['start_month'].'–'.$first['end_month'].' 月'
                : '7–12 月（6 期）',
            'reason' => $eligible
                ? null
                : '尚未在收費設定建立「年繳方案」',
        ];
    }

    /**
     * @param  list<int>  $courseIds
     * @param  array{code:string, label:string, total:int, monthly_total:int, start_month?:int, end_month?:int}|null  $package
     * @return array<string, mixed>
     */
    public static function quote(
        Student $student,
        array $courseIds,
        string $startDate,
        ?array $package = null,
    ): array {
        $package ??= self::matchPackage($student, $courseIds, 'annual', $startDate);
        if ($package === null) {
            throw new \InvalidArgumentException('找不到符合的年繳方案。');
        }

        $total = (int) $package['total'];
        $startMonth = (int) ($package['start_month'] ?? self::START_MONTH);
        $endMonth = (int) ($package['end_month'] ?? self::END_MONTH);
        $start = Carbon::parse($startDate);
        $year = (int) $start->year;
        $subjects = collect(EnrollmentPricing::subjectsForStudent($student))->keyBy('id');
        $ids = array_values(array_unique(array_map('intval', $courseIds)));
        sort($ids);

        $months = [];
        $cursor = $startMonth;
        $y = $year;
        for ($i = 0; $i < 12; $i++) {
            $months[] = ['y' => $y, 'm' => $cursor];
            if ($cursor === $endMonth) {
                break;
            }
            $cursor++;
            if ($cursor > 12) {
                $cursor = 1;
                $y++;
            }
        }

        $monthAmounts = EnrollmentPricing::allocateRoundedAmounts(
            array_fill(0, count($months), $total / max(1, count($months)))
        );

        $n = max(1, count($ids));
        $linesByCourse = [];
        foreach ($ids as $courseId) {
            $subject = $subjects->get($courseId);
            $linesByCourse[$courseId] = [
                'course_id' => $courseId,
                'course_name' => is_array($subject) ? (string) ($subject['name'] ?? '課目') : '課目',
                'pricing_group' => is_array($subject) ? (string) ($subject['pricing_group'] ?? '') : '',
                'unit' => is_array($subject) ? (string) ($subject['unit'] ?? 'month') : 'month',
                'unit_price' => 0,
                'months' => count($months),
                'tuition' => 0,
                'material' => 0,
                'material_unit' => 'term',
                'material_months' => [],
                'tuition_months' => [],
                'material_note' => $package['label'].'（含教材，每月均攤）',
            ];
        }

        $monthBreakdown = [];
        foreach ($months as $index => $month) {
            $key = ((int) $month['y']).'-'.((int) $month['m']);
            $monthTotal = (int) ($monthAmounts[$index] ?? 0);
            $shares = EnrollmentPricing::allocateRoundedAmounts(
                array_fill(0, $n, $monthTotal / $n)
            );
            foreach ($ids as $i => $courseId) {
                $share = (int) ($shares[$i] ?? 0);
                $linesByCourse[$courseId]['tuition_months'][$key] = [
                    'amount' => $share,
                    'attended' => 0,
                    'baseline' => 0,
                ];
                $linesByCourse[$courseId]['tuition'] += $share;
                $linesByCourse[$courseId]['unit_price'] = max(
                    (int) $linesByCourse[$courseId]['unit_price'],
                    $share
                );
            }
            $monthBreakdown[] = [
                'y' => (int) $month['y'],
                'm' => (int) $month['m'],
                'tuition' => $monthTotal,
                'material' => 0,
                'subtotal' => $monthTotal,
            ];
        }

        return [
            'tuition_total' => $total,
            'material_total' => 0,
            'grand_total' => $total,
            'months' => $months,
            'month_breakdown' => $monthBreakdown,
            'lines' => array_values($linesByCourse),
            'package' => [
                'code' => $package['code'],
                'label' => $package['label'],
                'total' => $total,
                'monthly_total' => (int) ($package['monthly_total'] ?? intdiv($total, max(1, count($months)))),
            ],
        ];
    }

    public static function suggestedJulyStart(?string $asOf = null): string
    {
        $d = Carbon::parse($asOf ?? Carbon::today()->toDateString());

        return Carbon::create($d->year, self::START_MONTH, 1)->toDateString();
    }
}
