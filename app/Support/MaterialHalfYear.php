<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentMaterialPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * 教材半年制：1–6＝H1、7–12＝H2；各科可分別勾選，可同時收兩段；已收過該半年則鎖定。
 */
final class MaterialHalfYear
{
    /**
     * @return array{year:int, half:string, label:string}
     */
    public static function periodFromDate(string $ymd): array
    {
        $d = Carbon::parse($ymd);
        [$year, $half] = EnrollmentPricing::halfYearOf((int) $d->year, (int) $d->month);

        return [
            'year' => $year,
            'half' => $half,
            'label' => EnrollmentPricing::halfYearLabel($year, $half),
        ];
    }

    /**
     * 帳期跨到 1 月或 7 月時自動收該半年教材（已收過者略過），金額為年費 ÷ 2。
     *
     * @param  list<int>  $courseIds
     * @param  list<array{y:int|string, m:int|string}>  $months
     * @param  array<int, array<string, mixed>>  $subjectsById
     * @return list<array{course_id:int, period_year:int, period_half:string, amount:int}>
     */
    public static function autoChargesForMonths(
        Student $student,
        array $courseIds,
        array $months,
        array $subjectsById,
    ): array {
        $periods = [];
        foreach ($months as $month) {
            $m = (int) $month['m'];
            if ($m === 1 || $m === 7) {
                $periods[] = ['year' => (int) $month['y'], 'half' => $m === 1 ? 'H1' : 'H2'];
            }
        }
        if ($periods === [] || $courseIds === []) {
            return [];
        }

        $paid = [];
        if (Schema::hasTable('student_material_payments')) {
            StudentMaterialPayment::query()
                ->where('student_id', $student->id)
                ->whereIn('course_id', $courseIds)
                ->whereIn('period_year', array_column($periods, 'year'))
                ->get(['course_id', 'period_year', 'period_half'])
                ->each(function (StudentMaterialPayment $row) use (&$paid): void {
                    $paid[$row->course_id.'-'.$row->period_year.'-'.$row->period_half] = true;
                });
        }

        $out = [];
        foreach ($courseIds as $courseId) {
            $subject = $subjectsById[(int) $courseId] ?? null;
            if (! is_array($subject)) {
                continue;
            }
            $fee = (int) ($subject['material'] ?? 0);
            if (($subject['material_unit'] ?? 'term') === 'class_day' || $fee <= 0) {
                continue;
            }
            foreach ($periods as $period) {
                if (isset($paid[$courseId.'-'.$period['year'].'-'.$period['half']])) {
                    continue;
                }
                $out[] = [
                    'course_id' => (int) $courseId,
                    'period_year' => $period['year'],
                    'period_half' => $period['half'],
                    'amount' => EnrollmentPricing::semiAnnualMaterialFee($fee),
                ];
            }
        }

        return $out;
    }

    /**
     * 回傳 asOf 所屬年與隔年各科 H1／H2 狀態（帳期可能跨年）。
     *
     * @param  list<int>  $courseIds
     * @return list<array{
     *   course_id:int,
     *   can_charge:bool,
     *   amount:int,
     *   period_year:int,
     *   period_half:string,
     *   period_label:string,
     *   months_label:string,
     *   paid_at:?string,
     *   note:?string
     * }>
     */
    public static function statusForCourses(
        Student $student,
        array $courseIds,
        string $asOfDate,
        array $subjectsById,
    ): array {
        $baseYear = (int) Carbon::parse($asOfDate)->year;
        $years = [$baseYear, $baseYear + 1];
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
        if ($courseIds === []) {
            return [];
        }

        $paidRows = [];
        if (Schema::hasTable('student_material_payments')) {
            $paidRows = StudentMaterialPayment::query()
                ->where('student_id', $student->id)
                ->whereIn('period_year', $years)
                ->whereIn('course_id', $courseIds)
                ->whereIn('period_half', ['H1', 'H2'])
                ->get(['course_id', 'period_year', 'period_half', 'paid_at', 'amount']);
        }

        /** @var array<string, object> $paidMap */
        $paidMap = [];
        foreach ($paidRows as $row) {
            $key = ((int) $row->course_id).'-'.((int) $row->period_year).'-'.(string) $row->period_half;
            $paidMap[$key] = $row;
        }

        $out = [];
        foreach ($courseIds as $courseId) {
            $subject = $subjectsById[$courseId] ?? null;
            $unit = is_array($subject) ? (string) ($subject['material_unit'] ?? 'term') : 'term';
            $fee = is_array($subject) ? (int) ($subject['material'] ?? 0) : 0;

            if ($unit === 'class_day' || $fee <= 0) {
                continue;
            }

            $defaultAmount = EnrollmentPricing::semiAnnualMaterialFee($fee);
            foreach ($years as $year) {
                foreach (['H1', 'H2'] as $half) {
                    $key = $courseId.'-'.$year.'-'.$half;
                    $paid = $paidMap[$key] ?? null;
                    $paidAt = null;
                    if ($paid !== null && $paid->paid_at) {
                        $paidAt = $paid->paid_at instanceof Carbon
                            ? $paid->paid_at->toDateString()
                            : substr((string) $paid->paid_at, 0, 10);
                    }
                    $canCharge = $paidAt === null;
                    $label = EnrollmentPricing::halfYearLabel($year, $half);
                    $out[] = [
                        'course_id' => $courseId,
                        'can_charge' => $canCharge,
                        'amount' => $defaultAmount,
                        'period_year' => $year,
                        'period_half' => $half,
                        'period_label' => $label,
                        'months_label' => $half === 'H1' ? '1–6' : '7–12',
                        'paid_at' => $paidAt,
                        'note' => $canCharge
                            ? null
                            : sprintf('已於 %s 收取%s教材', $paidAt, $label),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array{course_id:int, amount:int, period_year?:int, period_half?:string}>  $rows
     */
    public static function recordCharges(
        Student $student,
        array $rows,
        string $asOfDate,
        ?int $userId,
    ): void {
        if (! Schema::hasTable('student_material_payments') || $rows === []) {
            return;
        }

        $fallback = self::periodFromDate($asOfDate);
        $paidAt = Carbon::parse($asOfDate)->toDateString();

        foreach ($rows as $row) {
            $courseId = (int) ($row['course_id'] ?? 0);
            $amount = (int) ($row['amount'] ?? 0);
            if ($courseId <= 0 || $amount <= 0) {
                continue;
            }

            $periodYear = isset($row['period_year']) ? (int) $row['period_year'] : $fallback['year'];
            $periodHalf = isset($row['period_half']) ? (string) $row['period_half'] : $fallback['half'];
            if (! in_array($periodHalf, ['H1', 'H2'], true)) {
                continue;
            }

            StudentMaterialPayment::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'course_id' => $courseId,
                    'period_year' => $periodYear,
                    'period_half' => $periodHalf,
                ],
                [
                    'amount' => $amount,
                    'paid_at' => $paidAt,
                    'paid_by_user_id' => $userId,
                ]
            );
        }
    }
}
