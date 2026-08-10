<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Student;
use App\Models\Reconciliation;
use Carbon\Carbon;

final class PaymentRosterBuilder
{
    /**
     * 尚未繳下一期費用的學生名單（已繳 7–9 → 列出 10–12）。
     *
     * @return list<array{
     *   student_id:int,
     *   student_code:?string,
     *   student_name:string,
     *   grade_name:?string,
     *   subjects_label:string,
     *   subject_marks:list<string>,
     *   period_label:string,
     *   subjects_months:string,
     *   start_year:int,
     *   start_month:int,
     *   end_year:int,
     *   end_month:int,
     *   fee:int,
     *   note:string,
     *   pay_cycle:string,
     *   pay_cycle_label:string,
     *   fee_source:string,
     *   course_ids:list<int>
     * }>
     */
    public static function build(?string $q = null, ?int $year = null): array
    {
        $q = $q !== null ? trim($q) : '';

        $studentIds = Reconciliation::query()
            ->where('status', 'paid')
            ->whereNotNull('course_id')
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($studentIds === []) {
            return [];
        }

        $students = Student::query()
            ->with(['gradeLevel:id,name', 'academicYear:id,year_code,name'])
            ->whereIn('id', $studentIds)
            ->where('status', 'active')
            ->when($q !== '', function ($builder) use ($q): void {
                $builder->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('student_code', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($students as $student) {
            $snapshot = BillingRenewal::lastPaidRenewalSnapshot($student);
            if ($snapshot === null) {
                continue;
            }

            $courseIds = $snapshot['course_ids'];
            if ($courseIds === []) {
                continue;
            }

            $payCycle = $snapshot['pay_cycle'];
            $startDate = BillingRenewal::nextStartDate($snapshot['end_year'], $snapshot['end_month']);
            $months = self::monthsFromStart($startDate, $payCycle);
            if ($months === []) {
                continue;
            }

            $startYear = (int) $months[0]['y'];
            $startMonth = (int) $months[0]['m'];
            $endYear = (int) $months[array_key_last($months)]['y'];
            $endMonth = (int) $months[array_key_last($months)]['m'];

            if ($year !== null && $startYear !== $year && $endYear !== $year) {
                continue;
            }

            // 下一期已全部繳清 → 不列入
            if (BillingRenewal::hasPaidConflicts($student, $courseIds, $months)) {
                continue;
            }

            $keys = collect($months)
                ->map(fn (array $m): int => ((int) $m['y']) * 12 + (int) $m['m'])
                ->all();

            $existing = Reconciliation::query()
                ->where('student_id', $student->id)
                ->whereIn('course_id', $courseIds)
                ->where('status', '!=', 'cancelled')
                ->whereRaw(
                    '(billing_year * 12 + billing_month) in ('.implode(',', array_fill(0, count($keys), '?')).')',
                    $keys
                )
                ->get(['expected_amount', 'paid_amount', 'status', 'course_id']);

            $feeSource = 'estimate';
            if ($existing->isNotEmpty()) {
                $unpaid = $existing->where('status', 'unpaid');
                if ($unpaid->isEmpty()) {
                    // 僅有已繳以外的狀態且無未繳 → 略過
                    continue;
                }
                $fee = (int) $unpaid->sum('expected_amount');
                $feeSource = 'unpaid';
            } else {
                $sessions = BillingRenewal::defaultSessions($student, $courseIds, $startDate, $payCycle);
                if ($sessions === []) {
                    continue;
                }
                $quote = EnrollmentPricing::quote($student, $courseIds, $payCycle, $sessions, 0, $startDate);
                $fee = (int) ($quote['grand_total'] ?? 0);
                if ($fee <= 0 || ($quote['lines'] ?? []) === []) {
                    continue;
                }
            }

            $subjects = self::subjectLabels($courseIds);
            $periodLabel = self::periodLabel($startYear, $startMonth, $endYear, $endMonth);

            $rows[] = [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'student_name' => $student->name,
                'grade_name' => $student->gradeLevel?->name,
                'subjects_label' => $subjects === [] ? '—' : implode('、', $subjects),
                'subject_marks' => $subjects,
                'period_label' => $periodLabel,
                'subjects_months' => ($subjects === [] ? '—' : implode('、', $subjects)).'｜'.$periodLabel,
                'start_year' => $startYear,
                'start_month' => $startMonth,
                'end_year' => $endYear,
                'end_month' => $endMonth,
                'fee' => $fee,
                'note' => '',
                'pay_cycle' => $payCycle,
                'pay_cycle_label' => BillingRenewal::payCycleLabel($payCycle),
                'fee_source' => $feeSource,
                'course_ids' => $courseIds,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['start_year'], $a['start_month'], $a['student_name']]
                <=> [$b['start_year'], $b['start_month'], $b['student_name']];
        });

        return $rows;
    }

    /**
     * @param  list<int>  $courseIds
     * @return list<string>
     */
    private static function subjectLabels(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return Course::query()
            ->with('courseCategory:id,name')
            ->whereIn('id', $courseIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Course $c) => $c->courseCategory?->name ?: $c->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{y:int,m:int}>
     */
    private static function monthsFromStart(string $startDate, string $payCycle): array
    {
        $span = BillingRenewal::monthSpan($payCycle);
        $start = Carbon::parse($startDate)->startOfMonth();
        $months = [];
        for ($i = 0; $i < $span; $i++) {
            $d = $start->copy()->addMonths($i);
            $months[] = ['y' => (int) $d->year, 'm' => (int) $d->month];
        }

        return $months;
    }

    private static function periodLabel(int $startYear, int $startMonth, int $endYear, int $endMonth): string
    {
        if ($startYear === $endYear && $startMonth === $endMonth) {
            return sprintf('%d月', $startMonth);
        }
        if ($startYear === $endYear) {
            return sprintf('%d–%d月', $startMonth, $endMonth);
        }

        return sprintf('%d/%d—%d/%d', $startYear, $startMonth, $endYear, $endMonth);
    }
}
