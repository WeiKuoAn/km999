<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Reconciliation;
use App\Models\Student;

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

            $intended = is_array($student->intended_course_ids) ? $student->intended_course_ids : [];
            $courseIds = $intended !== []
                ? array_values(array_unique(array_map('intval', $intended)))
                : $snapshot['course_ids'];
            if ($courseIds === []) {
                continue;
            }

            $payCycle = $snapshot['pay_cycle'];
            $plan = BillingRenewal::perCourseNextPeriod(
                $student,
                $courseIds,
                BillingRenewal::nextStartDate($snapshot['end_year'], $snapshot['end_month']),
                $payCycle,
                true,
            );
            $startDate = $plan['start_date'];
            $months = $plan['months'];
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

            /** @var array<int, list<array{y:int,m:int}>> $monthsByCourse */
            $monthsByCourse = [];
            foreach ($plan['course_months'] as $cid => $labels) {
                $monthsByCourse[(int) $cid] = array_map(function (string $label): array {
                    [$y, $m] = array_map('intval', explode('-', $label));

                    return ['y' => $y, 'm' => $m];
                }, $labels);
            }

            // 下一期已繳 → 不列入
            $conflict = false;
            foreach ($monthsByCourse as $cid => $courseMonthList) {
                if (BillingRenewal::hasPaidConflicts($student, [$cid], $courseMonthList)) {
                    $conflict = true;
                    break;
                }
            }
            if ($conflict) {
                continue;
            }

            $existing = Reconciliation::query()
                ->where('student_id', $student->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($monthsByCourse): void {
                    foreach ($monthsByCourse as $cid => $courseMonthList) {
                        foreach ($courseMonthList as $month) {
                            $query->orWhere(fn ($w) => $w
                                ->where('course_id', $cid)
                                ->where('billing_year', $month['y'])
                                ->where('billing_month', $month['m']));
                        }
                    }
                })
                ->get(['expected_amount', 'paid_amount', 'status', 'course_id']);

            $feeSource = 'estimate';
            $note = '';
            if ($existing->isNotEmpty()) {
                $unpaid = $existing->where('status', 'unpaid');
                if ($unpaid->isEmpty()) {
                    // 僅有已繳以外的狀態且無未繳 → 略過
                    continue;
                }
                $fee = (int) $unpaid->sum('expected_amount');
                $feeSource = 'unpaid';
            } else {
                $sessions = $plan['sessions'];
                if ($sessions === []) {
                    continue;
                }
                $subjectsById = collect(EnrollmentPricing::subjectsForStudent($student))->keyBy('id')->all();
                $materialCharges = MaterialHalfYear::autoChargesForCourseMonths($student, $plan['course_months'], $subjectsById);
                $quote = EnrollmentPricing::quote(
                    $student,
                    $courseIds,
                    $payCycle,
                    $sessions,
                    0,
                    $startDate,
                    [],
                    $materialCharges,
                    $plan['split'] ? $plan['course_months'] : null,
                );
                $fee = (int) ($quote['grand_total'] ?? 0);
                if ($fee <= 0 || ($quote['lines'] ?? []) === []) {
                    continue;
                }
                $materialTotal = (int) ($quote['material_total'] ?? 0);
                if ($materialTotal > 0) {
                    $halves = collect($materialCharges)
                        ->map(fn (array $c): string => EnrollmentPricing::halfYearLabel($c['period_year'], $c['period_half']))
                        ->unique()
                        ->implode('、');
                    $note = sprintf('含教材 %s（%s）', number_format($materialTotal), $halves);
                }
            }

            $subjects = self::subjectLabels($courseIds);
            $periodLabel = $plan['split']
                ? self::splitPeriodLabel($monthsByCourse)
                : self::periodLabel($startYear, $startMonth, $endYear, $endMonth);

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
                'note' => $note,
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
     * 各科帳期不同時：「數學、英文 1–3月；理化 2–4月」。
     *
     * @param  array<int, list<array{y:int,m:int}>>  $monthsByCourse
     */
    private static function splitPeriodLabel(array $monthsByCourse): string
    {
        $groups = [];
        foreach ($monthsByCourse as $cid => $courseMonths) {
            if ($courseMonths === []) {
                continue;
            }
            $first = $courseMonths[0];
            $last = $courseMonths[array_key_last($courseMonths)];
            $label = self::periodLabel($first['y'], $first['m'], $last['y'], $last['m']);
            $groups[$label][] = (int) $cid;
        }

        $parts = [];
        foreach ($groups as $label => $ids) {
            $parts[] = implode('、', self::subjectLabels($ids)).' '.$label;
        }

        return implode('；', $parts);
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
