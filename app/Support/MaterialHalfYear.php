<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentMaterialPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * 教材半年制：1–6＝H1、7–12＝H2；打勾才收，已收過該半年則註記並鎖定。
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
     * @param  list<int>  $courseIds
     * @return list<array{
     *   course_id:int,
     *   can_charge:bool,
     *   amount:int,
     *   period_year:int,
     *   period_half:string,
     *   period_label:string,
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
        $period = self::periodFromDate($asOfDate);
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
        if ($courseIds === []) {
            return [];
        }

        $paidMap = [];
        if (Schema::hasTable('student_material_payments')) {
            $paidMap = StudentMaterialPayment::query()
                ->where('student_id', $student->id)
                ->where('period_year', $period['year'])
                ->where('period_half', $period['half'])
                ->whereIn('course_id', $courseIds)
                ->get(['course_id', 'paid_at', 'amount'])
                ->keyBy(fn ($row) => (int) $row->course_id)
                ->all();
        }

        $out = [];
        foreach ($courseIds as $courseId) {
            $subject = $subjectsById[$courseId] ?? null;
            $unit = is_array($subject) ? (string) ($subject['material_unit'] ?? 'term') : 'term';
            $fee = is_array($subject) ? (int) ($subject['material'] ?? 0) : 0;

            if ($unit === 'class_day' || $fee <= 0) {
                continue;
            }

            $amount = EnrollmentPricing::semiAnnualMaterialFee($fee);
            $paid = $paidMap[$courseId] ?? null;
            $paidAt = null;
            if ($paid !== null && $paid->paid_at) {
                $paidAt = $paid->paid_at instanceof Carbon
                    ? $paid->paid_at->toDateString()
                    : substr((string) $paid->paid_at, 0, 10);
            }

            $canCharge = $paidAt === null;
            $out[] = [
                'course_id' => $courseId,
                'can_charge' => $canCharge,
                'amount' => $amount,
                'period_year' => $period['year'],
                'period_half' => $period['half'],
                'period_label' => $period['label'],
                'paid_at' => $paidAt,
                'note' => $canCharge
                    ? null
                    : sprintf('已於 %s 收取%s教材', $paidAt, $period['label']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{course_id:int, amount:int}>  $rows
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

        $period = self::periodFromDate($asOfDate);
        $paidAt = Carbon::parse($asOfDate)->toDateString();

        foreach ($rows as $row) {
            $courseId = (int) ($row['course_id'] ?? 0);
            $amount = (int) ($row['amount'] ?? 0);
            if ($courseId <= 0 || $amount <= 0) {
                continue;
            }

            StudentMaterialPayment::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'course_id' => $courseId,
                    'period_year' => $period['year'],
                    'period_half' => $period['half'],
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
