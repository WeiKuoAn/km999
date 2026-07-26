<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Reconciliation;
use App\Models\ReconciliationLog;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CourseTuition;
use App\Support\TuitionBagStatisticsBuilder;
use App\Support\TuitionBagUnpaidListBuilder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TuitionBagStatisticsController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'payment_status' => ['nullable', 'in:all,paid,unpaid'],
            'months_back' => ['nullable', 'string', 'in:month,3,6,all'],
        ]);

        $monthsBack = (string) ($validated['months_back'] ?? 'month');
        $studentName = trim((string) ($validated['student_name'] ?? ''));
        $courseId = isset($validated['course_id']) ? (int) $validated['course_id'] : null;

        $user = auth()->user();
        $teacherId = null;
        if ($user?->role !== User::ROLE_TEACHER && isset($validated['teacher_id'])) {
            $teacherId = (int) $validated['teacher_id'];
        }

        $role = $user?->role;
        $canManagePayment = in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_TEACHER], true);
        $canFilterByTeacher = $role !== User::ROLE_TEACHER;

        if ($monthsBack !== 'month') {
            $scanScope = TuitionBagUnpaidListBuilder::normalizeScanScope($monthsBack);
            $built = (new TuitionBagUnpaidListBuilder)->build(
                $user,
                $studentName,
                $courseId,
                $teacherId,
                $scanScope,
            );

            return Inertia::render('TuitionBagStatistics/Index', [
                'viewMode' => 'multi_unpaid',
                'sections' => [],
                'students' => $built['students'],
                'rangeLabel' => $built['range_label'],
                'groupBy' => 'student',
                'courseOptions' => $this->courseOptions(),
                'teacherOptions' => $canFilterByTeacher
                    ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
                    : [],
                'canFilterByTeacher' => $canFilterByTeacher,
                'canManagePayment' => $canManagePayment,
                'todayDate' => $today->toDateString(),
                'filters' => [
                    'months_back' => $built['scan_scope'],
                    'year' => (string) $today->year,
                    'month' => (string) $today->month,
                    'student_name' => $studentName,
                    'course_id' => $courseId === null ? '' : (string) $courseId,
                    'teacher_id' => $teacherId === null ? '' : (string) $teacherId,
                    'payment_status' => 'unpaid',
                ],
            ]);
        }

        // 預設顯示上一個月（多數對帳發生在月初，先看上個月較合理）。
        $defaultMonth = $today->copy()->subMonthNoOverflow();
        $year = (int) ($validated['year'] ?? $defaultMonth->year);
        $month = (int) ($validated['month'] ?? $defaultMonth->month);

        $built = (new TuitionBagStatisticsBuilder)->build(
            $year,
            $month,
            $user,
            $studentName,
            $courseId,
            $teacherId,
        );

        $paymentStatus = (string) ($validated['payment_status'] ?? 'all');
        $sections = $this->filterSectionsByPaymentStatus($built['sections'], $paymentStatus);

        return Inertia::render('TuitionBagStatistics/Index', [
            'viewMode' => 'single_month',
            'sections' => $sections,
            'students' => [],
            'rangeLabel' => null,
            'groupBy' => $built['group_by'] ?? 'student',
            'courseOptions' => $built['course_options'],
            'teacherOptions' => $canFilterByTeacher
                ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
                : [],
            'canFilterByTeacher' => $canFilterByTeacher,
            'canManagePayment' => $canManagePayment,
            'todayDate' => $today->toDateString(),
            'filters' => [
                'months_back' => 'month',
                'year' => (string) $year,
                'month' => (string) $month,
                'student_name' => $studentName,
                'course_id' => $courseId === null ? '' : (string) $courseId,
                'teacher_id' => $teacherId === null ? '' : (string) $teacherId,
                'payment_status' => $paymentStatus,
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function filterSectionsByPaymentStatus(array $sections, string $paymentStatus): array
    {
        if ($paymentStatus === 'paid') {
            return array_values(array_filter(
                $sections,
                fn (array $section) => ($section['payment_status'] ?? '') === 'paid',
            ));
        }

        if ($paymentStatus === 'unpaid') {
            return array_values(array_filter(
                $sections,
                fn (array $section) => in_array($section['payment_status'] ?? '', ['unpaid', 'partial'], true),
            ));
        }

        return $sections;
    }

    public function unpaidList(Request $request): RedirectResponse
    {
        return redirect()->route('tuition-bag-statistics.index', array_merge(
            $request->only(['student_name', 'course_id', 'teacher_id']),
            [
                'months_back' => TuitionBagUnpaidListBuilder::normalizeScanScope(
                    $request->query('months_back') !== null ? (string) $request->query('months_back') : null,
                ),
            ],
        ));
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function courseOptions(): array
    {
        return Course::query()
            ->with('courseCategory')
            ->orderBy('name')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'name' => ($c->courseCategory?->name ? $c->courseCategory->name.' / ' : '').$c->name,
            ])
            ->values()
            ->all();
    }

    public function reconciliationRecords(Request $request): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'action' => ['nullable', 'in:confirm,update,cancel'],
        ]);

        $user = auth()->user();
        $canFilterByTeacher = $user?->role !== User::ROLE_TEACHER;
        $defaultMonth = Carbon::today()->subMonthNoOverflow();

        if (! $request->has('year') && ! $request->has('month')) {
            $year = $defaultMonth->year;
            $month = $defaultMonth->month;
        } else {
            $year = $request->filled('year') ? (int) $validated['year'] : null;
            $month = $request->filled('month') ? (int) $validated['month'] : null;
        }

        $studentName = trim((string) ($validated['student_name'] ?? ''));
        $action = trim((string) ($validated['action'] ?? ''));

        $query = ReconciliationLog::query()
            ->with([
                'student:id,name',
                'classroom.course.courseCategory',
                'classroom.teacher:id,name',
                'performedByUser:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('classroom', fn ($q) => $q->where('teacher_id', $teacherId));
            }
        } elseif (isset($validated['teacher_id'])) {
            $query->whereHas('classroom', fn ($q) => $q->where('teacher_id', (int) $validated['teacher_id']));
        }

        if ($year !== null) {
            $query->where('billing_year', $year);
        }
        if ($month !== null) {
            $query->where('billing_month', $month);
        }
        if ($studentName !== '') {
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', '%'.$studentName.'%'));
        }
        if ($action !== '') {
            $query->where('action', $action);
        }

        $filterTeacherId = null;
        if ($canFilterByTeacher && isset($validated['teacher_id'])) {
            $filterTeacherId = (int) $validated['teacher_id'];
        }

        $rows = $query->paginate(50)->withQueryString()->through(fn (ReconciliationLog $log): array => [
            'id' => $log->id,
            'billing_year' => $log->billing_year,
            'billing_month' => $log->billing_month,
            'student_name' => $log->student?->name ?? '—',
            'classroom_name' => $log->classroom?->name ?? '—',
            'course_name' => $log->classroom?->course?->name ?? '—',
            'course_category_name' => $log->classroom?->course?->courseCategory?->name ?? '—',
            'teacher_name' => $log->classroom?->teacher?->name,
            'expected_amount' => (int) $log->expected_amount,
            'paid_amount' => (int) $log->paid_amount,
            'paid_date' => $log->paid_date?->toDateString(),
            'status' => $log->status,
            'action' => $log->action,
            'performed_by_name' => $log->performedByUser?->name ?? '—',
            'note' => $log->note,
            'created_at' => $log->created_at?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('TuitionBagStatistics/ReconciliationRecords', [
            'rows' => $rows,
            'teacherOptions' => $canFilterByTeacher
                ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
                : [],
            'canFilterByTeacher' => $canFilterByTeacher,
            'filters' => [
                'year' => $year === null ? '' : (string) $year,
                'month' => $month === null ? '' : (string) $month,
                'student_name' => $studentName,
                'teacher_id' => $filterTeacherId === null ? '' : (string) $filterTeacherId,
                'action' => $action,
            ],
        ]);
    }

    /**
     * 學生層級「一筆繳清」：將該學生當月所有班級標記為已繳費。
     */
    public function confirmPayment(Request $request): RedirectResponse
    {
        $this->authorizePaymentManagement();

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'paid_date' => ['nullable', 'date'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer'],
        ]);

        $studentId = (int) $validated['student_id'];
        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $paidDate = isset($validated['paid_date'])
            ? Carbon::parse($validated['paid_date'])->toDateString()
            : Carbon::today()->toDateString();
        $settledByUserId = auth()->id();

        $visibleIds = $this->visibleClassroomIds(auth()->user());
        $classroomIds = array_values(array_intersect(
            array_map('intval', $validated['classroom_ids']),
            $visibleIds,
        ));
        if ($classroomIds === []) {
            return back()->withErrors(['payment' => '沒有可繳費的班級。']);
        }

        $student = Student::query()->findOrFail($studentId, ['id', 'school_segment']);

        $enrollments = Enrollment::query()
            ->where('student_id', $studentId)
            ->whereIn('classroom_id', $classroomIds)
            ->with('tuitionRates')
            ->get(['id', 'classroom_id', 'tuition_amount'])
            ->keyBy('classroom_id');

        $classrooms = Classroom::query()
            ->whereIn('id', $classroomIds)
            ->with('course.coursePrices')
            ->get(['id', 'course_id'])
            ->keyBy('id');

        $attendances = Attendance::query()
            ->where('student_id', $studentId)
            ->where(function ($q) use ($classroomIds) {
                $q->whereIn('classroom_id', $classroomIds)
                    ->orWhereIn('makeup_for_classroom_id', $classroomIds);
            })
            ->whereIn('status', ['present', 'late', 'makeup', 'extra'])
            ->whereYear('class_date', $year)
            ->whereMonth('class_date', $month)
            ->get(['id', 'classroom_id', 'makeup_for_classroom_id', 'status', 'duration_hours']);

        if ($attendances->isEmpty()) {
            return back()->withErrors(['payment' => '此月份沒有可繳費的出勤資料。']);
        }

        $amountsByClassroom = [];
        foreach ($attendances as $attendance) {
            $billingClassroomId = in_array($attendance->status, ['makeup', 'extra'], true)
                && $attendance->makeup_for_classroom_id !== null
                ? (int) $attendance->makeup_for_classroom_id
                : (int) $attendance->classroom_id;

            if (! in_array($billingClassroomId, $classroomIds, true)) {
                continue;
            }

            $enrollment = $enrollments->get($billingClassroomId);
            $prices = $classrooms->get($billingClassroomId)?->course?->coursePrices ?? collect();
            $multiDuration = CourseTuition::hasMultipleDurations($prices);
            $durationForBilling = $multiDuration && $attendance->duration_hours !== null
                ? (float) $attendance->duration_hours
                : null;
            $amount = CourseTuition::sessionAmount(
                $prices,
                $student->school_segment,
                $durationForBilling,
                $enrollment !== null ? (int) $enrollment->tuition_amount : 0,
                $enrollment?->tuitionRates,
            );

            $amountsByClassroom[$billingClassroomId] = ($amountsByClassroom[$billingClassroomId] ?? 0) + $amount;
        }

        if ($amountsByClassroom === []) {
            return back()->withErrors(['payment' => '此月份沒有可繳費的出勤資料。']);
        }

        DB::transaction(function () use ($amountsByClassroom, $studentId, $year, $month, $paidDate, $settledByUserId): void {
            foreach ($amountsByClassroom as $classroomId => $sum) {
                $sum = (int) $sum;
                $reco = Reconciliation::query()->updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'classroom_id' => (int) $classroomId,
                        'billing_year' => $year,
                        'billing_month' => $month,
                    ],
                    [
                        'expected_amount' => $sum,
                        'paid_amount' => $sum,
                        'paid_date' => $paidDate,
                        'status' => 'paid',
                        'settled_by_user_id' => $settledByUserId,
                        'note' => '學費袋統計繳費確認',
                    ]
                );

                $this->logReconciliationAction(
                    $reco,
                    ReconciliationLog::ACTION_CONFIRM,
                    '學費袋統計繳費確認',
                );
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '繳費確認完成。',
        ]);

        return back();
    }

    /**
     * 調整單一班級已確認的繳費金額／日期。
     */
    public function updatePayment(Request $request): RedirectResponse
    {
        $this->authorizePaymentManagement();

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'classroom_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'paid_amount' => ['required', 'integer', 'min:0'],
            'paid_date' => ['required', 'date'],
        ]);

        $visibleIds = $this->visibleClassroomIds(auth()->user());
        if (! in_array((int) $validated['classroom_id'], $visibleIds, true)) {
            return back()->withErrors(['payment' => '沒有權限調整此班級。']);
        }

        $reco = Reconciliation::query()
            ->where('student_id', (int) $validated['student_id'])
            ->where('classroom_id', (int) $validated['classroom_id'])
            ->where('billing_year', (int) $validated['year'])
            ->where('billing_month', (int) $validated['month'])
            ->first();

        if ($reco === null) {
            return back()->withErrors(['payment' => '找不到繳費紀錄。']);
        }

        $reco->update([
            'paid_amount' => (int) $validated['paid_amount'],
            'paid_date' => Carbon::parse($validated['paid_date'])->toDateString(),
            'status' => 'paid',
            'settled_by_user_id' => auth()->id(),
        ]);

        $this->logReconciliationAction(
            $reco->fresh(),
            ReconciliationLog::ACTION_UPDATE,
            '調整繳費金額／日期',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '已更新繳費資料。',
        ]);

        return back();
    }

    /**
     * 取消該學生當月的繳費紀錄（還原為未繳費）。
     */
    public function cancelPayment(Request $request): RedirectResponse
    {
        $this->authorizePaymentManagement();

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer'],
        ]);

        $visibleIds = $this->visibleClassroomIds(auth()->user());
        $classroomIds = array_values(array_intersect(
            array_map('intval', $validated['classroom_ids']),
            $visibleIds,
        ));
        if ($classroomIds === []) {
            return back();
        }

        $records = Reconciliation::query()
            ->where('student_id', (int) $validated['student_id'])
            ->whereIn('classroom_id', $classroomIds)
            ->where('billing_year', (int) $validated['year'])
            ->where('billing_month', (int) $validated['month'])
            ->get();

        DB::transaction(function () use ($records): void {
            foreach ($records as $reco) {
                $reco->update([
                    'status' => 'cancelled',
                    'paid_amount' => 0,
                    'paid_date' => null,
                    'settled_by_user_id' => auth()->id(),
                    'note' => '已取消繳費',
                ]);

                $this->logReconciliationAction(
                    $reco->fresh(),
                    ReconciliationLog::ACTION_CANCEL,
                    '取消繳費',
                );
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '已取消繳費。',
        ]);

        return back();
    }

    private function authorizePaymentManagement(): void
    {
        $role = auth()->user()?->role;
        if (! in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_TEACHER], true)) {
            abort(403, '你沒有權限操作對帳。');
        }
    }

    private function logReconciliationAction(
        Reconciliation $reco,
        string $action,
        ?string $note = null,
    ): void {
        ReconciliationLog::query()->create([
            'student_id' => $reco->student_id,
            'classroom_id' => $reco->classroom_id,
            'billing_year' => $reco->billing_year,
            'billing_month' => $reco->billing_month,
            'expected_amount' => (int) $reco->expected_amount,
            'paid_amount' => (int) $reco->paid_amount,
            'paid_date' => $reco->paid_date,
            'status' => (string) $reco->status,
            'action' => $action,
            'performed_by_user_id' => auth()->id(),
            'note' => $note ?? $reco->note,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function visibleClassroomIds(?User $user): array
    {
        $query = Classroom::query()->where('status', 'active');

        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                return [];
            }
            $query->where('teacher_id', $teacherId);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
