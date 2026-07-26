<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Reconciliation;
use App\Models\User;
use Carbon\Carbon;

final class TuitionBagUnpaidListBuilder
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_3_MONTHS = '3';

    public const SCOPE_6_MONTHS = '6';

    public const DEFAULT_SCOPE = self::SCOPE_3_MONTHS;

    /**
     * 依學生彙整未繳清（含部分繳費）的月份。
     *
     * @return array{
     *     students: array<int, array{
     *         student_id: int,
     *         student_name: string,
     *         unpaid_months: array<int, array{
     *             year: int,
     *             month: int,
     *             label: string,
     *             total_expected: int,
     *             payment_status: string,
     *             classroom_count: int,
     *             rows: array<int, array<string, mixed>>,
     *         }>,
     *         total_unpaid_amount: int,
     *     }>,
     *     student_count: int,
     *     scan_scope: string,
     *     range_label: string,
     * }
     */
    public function build(
        ?User $user,
        string $studentName = '',
        ?int $courseId = null,
        ?int $teacherId = null,
        string $scanScope = self::DEFAULT_SCOPE,
    ): array {
        $end = Carbon::today()->startOfMonth();
        [$start, $scanScope] = $this->resolveScanRange($scanScope, $end);

        /** @var array<int, array{student_id: int, student_name: string, unpaid_months: array<int, array<string, mixed>>}> $byStudent */
        $byStudent = [];

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $year = (int) $cursor->year;
            $month = (int) $cursor->month;

            $built = (new TuitionBagStatisticsBuilder)->build(
                $year,
                $month,
                $user,
                $studentName,
                $courseId,
                $teacherId,
            );

            foreach ($built['sections'] as $section) {
                $status = (string) ($section['payment_status'] ?? 'none');
                if (! in_array($status, ['unpaid', 'partial'], true)) {
                    continue;
                }

                $studentId = (int) ($section['student_id'] ?? 0);
                if ($studentId === 0) {
                    continue;
                }

                if (! isset($byStudent[$studentId])) {
                    $byStudent[$studentId] = [
                        'student_id' => $studentId,
                        'student_name' => (string) ($section['title'] ?? ''),
                        'unpaid_months' => [],
                    ];
                }

                $billableRows = array_values(array_filter(
                    $section['rows'] ?? [],
                    fn (array $row) => (int) ($row['session_count'] ?? 0) > 0,
                ));

                $unpaidRows = array_values(array_filter(
                    $billableRows,
                    fn (array $row) => ! ($row['is_paid'] ?? false),
                ));

                if ($unpaidRows === []) {
                    continue;
                }

                $byStudent[$studentId]['unpaid_months'][] = [
                    'year' => $year,
                    'month' => $month,
                    'label' => "{$year}年{$month}月",
                    'total_expected' => (int) collect($unpaidRows)->sum('expected_amount'),
                    'payment_status' => $status,
                    'classroom_count' => count($unpaidRows),
                    'rows' => $unpaidRows,
                ];
            }

            $cursor->addMonth();
        }

        $students = collect($byStudent)
            ->map(function (array $student): array {
                $months = collect($student['unpaid_months'])
                    ->sortByDesc(fn (array $m) => sprintf('%04d-%02d', $m['year'], $m['month']))
                    ->values()
                    ->all();

                return [
                    'student_id' => $student['student_id'],
                    'student_name' => $student['student_name'],
                    'unpaid_months' => $months,
                    'total_unpaid_amount' => (int) collect($months)->sum('total_expected'),
                ];
            })
            ->sortBy('student_name')
            ->values()
            ->all();

        return [
            'students' => $students,
            'student_count' => count($students),
            'scan_scope' => $scanScope,
            'range_label' => $this->formatRangeLabel($start, $end),
        ];
    }

    public static function normalizeScanScope(?string $scanScope): string
    {
        return match ($scanScope) {
            self::SCOPE_ALL => self::SCOPE_ALL,
            self::SCOPE_6_MONTHS => self::SCOPE_6_MONTHS,
            self::SCOPE_3_MONTHS => self::SCOPE_3_MONTHS,
            default => self::DEFAULT_SCOPE,
        };
    }

    /**
     * @return array{0: Carbon, 1: string}
     */
    private function resolveScanRange(string $scanScope, Carbon $end): array
    {
        $scanScope = self::normalizeScanScope($scanScope);

        if ($scanScope === self::SCOPE_ALL) {
            return [$this->resolveScanStart($end), self::SCOPE_ALL];
        }

        $monthsBack = $scanScope === self::SCOPE_6_MONTHS ? 6 : 3;

        return [$end->copy()->subMonths($monthsBack - 1), $scanScope];
    }

    private function formatRangeLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameMonth($end)) {
            return $end->format('Y年n月');
        }

        return $start->format('Y年n月').' – '.$end->format('Y年n月');
    }

    private function resolveScanStart(Carbon $end): Carbon
    {
        $candidates = [$end];

        $earliestAttendance = Attendance::query()
            ->whereIn('status', ['present', 'late', 'makeup', 'extra'])
            ->min('class_date');

        if ($earliestAttendance !== null) {
            $candidates[] = Carbon::parse($earliestAttendance)->startOfMonth();
        }

        $earliestReco = Reconciliation::query()
            ->orderBy('billing_year')
            ->orderBy('billing_month')
            ->first(['billing_year', 'billing_month']);

        if ($earliestReco !== null) {
            $candidates[] = Carbon::create(
                (int) $earliestReco->billing_year,
                (int) $earliestReco->billing_month,
                1,
            )->startOfMonth();
        }

        return collect($candidates)
            ->sortBy(fn (Carbon $date) => $date->format('Y-m'))
            ->first();
    }
}
