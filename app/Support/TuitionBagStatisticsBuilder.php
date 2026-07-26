<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\CourseTuition;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TuitionBagStatisticsBuilder
{
    private const WEEKDAY_ZH = [
        1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六', 7 => '日',
    ];

    private bool $hasSchedulesTable;

    public function __construct()
    {
        $this->hasSchedulesTable = Schema::hasTable('classroom_schedules');
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>, course_options: array<int, array{id: int, name: string}>}
     */
    public function build(
        int $year,
        int $month,
        ?User $user,
        string $studentName = '',
        ?int $courseId = null,
        ?int $teacherId = null,
    ): array {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $classrooms = $this->loadClassrooms($user, $courseId, $teacherId);
        $classroomIds = $classrooms->pluck('id')->all();
        $attendances = $this->loadBillableAttendances($year, $month, $classroomIds);
        $extraSessionKeys = $this->loadExtraSessionKeys($classroomIds, $monthStart, $monthEnd);
        $reconciliations = $this->loadReconciliations($year, $month, $classroomIds);
        $enrollments = $this->loadEnrollmentsForMonth($classroomIds, $attendances, $reconciliations, $studentName);

        if ($studentName !== '') {
            $needle = mb_strtolower($studentName);
            $attendances = $attendances->filter(
                fn (Attendance $a) => $a->student && str_contains(mb_strtolower($a->student->name), $needle)
            );
            $reconciliations = $reconciliations->filter(function (Reconciliation $r) use ($needle, $enrollments, $attendances) {
                $student = $this->resolveStudentForBilling((int) $r->student_id, $enrollments, $attendances);

                return $student !== null && str_contains(mb_strtolower($student->name), $needle);
            });
        }

        $sections = $this->buildByStudent($classrooms, $enrollments, $attendances, $extraSessionKeys, $reconciliations);

        $courseOptions = Course::query()
            ->with('courseCategory')
            ->orderBy('name')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'name' => ($c->courseCategory?->name ? $c->courseCategory->name.' / ' : '').$c->name,
            ])
            ->values()
            ->all();

        return [
            'sections' => $sections,
            'course_options' => $courseOptions,
            'group_by' => 'student',
        ];
    }

    /**
     * @return Collection<int, Classroom>
     */
    private function loadClassrooms(?User $user, ?int $courseId, ?int $teacherId = null): Collection
    {
        $query = Classroom::query()
            ->where('status', 'active')
            ->with(['course.courseCategory', 'course.coursePrices', 'schedules'])
            ->orderBy('name');

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        if ($user?->role === User::ROLE_TEACHER) {
            $teacherIdForUser = $user->teacher?->id;
            if ($teacherIdForUser === null) {
                return collect();
            }
            $query->where('teacher_id', $teacherIdForUser);
        } elseif ($teacherId !== null) {
            $query->where('teacher_id', $teacherId);
        }

        return $query->get();
    }

    /**
     * 在籍報名 + 當月有出勤／繳費紀錄的離班報名（避免調班後歷史學費袋消失）。
     *
     * @param  array<int>  $classroomIds
     * @param  Collection<int, Attendance>  $attendances
     * @param  Collection<string, Reconciliation>  $reconciliations
     * @return Collection<int, Enrollment>
     */
    private function loadEnrollmentsForMonth(
        array $classroomIds,
        Collection $attendances,
        Collection $reconciliations,
        string $studentName = '',
    ): Collection {
        if ($classroomIds === []) {
            return collect();
        }

        $billingPairs = $this->collectBillingPairs($attendances, $reconciliations);

        $enrollments = Enrollment::query()
            ->whereIn('classroom_id', $classroomIds)
            ->whereHas('student', fn ($q) => $q->where('status', 'active'))
            ->where(function ($q) use ($billingPairs) {
                $q->where('status', 'active');
                if ($billingPairs->isEmpty()) {
                    return;
                }
                $q->orWhere(function ($qq) use ($billingPairs) {
                    foreach ($billingPairs as [$studentId, $classroomId]) {
                        $qq->orWhere(function ($q) use ($studentId, $classroomId) {
                            $q->where('student_id', $studentId)
                                ->where('classroom_id', $classroomId);
                        });
                    }
                });
            })
            ->with(['student:id,name,school_segment', 'tuitionRates'])
            ->get();

        if ($studentName !== '') {
            $enrollments = $enrollments->filter(
                fn (Enrollment $e) => $e->student && str_contains(mb_strtolower($e->student->name), mb_strtolower($studentName))
            );
        }

        return $enrollments->values();
    }

    /**
     * @param  Collection<int, Attendance>  $attendances
     * @param  Collection<string, Reconciliation>  $reconciliations
     * @return Collection<int, array{0: int, 1: int}>
     */
    private function collectBillingPairs(Collection $attendances, Collection $reconciliations): Collection
    {
        $pairs = collect();

        foreach ($attendances as $attendance) {
            $pairs->push([
                (int) $attendance->student_id,
                $this->billingClassroomId($attendance),
            ]);
        }

        foreach ($reconciliations as $reconciliation) {
            $pairs->push([
                (int) $reconciliation->student_id,
                (int) $reconciliation->classroom_id,
            ]);
        }

        return $pairs
            ->unique(fn (array $pair) => $pair[0].'|'.$pair[1])
            ->values();
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, Attendance>  $attendances
     * @param  Collection<string, Reconciliation>  $reconciliations
     * @return Collection<int, int>
     */
    private function studentIdsForBilling(
        Collection $enrollments,
        Collection $attendances,
        Collection $reconciliations,
    ): Collection {
        return $enrollments->pluck('student_id')
            ->merge($attendances->pluck('student_id'))
            ->merge($reconciliations->pluck('student_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, Attendance>  $attendances
     * @param  Collection<string, Reconciliation>  $reconciliations
     * @return Collection<int, int>
     */
    private function classroomIdsForStudentBilling(
        int $studentId,
        Collection $enrollments,
        Collection $attendances,
        Collection $reconciliations,
    ): Collection {
        $ids = $enrollments
            ->where('student_id', $studentId)
            ->pluck('classroom_id');

        foreach ($attendances->where('student_id', $studentId) as $attendance) {
            $ids->push($this->billingClassroomId($attendance));
        }

        foreach ($reconciliations as $reconciliation) {
            if ((int) $reconciliation->student_id === $studentId) {
                $ids->push((int) $reconciliation->classroom_id);
            }
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function resolveStudentForBilling(
        int $studentId,
        Collection $enrollments,
        Collection $attendances,
    ): ?Student {
        $student = $enrollments->first(fn (Enrollment $e) => (int) $e->student_id === $studentId)?->student;
        if ($student !== null) {
            return $student;
        }

        return $attendances->first(fn (Attendance $a) => (int) $a->student_id === $studentId)?->student;
    }

    /**
     * @param  array<int>  $classroomIds
     * @return Collection<int, Attendance>
     */
    private function loadBillableAttendances(int $year, int $month, array $classroomIds): Collection
    {
        if ($classroomIds === []) {
            return collect();
        }

        return Attendance::query()
            ->where(function ($q) use ($classroomIds) {
                // 含「實際記在這些班級」與「補課歸帳到這些班級」的出勤（跨班補課）。
                $q->whereIn('classroom_id', $classroomIds)
                    ->orWhereIn('makeup_for_classroom_id', $classroomIds);
            })
            ->whereIn('status', ['present', 'late', 'makeup', 'extra'])
            ->whereYear('class_date', $year)
            ->whereMonth('class_date', $month)
            ->with('student:id,name,school_segment')
            ->orderBy('class_date')
            ->get();
    }

    /**
     * 計算這筆出勤應計入哪一個班級的學費：
     * 補課若有指定「補哪一門課」，歸帳到該原班；否則照實際點名班級。
     */
    private function billingClassroomId(Attendance $a): int
    {
        if (in_array($a->status, ['makeup', 'extra'], true) && $a->makeup_for_classroom_id !== null) {
            return (int) $a->makeup_for_classroom_id;
        }

        return (int) $a->classroom_id;
    }

    /**
     * @param  array<int>  $classroomIds
     * @return array<string, true> keys: "{classroom_id}|{Y-m-d}|{student_id}"
     */
    private function loadExtraSessionKeys(array $classroomIds, Carbon $monthStart, Carbon $monthEnd): array
    {
        if ($classroomIds === [] || ! Schema::hasTable('classroom_extra_sessions')) {
            return [];
        }

        $rows = DB::table('classroom_extra_sessions as ces')
            ->leftJoin('classroom_extra_session_student as cess', 'cess.classroom_extra_session_id', '=', 'ces.id')
            ->whereIn('ces.classroom_id', $classroomIds)
            ->whereDate('ces.session_date', '>=', $monthStart->toDateString())
            ->whereDate('ces.session_date', '<=', $monthEnd->toDateString())
            ->select(['ces.classroom_id', 'ces.session_date', 'cess.student_id'])
            ->get();

        $keys = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->session_date)->toDateString();
            if ($row->student_id === null) {
                $keys["{$row->classroom_id}|{$date}|*"] = true;
            } else {
                $keys["{$row->classroom_id}|{$date}|{$row->student_id}"] = true;
            }
        }

        return $keys;
    }

    /**
     * @param  array<int>  $classroomIds
     * @return Collection<string, Reconciliation> key: "{student_id}|{classroom_id}"
     */
    private function loadReconciliations(int $year, int $month, array $classroomIds): Collection
    {
        if ($classroomIds === []) {
            return collect();
        }

        return Reconciliation::query()
            ->whereIn('classroom_id', $classroomIds)
            ->where('billing_year', $year)
            ->where('billing_month', $month)
            ->get()
            ->keyBy(fn (Reconciliation $r) => "{$r->student_id}|{$r->classroom_id}");
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, Attendance>  $attendances
     * @param  array<string, true>  $extraSessionKeys
     * @param  Collection<string, Reconciliation>  $reconciliations
     * @return array<int, array<string, mixed>>
     */
    private function buildByCourse(
        Collection $classrooms,
        Collection $enrollments,
        Collection $attendances,
        array $extraSessionKeys,
        Collection $reconciliations,
    ): array {
        $sections = [];

        foreach ($classrooms as $classroom) {
            $classEnrollments = $enrollments->where('classroom_id', $classroom->id);
            if ($classEnrollments->isEmpty()) {
                continue;
            }

            $classAttendances = $attendances->filter(
                fn (Attendance $a) => $this->billingClassroomId($a) === (int) $classroom->id
            );
            $weekdayLabel = $this->primaryWeekdayLabel($classroom);
            $courseName = $classroom->course?->name ?? '課程';

            $rows = [];
            foreach ($classEnrollments->sortBy(fn ($e) => $e->student?->name ?? '') as $enrollment) {
                $student = $enrollment->student;
                if ($student === null) {
                    continue;
                }

                $studentAttendances = $classAttendances->where('student_id', $student->id);
                $dateEntries = $this->dateEntriesForStudent(
                    $studentAttendances,
                    $classroom->id,
                    $student->id,
                    $extraSessionKeys,
                );

                $reco = $reconciliations->get("{$student->id}|{$classroom->id}");
                $isPaid = $reco !== null && $reco->status === 'paid';
                $sessionCount = count($dateEntries);
                $expected = (int) ($enrollment->tuition_amount ?? 0) * $sessionCount;
                $payment = $this->paymentFromReconciliation($reco, $expected, $sessionCount);

                $rows[] = $this->rowPayload(
                    $student->name,
                    $dateEntries,
                    $payment,
                    $expected,
                    $isPaid,
                    (int) $classroom->id,
                    (int) ($enrollment->tuition_amount ?? 0),
                );
            }

            if ($rows === []) {
                continue;
            }

            $sections[] = [
                'key' => 'classroom-'.$classroom->id,
                'title' => $courseName,
                'subtitle' => $weekdayLabel !== '' ? '週'.$weekdayLabel : $classroom->name,
                'classroom_name' => $classroom->name,
                'rows' => $rows,
            ];
        }

        return $sections;
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, Attendance>  $attendances
     * @param  array<string, true>  $extraSessionKeys
     * @param  Collection<string, Reconciliation>  $reconciliations
     * @return array<int, array<string, mixed>>
     */
    private function buildByStudent(
        Collection $classrooms,
        Collection $enrollments,
        Collection $attendances,
        array $extraSessionKeys,
        Collection $reconciliations,
    ): array {
        $sections = [];
        $studentIds = $this->studentIdsForBilling($enrollments, $attendances, $reconciliations);

        foreach ($studentIds as $studentId) {
            $student = $this->resolveStudentForBilling((int) $studentId, $enrollments, $attendances);
            if ($student === null) {
                continue;
            }

            $rows = [];
            $classroomIds = $this->classroomIdsForStudentBilling(
                (int) $studentId,
                $enrollments,
                $attendances,
                $reconciliations,
            )->sortBy(fn (int $classroomId) => $classrooms->firstWhere('id', $classroomId)?->name ?? '');

            foreach ($classroomIds as $classroomId) {
                $classroom = $classrooms->firstWhere('id', $classroomId);
                if ($classroom === null) {
                    continue;
                }

                $enrollment = $enrollments->first(
                    fn (Enrollment $e) => (int) $e->student_id === (int) $studentId
                        && (int) $e->classroom_id === (int) $classroomId
                );

                $studentAttendances = $attendances->filter(
                    fn (Attendance $a) => (int) $a->student_id === (int) $studentId
                        && $this->billingClassroomId($a) === (int) $classroomId
                );

                $reco = $reconciliations->get("{$studentId}|{$classroomId}");
                $isPaid = $reco !== null && $reco->status === 'paid';
                $schoolSegment = $student->school_segment ?? null;
                $prices = $classroom->course?->coursePrices ?? collect();
                $multiDuration = CourseTuition::hasMultipleDurations($prices);

                foreach ($this->attendanceGroupsByDuration($studentAttendances, $multiDuration) as $durationKey => $groupAttendances) {
                    $durationHours = $multiDuration && $durationKey !== '__legacy__'
                        ? (float) $durationKey
                        : null;

                    $dateEntries = $this->dateEntriesForStudent(
                        $groupAttendances,
                        (int) $classroomId,
                        (int) $studentId,
                        $extraSessionKeys,
                    );

                    $sessionCount = count($dateEntries);
                    if ($sessionCount === 0) {
                        continue;
                    }

                    $unitAmount = CourseTuition::sessionAmount(
                        $prices,
                        $schoolSegment,
                        $durationHours,
                        (int) ($enrollment?->tuition_amount ?? 0),
                        $enrollment?->tuitionRates,
                    );
                    $expected = $unitAmount * $sessionCount;
                    $payment = $this->paymentFromReconciliation($reco, $expected, $sessionCount, deferPaidAmount: $isPaid);

                    $rows[] = $this->rowPayload(
                        $this->durationRowLabel($classroom, $durationHours, $multiDuration),
                        $dateEntries,
                        $payment,
                        $expected,
                        $isPaid,
                        (int) $classroomId,
                        $unitAmount,
                        $durationHours,
                    );
                }
            }

            if ($rows === []) {
                continue;
            }

            $this->applyProportionalPaidAmounts($rows, $studentId, $reconciliations);

            $summary = $this->paymentSummaryForRows($rows);

            $sections[] = [
                'key' => 'student-'.$studentId,
                'student_id' => $studentId,
                'title' => $student->name,
                'subtitle' => count($rows).' 個班級',
                'classroom_name' => null,
                'rows' => $rows,
                'payment_status' => $summary['status'],
                'total_expected' => $summary['total_expected'],
                'paid_date_display' => $summary['paid_date'],
            ];
        }

        usort($sections, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $sections;
    }

    private function classroomRowLabel(Classroom $classroom): string
    {
        $categoryName = $classroom->course?->courseCategory?->name;
        $courseName = $classroom->course?->name ?? '課程';
        $coursePart = $categoryName ? "{$categoryName} / {$courseName}" : $courseName;
        $weekday = $this->primaryWeekdayLabel($classroom);
        $weekPart = $weekday !== '' ? '週'.$weekday : '';

        return implode(' · ', array_filter([$coursePart, $weekPart, $classroom->name]));
    }

    /**
     * @param  Collection<int, Attendance>  $studentAttendances
     * @param  array<string, true>  $extraSessionKeys
     * @return array<int, array{sort: string, label: string}>
     */
    private function dateEntriesForStudent(
        Collection $studentAttendances,
        int $classroomId,
        int $studentId,
        array $extraSessionKeys,
    ): array {
        $entries = [];
        foreach ($studentAttendances as $a) {
            $date = Carbon::parse($a->class_date)->startOfDay();
            $ymd = $date->toDateString();
            $isMakeup = $a->status === 'makeup' || str_starts_with((string) ($a->note ?? ''), '補課日期:');
            $isExtra = $a->status === 'extra'
                || isset($extraSessionKeys["{$classroomId}|{$ymd}|{$studentId}"])
                || isset($extraSessionKeys["{$classroomId}|{$ymd}|*"]);
            $suffix = $isMakeup ? '（補）' : ($isExtra ? '（加）' : '');

            $entries[] = [
                'sort' => $ymd,
                'label' => $date->format('n/j').$suffix,
            ];
        }

        return $this->uniqueDateEntries($entries);
    }

    /**
     * @param  array<int, array{sort: string, label: string}>  $entries
     * @return array<int, array{sort: string, label: string}>
     */
    private function uniqueDateEntries(array $entries): array
    {
        $bySort = [];
        foreach ($entries as $entry) {
            $bySort[$entry['sort']] = $entry;
        }
        ksort($bySort);

        return array_values($bySort);
    }

    /**
     * @param  array<int, array{sort: string, label: string}>  $dateEntries
     * @param  array{paid_amount: int|null, paid_date: string|null, payment_note: string|null}  $payment
     * @return array<string, mixed>
     */
    private function rowPayload(
        string $studentName,
        array $dateEntries,
        array $payment,
        int $expectedAmount = 0,
        bool $isPaid = false,
        int $classroomId = 0,
        int $unitAmount = 0,
        ?float $durationHours = null,
    ): array {
        $labels = array_column($dateEntries, 'label');

        $sortKeys = array_column($dateEntries, 'sort');

        return [
            'classroom_id' => $classroomId,
            'duration_hours' => $durationHours,
            'student_name' => $studentName,
            'unit_amount' => $unitAmount,
            'date_sort_keys' => $sortKeys,
            'date_labels' => $labels,
            'date_cells' => $labels,
            'session_count' => count($labels),
            'expected_amount' => $expectedAmount,
            'is_paid' => $isPaid,
            'paid_amount' => $payment['paid_amount'],
            'paid_date' => $payment['paid_date'],
            'payment_note' => $payment['payment_note'],
        ];
    }

    /**
     * 依各班列計算該學生整月的繳費狀態彙總。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{status: string, total_expected: int, paid_date: string|null}
     */
    private function paymentSummaryForRows(array $rows): array
    {
        $billable = array_filter($rows, fn (array $r) => (int) ($r['session_count'] ?? 0) > 0);
        $billableCount = count($billable);

        if ($billableCount === 0) {
            return ['status' => 'none', 'total_expected' => 0, 'paid_date' => null];
        }

        $paidCount = 0;
        $totalExpected = 0;
        $latestPaidDate = null;
        foreach ($billable as $r) {
            $totalExpected += (int) ($r['expected_amount'] ?? 0);
            if (($r['is_paid'] ?? false) === true) {
                $paidCount++;
                $pd = $r['paid_date'] ?? null;
                if ($pd !== null && ($latestPaidDate === null || strcmp($pd, $latestPaidDate) > 0)) {
                    $latestPaidDate = $pd;
                }
            }
        }

        $status = $paidCount === $billableCount
            ? 'paid'
            : ($paidCount === 0 ? 'unpaid' : 'partial');

        return ['status' => $status, 'total_expected' => $totalExpected, 'paid_date' => $latestPaidDate];
    }

    /**
     * 已繳費：顯示繳費當下的快照金額／日期；
     * 未繳費：顯示「即時應收」（依目前班級名單學費 × 堂數動態計算），方便修改學費後立即反映。
     *
     * @return array{paid_amount: int|null, paid_date: string|null, payment_note: string|null}
     */
    private function paymentFromReconciliation(
        ?Reconciliation $reco,
        int $liveExpected,
        int $sessionCount,
        bool $deferPaidAmount = false,
    ): array {
        if ($reco !== null && $reco->status === 'paid') {
            return [
                'paid_amount' => $deferPaidAmount ? null : (int) $reco->paid_amount,
                'paid_date' => $reco->paid_date?->toDateString(),
                'payment_note' => null,
            ];
        }

        if ($sessionCount <= 0) {
            return ['paid_amount' => null, 'paid_date' => null, 'payment_note' => null];
        }

        return [
            'paid_amount' => $liveExpected,
            'paid_date' => null,
            'payment_note' => '（未繳費）',
        ];
    }

    private function durationRowLabel(Classroom $classroom, ?float $durationHours, bool $showDuration = true): string
    {
        $base = $this->classroomRowLabel($classroom);
        if (! $showDuration || $durationHours === null) {
            return $base;
        }

        return $base.' - '.CourseTuition::durationLabel($durationHours);
    }

    /**
     * @param  Collection<int, Attendance>  $studentAttendances
     * @return Collection<string|float, Collection<int, Attendance>>
     */
    private function attendanceGroupsByDuration(Collection $studentAttendances, bool $splitByDuration = true): Collection
    {
        if (! $splitByDuration) {
            return collect(['__legacy__' => $studentAttendances]);
        }

        return $studentAttendances->groupBy(function (Attendance $a) {
            if ($a->duration_hours === null) {
                return '__legacy__';
            }

            return (string) CourseTuition::normalizeDuration($a->duration_hours);
        })->sortKeys();
    }

    /**
     * 同一班級拆成多列（依時數）時，將已繳金額依應收比例分配至各列。
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyProportionalPaidAmounts(array &$rows, int $studentId, Collection $reconciliations): void
    {
        /** @var array<int, array<int>> $indicesByClassroom */
        $indicesByClassroom = [];
        foreach ($rows as $index => $row) {
            $classroomId = (int) ($row['classroom_id'] ?? 0);
            $indicesByClassroom[$classroomId][] = (int) $index;
        }

        foreach ($indicesByClassroom as $classroomId => $indices) {
            $reco = $reconciliations->get("{$studentId}|{$classroomId}");
            if ($reco === null || $reco->status !== 'paid') {
                continue;
            }

            $totalExpected = 0;
            foreach ($indices as $index) {
                $totalExpected += (int) ($rows[$index]['expected_amount'] ?? 0);
            }

            if ($totalExpected <= 0) {
                foreach ($indices as $index) {
                    $rows[$index]['paid_amount'] = count($indices) === 1
                        ? (int) $reco->paid_amount
                        : 0;
                }

                continue;
            }

            $paidTotal = (int) $reco->paid_amount;
            $remaining = $paidTotal;
            $lastIndex = $indices[array_key_last($indices)];

            foreach ($indices as $index) {
                if ($index === $lastIndex) {
                    $rows[$index]['paid_amount'] = $remaining;

                    continue;
                }

                $share = (int) round($paidTotal * ((int) ($rows[$index]['expected_amount'] ?? 0)) / $totalExpected);
                $rows[$index]['paid_amount'] = $share;
                $remaining -= $share;
            }
        }
    }

    private function primaryWeekdayLabel(Classroom $classroom): string
    {
        if ($this->hasSchedulesTable && $classroom->relationLoaded('schedules') && $classroom->schedules->isNotEmpty()) {
            $wd = (int) $classroom->schedules->sortBy('weekday')->first()->weekday;

            return self::WEEKDAY_ZH[$wd] ?? '';
        }

        if ($classroom->weekday !== null) {
            return self::WEEKDAY_ZH[(int) $classroom->weekday] ?? '';
        }

        return '';
    }
}
