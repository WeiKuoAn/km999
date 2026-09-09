<?php

namespace App\Support;

use App\Models\Student;
use Carbon\Carbon;

/**
 * 國三全科年繳方案：總額 120,000（含教材），7–12 月共 6 期，每月合計 20,000 均攤至各科。
 * 條件：國三＋年繳＋起算日為 7 月＋選齊該年級有價目的全部科目。
 */
final class Grade9AnnualPackage
{
    public const TOTAL = 120_000;

    public const MONTHLY_TOTAL = 20_000;

    public const START_MONTH = 7;

    public const END_MONTH = 12;

    public static function isGrade9(Student $student): bool
    {
        $grade = $student->gradeLevel;
        if ($grade === null) {
            return false;
        }

        return $grade->name === '國三' || (int) $grade->code === 9;
    }

    /**
     * 國三收費標準有綁定價目的科目 id（須全部勾選才算全科）。
     *
     * @return list<int>
     */
    public static function requiredCourseIds(Student $student): array
    {
        if (! self::isGrade9($student)) {
            return [];
        }

        return collect(EnrollmentPricing::subjectsForStudent($student))
            ->filter(fn (array $s): bool => ! empty($s['fee_plan_id']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
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
        if ($payCycle !== 'annual' || ! self::isGrade9($student)) {
            return false;
        }
        if ($startDate === null || $startDate === '') {
            return false;
        }
        $start = Carbon::parse($startDate);
        if ((int) $start->month !== self::START_MONTH) {
            return false;
        }

        $required = self::requiredCourseIds($student);
        if ($required === []) {
            return false;
        }

        $selected = array_values(array_unique(array_map('intval', $courseIds)));
        sort($selected);

        return $selected === $required;
    }

    /**
     * @return array{
     *   eligible:bool,
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
        $required = self::requiredCourseIds($student);
        $eligible = self::isGrade9($student) && $required !== [];

        return [
            'eligible' => $eligible,
            'required_course_ids' => $required,
            'required_count' => count($required),
            'total' => self::TOTAL,
            'monthly_total' => self::MONTHLY_TOTAL,
            'months_label' => '7–12 月（6 期）',
            'reason' => $eligible
                ? null
                : (self::isGrade9($student) ? '國三尚未設定有價目的科目' : '僅國三適用'),
        ];
    }

    /**
     * 產生與 EnrollmentPricing::quote 相容的計價結果（教材＝0，已含在方案內）。
     *
     * @param  list<int>  $courseIds
     * @return array{
     *   tuition_total:int,
     *   material_total:int,
     *   grand_total:int,
     *   months:list<array{y:int,m:int}>,
     *   month_breakdown:list<array{y:int,m:int,tuition:int,material:int,subtotal:int}>,
     *   lines:list<array<string,mixed>>,
     *   package:array{code:string,label:string,total:int,monthly_total:int}
     * }
     */
    public static function quote(Student $student, array $courseIds, string $startDate): array
    {
        $start = Carbon::parse($startDate);
        $year = (int) $start->year;
        $subjects = collect(EnrollmentPricing::subjectsForStudent($student))->keyBy('id');
        $ids = array_values(array_unique(array_map('intval', $courseIds)));
        sort($ids);

        $months = [];
        for ($m = self::START_MONTH; $m <= self::END_MONTH; $m++) {
            $months[] = ['y' => $year, 'm' => $m];
        }

        $n = count($ids);
        $base = intdiv(self::MONTHLY_TOTAL, $n);
        $rem = self::MONTHLY_TOTAL % $n;

        $lines = [];
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

        foreach ($ids as $index => $courseId) {
            $subject = $subjects->get($courseId);
            $share = $base + ($index < $rem ? 1 : 0);
            $tuitionMonths = [];
            foreach ($months as $month) {
                $key = ((int) $month['y']).'-'.((int) $month['m']);
                $tuitionMonths[$key] = [
                    'amount' => $share,
                    'attended' => 0,
                    'baseline' => 0,
                ];
                $monthAgg[$key]['tuition'] += $share;
            }

            $lines[] = [
                'course_id' => $courseId,
                'course_name' => is_array($subject) ? (string) ($subject['name'] ?? '課目') : '課目',
                'pricing_group' => is_array($subject) ? (string) ($subject['pricing_group'] ?? '') : '',
                'unit' => is_array($subject) ? (string) ($subject['unit'] ?? 'month') : 'month',
                'unit_price' => $share,
                'months' => count($months),
                'tuition' => $share * count($months),
                'material' => 0,
                'material_unit' => 'term',
                'material_months' => [],
                'tuition_months' => $tuitionMonths,
                'material_note' => '國三全科年繳方案（含教材，每月均攤）',
            ];
        }

        $monthBreakdown = [];
        foreach ($months as $month) {
            $key = ((int) $month['y']).'-'.((int) $month['m']);
            $tuition = (int) $monthAgg[$key]['tuition'];
            $monthBreakdown[] = [
                'y' => (int) $month['y'],
                'm' => (int) $month['m'],
                'tuition' => $tuition,
                'material' => 0,
                'subtotal' => $tuition,
            ];
        }

        return [
            'tuition_total' => self::TOTAL,
            'material_total' => 0,
            'grand_total' => self::TOTAL,
            'months' => $months,
            'month_breakdown' => $monthBreakdown,
            'lines' => $lines,
            'package' => [
                'code' => 'grade9_annual_all',
                'label' => '國三全科年繳（含教材）',
                'total' => self::TOTAL,
                'monthly_total' => self::MONTHLY_TOTAL,
            ],
        ];
    }

    /** 建議起算日：當年 7/1（若已過 7 月則用當年；否則仍用 start 所屬年） */
    public static function suggestedJulyStart(?string $asOf = null): string
    {
        $d = Carbon::parse($asOf ?? Carbon::today()->toDateString());

        return Carbon::create($d->year, self::START_MONTH, 1)->toDateString();
    }
}
