<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\StudentCourseDrop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BillingRenewal
{
    public static function monthSpan(string $payCycle): int
    {
        return match ($payCycle) {
            'monthly' => 1,
            'annual' => 12,
            default => 3,
        };
    }

    public static function payCycleLabel(string $payCycle): string
    {
        return match ($payCycle) {
            'monthly' => '月繳',
            'annual' => '年繳',
            default => '季繳',
        };
    }

    public static function renewButtonLabel(string $payCycle): string
    {
        return match ($payCycle) {
            'monthly' => '產生下一月',
            'annual' => '產生下一年',
            default => '產生下一季',
        };
    }

    public static function nextStartDate(int $endYear, int $endMonth): string
    {
        return Carbon::create($endYear, $endMonth, 1)->addMonth()->startOfMonth()->toDateString();
    }

    /**
     * 該生是否已有任一帳期（含取消）。
     */
    public static function hasPriorPayments(Student $student): bool
    {
        return Reconciliation::query()
            ->where('student_id', $student->id)
            ->exists();
    }

    /**
     * 建議起算日：最後帳期月的下月 1 日；無帳期則 null。
     */
    public static function suggestedStartDate(Student $student): ?string
    {
        $latest = Reconciliation::query()
            ->where('student_id', $student->id)
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->first(['billing_year', 'billing_month']);

        if ($latest === null) {
            return null;
        }

        return self::nextStartDate((int) $latest->billing_year, (int) $latest->billing_month);
    }

    /**
     * 最近一輪收款快照。
     *
     * @return array{
     *   end_year:int,
     *   end_month:int,
     *   pay_cycle:string,
     *   course_ids:list<int>
     * }|null
     */
    public static function lastBillingSnapshot(Student $student): ?array
    {
        $latest = Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->first(['billing_year', 'billing_month', 'pay_cycle']);

        if ($latest === null) {
            return null;
        }

        $endYear = (int) $latest->billing_year;
        $endMonth = (int) $latest->billing_month;

        $payCycle = Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('billing_year', $endYear)
            ->where('billing_month', $endMonth)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('pay_cycle')
            ->where('pay_cycle', '!=', '')
            ->value('pay_cycle');

        $payCycle = is_string($payCycle) && in_array($payCycle, ['monthly', 'quarterly', 'annual'], true)
            ? $payCycle
            : 'quarterly';

        $span = self::monthSpan($payCycle);
        $end = Carbon::create($endYear, $endMonth, 1)->startOfMonth();
        $windowStart = $end->copy()->subMonths($span - 1);
        $startKey = ((int) $windowStart->year) * 12 + (int) $windowStart->month;
        $endKey = $endYear * 12 + $endMonth;

        $courseIds = Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('course_id')
            ->whereRaw('(billing_year * 12 + billing_month) between ? and ?', [$startKey, $endKey])
            ->distinct()
            ->orderBy('course_id')
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($courseIds === []) {
            return null;
        }

        return [
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'pay_cycle' => $payCycle,
            'course_ids' => $courseIds,
        ];
    }

    /**
     * 續期用：最近一輪「已繳」快照（與繳費名單一致），科目已排除停修。
     *
     * @return array{
     *   end_year:int,
     *   end_month:int,
     *   pay_cycle:string,
     *   course_ids:list<int>
     * }|null
     */
    public static function lastPaidRenewalSnapshot(Student $student): ?array
    {
        $latest = Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->first(['billing_year', 'billing_month', 'pay_cycle']);

        if ($latest === null) {
            return null;
        }

        $endYear = (int) $latest->billing_year;
        $endMonth = (int) $latest->billing_month;

        $payCycle = Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('billing_year', $endYear)
            ->where('billing_month', $endMonth)
            ->where('status', 'paid')
            ->whereNotNull('pay_cycle')
            ->where('pay_cycle', '!=', '')
            ->value('pay_cycle');

        $payCycle = is_string($payCycle) && in_array($payCycle, ['monthly', 'quarterly', 'annual'], true)
            ? $payCycle
            : 'quarterly';

        $span = self::monthSpan($payCycle);
        $end = Carbon::create($endYear, $endMonth, 1)->startOfMonth();
        $windowStart = $end->copy()->subMonths($span - 1);
        $startKey = ((int) $windowStart->year) * 12 + (int) $windowStart->month;
        $endKey = $endYear * 12 + $endMonth;

        $courseIds = Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->whereNotNull('course_id')
            ->whereRaw('(billing_year * 12 + billing_month) between ? and ?', [$startKey, $endKey])
            ->distinct()
            ->orderBy('course_id')
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($courseIds === []) {
            return null;
        }

        if (Schema::hasTable('student_course_drops')) {
            $dropped = StudentCourseDrop::query()
                ->where('student_id', $student->id)
                ->whereIn('course_id', $courseIds)
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $courseIds = array_values(array_filter(
                $courseIds,
                fn (int $id) => ! in_array($id, $dropped, true)
            ));
        }

        if ($courseIds === []) {
            return null;
        }

        return [
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'pay_cycle' => $payCycle,
            'course_ids' => $courseIds,
        ];
    }

    /**
     * 明細頁續期按鈕用摘要。
     *
     * @return array{
     *   available:bool,
     *   button_label:string,
     *   start_date:string,
     *   pay_cycle:string,
     *   pay_cycle_label:string,
     *   course_count:int,
     *   span_months:int
     * }|null
     */
    public static function renewalSummary(Student $student): ?array
    {
        $snapshot = self::lastBillingSnapshot($student);
        if ($snapshot === null) {
            return null;
        }

        $startDate = self::nextStartDate($snapshot['end_year'], $snapshot['end_month']);

        return [
            'available' => true,
            'button_label' => self::renewButtonLabel($snapshot['pay_cycle']),
            'start_date' => $startDate,
            'pay_cycle' => $snapshot['pay_cycle'],
            'pay_cycle_label' => self::payCycleLabel($snapshot['pay_cycle']),
            'course_count' => count($snapshot['course_ids']),
            'span_months' => self::monthSpan($snapshot['pay_cycle']),
        ];
    }

    /**
     * 依科目上課日預選堂次（對齊前端 buildDefaultSessionEntries）。
     *
     * @param  list<int>  $courseIds
     * @return list<array{date:string, course_id:int}>
     */
    public static function defaultSessions(
        Student $student,
        array $courseIds,
        string $startDate,
        string $payCycle,
    ): array {
        $span = self::monthSpan($payCycle);
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::create($start->year, $start->month, 1)
            ->addMonths($span)
            ->subDay()
            ->startOfDay();

        $holidaySet = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date'])
            ->map(fn (Holiday $holiday): string => $holiday->date->toDateString())
            ->flip()
            ->all();

        $subjects = collect(EnrollmentPricing::subjectsForStudent($student))->keyBy('id');
        $out = [];

        foreach ($courseIds as $courseId) {
            $courseId = (int) $courseId;
            $subject = $subjects->get($courseId);
            if ($subject === null) {
                continue;
            }
            $weekdays = WeekdayDates::normalize($subject['weekdays'] ?? []);
            if ($weekdays === []) {
                continue;
            }
            foreach (WeekdayDates::inRange($start, $end, $weekdays) as $ymd) {
                if (isset($holidaySet[$ymd])) {
                    continue;
                }
                $out[] = ['date' => $ymd, 'course_id' => $courseId];
            }
        }

        usort(
            $out,
            fn (array $a, array $b): int => $a['date'] <=> $b['date'] ?: $a['course_id'] <=> $b['course_id']
        );

        return $out;
    }

    /**
     * 目標帳期月是否已有「已繳」列。
     *
     * @param  list<int>  $courseIds
     * @param  list<array{y:int,m:int}>  $months
     */
    public static function hasPaidConflicts(Student $student, array $courseIds, array $months): bool
    {
        if ($courseIds === [] || $months === []) {
            return false;
        }

        $keys = collect($months)
            ->map(fn (array $month): int => ((int) $month['y']) * 12 + (int) $month['m'])
            ->all();

        return Reconciliation::query()
            ->where('student_id', $student->id)
            ->whereIn('course_id', $courseIds)
            ->where('status', 'paid')
            ->whereRaw('(billing_year * 12 + billing_month) in ('.implode(',', array_fill(0, count($keys), '?')).')', $keys)
            ->exists();
    }

    /**
     * 將計價結果寫入 reconciliations（與 store 相同邏輯）。
     *
     * @param  array{
     *   tuition_total:int,
     *   material_total:int,
     *   grand_total:int,
     *   months:list<array{y:int,m:int}>,
     *   lines:list<array<string, mixed>>
     * }  $quote
     */
    public static function persistQuote(
        Student $student,
        string $payCycle,
        array $quote,
        int $allowance = 0,
    ): void {
        $months = $quote['months'] ?? [];
        if ($months === [] || ($quote['lines'] ?? []) === []) {
            return;
        }

        DB::transaction(function () use ($student, $payCycle, $quote, $allowance, $months): void {
            $allowanceLeft = $allowance;
            $paidDate = now()->toDateString();
            $settledByUserId = auth()->id();

            $courseIds = collect($quote['lines'] ?? [])
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($courseIds !== [] && Schema::hasTable('student_course_drops')) {
                StudentCourseDrop::query()
                    ->where('student_id', $student->id)
                    ->whereIn('course_id', $courseIds)
                    ->delete();
            }

            foreach ($quote['lines'] as $line) {
                $tuition = (int) $line['tuition'];
                $material = (int) $line['material'];
                $courseTotal = $tuition + $material;

                $share = $quote['grand_total'] + $allowance > 0
                    ? (int) round($allowance * ($courseTotal / max(1, $quote['tuition_total'] + $quote['material_total'])))
                    : 0;
                $share = min($share, $allowanceLeft);
                $allowanceLeft -= $share;

                /** @var array<string, array{amount:int, attended?:int, baseline?:int}> $tuitionMonths */
                $tuitionMonths = $line['tuition_months'] ?? [];
                /** @var array<string, array{amount:int, days:int}> $materialMonths */
                $materialMonths = $line['material_months'] ?? [];
                $materialNote = $line['material_note'] ?? null;

                $monthWeights = [];
                foreach ($months as $month) {
                    $key = ((int) $month['y']).'-'.((int) $month['m']);
                    $monthWeights[$key] = (int) ($tuitionMonths[$key]['amount'] ?? 0)
                        + (int) ($materialMonths[$key]['amount'] ?? 0);
                }
                $weightSum = array_sum($monthWeights);
                $allowanceByMonth = [];
                $assignedAllowance = 0;
                $keys = array_keys($monthWeights);
                foreach ($keys as $index => $key) {
                    if ($index === count($keys) - 1) {
                        $allowanceByMonth[$key] = max(0, $share - $assignedAllowance);
                    } else {
                        $part = $weightSum > 0
                            ? (int) round($share * ($monthWeights[$key] / $weightSum))
                            : 0;
                        $allowanceByMonth[$key] = $part;
                        $assignedAllowance += $part;
                    }
                }

                foreach ($months as $index => $month) {
                    $y = (int) $month['y'];
                    $m = (int) $month['m'];
                    $key = $y.'-'.$m;
                    $monthMaterial = (int) ($materialMonths[$key]['amount'] ?? 0);
                    $monthTuition = (int) ($tuitionMonths[$key]['amount'] ?? 0);
                    $monthAllowance = (int) ($allowanceByMonth[$key] ?? 0);
                    $amount = max(0, $monthTuition + $monthMaterial - $monthAllowance);

                    $noteParts = [
                        sprintf('報名計價｜%s｜單價 %s', $line['course_name'], number_format($line['unit_price'])),
                    ];
                    $attended = (int) ($tuitionMonths[$key]['attended'] ?? 0);
                    $baseline = (int) ($tuitionMonths[$key]['baseline'] ?? 0);
                    if ($baseline > 0 && $attended > 0 && $attended < $baseline) {
                        $noteParts[] = sprintf('比例 %d/%d', $attended, $baseline);
                    }
                    if ($monthMaterial > 0) {
                        $days = (int) ($materialMonths[$key]['days'] ?? 0);
                        if (($line['material_unit'] ?? '') === 'class_day' && $days > 0) {
                            $noteParts[] = sprintf('耗材 %d天 %s', $days, number_format($monthMaterial));
                        } else {
                            $noteParts[] = sprintf('教材／耗材 %s', number_format($monthMaterial));
                        }
                    }
                    if ($monthAllowance > 0) {
                        $noteParts[] = sprintf('折讓 %s', number_format($monthAllowance));
                    }
                    if ($index === 0 && is_string($materialNote) && $materialNote !== '') {
                        $noteParts[] = $materialNote;
                    }

                    Reconciliation::query()->updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'course_id' => $line['course_id'],
                            'billing_year' => $y,
                            'billing_month' => $m,
                        ],
                        [
                            'classroom_id' => null,
                            'expected_amount' => $amount,
                            'paid_amount' => $amount,
                            'paid_date' => $paidDate,
                            'status' => 'paid',
                            'settled_by_user_id' => $settledByUserId,
                            'pay_cycle' => $payCycle,
                            'note' => implode('｜', $noteParts),
                        ]
                    );
                }
            }
        });
    }
}
