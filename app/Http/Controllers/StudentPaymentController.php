<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\BillingRenewal;
use App\Support\EnrollmentPricing;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,unpaid,paid,cancelled'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');

        $query = Reconciliation::query()
            ->join('students', 'students.id', '=', 'reconciliations.student_id')
            ->leftJoin('grade_levels', 'grade_levels.id', '=', 'students.grade_level_id')
            ->leftJoin('users as settled_users', 'settled_users.id', '=', 'reconciliations.settled_by_user_id')
            ->selectRaw('
                MAX(reconciliations.id) as id,
                reconciliations.student_id,
                students.student_code,
                students.name as student_name,
                grade_levels.name as grade_name,
                reconciliations.billing_year,
                reconciliations.billing_month,
                SUM(reconciliations.expected_amount) as expected_total,
                SUM(reconciliations.paid_amount) as paid_total,
                COUNT(*) as course_count,
                MAX(reconciliations.paid_date) as paid_date,
                MAX(settled_users.name) as settled_by_name,
                CASE
                    WHEN SUM(CASE WHEN reconciliations.status = "paid" THEN 1 ELSE 0 END) = COUNT(*) THEN "paid"
                    WHEN SUM(CASE WHEN reconciliations.status = "cancelled" THEN 1 ELSE 0 END) = COUNT(*) THEN "cancelled"
                    WHEN SUM(CASE WHEN reconciliations.status = "unpaid" THEN 1 ELSE 0 END) = COUNT(*) THEN "unpaid"
                    ELSE "partial"
                END as group_status
            ')
            ->groupBy([
                'reconciliations.student_id',
                'students.student_code',
                'students.name',
                'grade_levels.name',
                'reconciliations.billing_year',
                'reconciliations.billing_month',
            ]);

        $this->restrictReconciliationsForTeacher($query);

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('students.name', 'like', '%'.$q.'%')
                    ->orWhere('students.student_code', 'like', '%'.$q.'%');
            });
        }

        if ($status === 'paid') {
            $query->havingRaw(
                'SUM(CASE WHEN reconciliations.status = "paid" THEN 1 ELSE 0 END) = COUNT(*)'
            );
        } elseif ($status === 'cancelled') {
            $query->havingRaw(
                'SUM(CASE WHEN reconciliations.status = "cancelled" THEN 1 ELSE 0 END) = COUNT(*)'
            );
        } elseif ($status === 'unpaid') {
            $query->havingRaw(
                'SUM(CASE WHEN reconciliations.status = "paid" THEN 1 ELSE 0 END) < COUNT(*)
                AND SUM(CASE WHEN reconciliations.status = "cancelled" THEN 1 ELSE 0 END) < COUNT(*)'
            );
        }

        $summaryQuery = Reconciliation::query();
        $this->restrictReconciliationsForTeacher($summaryQuery);
        if ($q !== '') {
            $summaryQuery->whereHas('student', function ($builder) use ($q): void {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('student_code', 'like', '%'.$q.'%');
            });
        }

        $unpaidTotal = (int) (clone $summaryQuery)->where('status', 'unpaid')->sum('expected_amount');
        $paidTotal = (int) (clone $summaryQuery)->where('status', 'paid')->sum('paid_amount');

        $rows = $query
            ->orderByDesc('reconciliations.billing_year')
            ->orderByDesc('reconciliations.billing_month')
            ->orderByRaw('MAX(reconciliations.id) DESC')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Reconciliation $row): array => [
                'id' => (int) $row->id,
                'student_id' => (int) $row->student_id,
                'student_code' => $row->student_code,
                'student_name' => $row->student_name ?? '—',
                'grade_name' => $row->grade_name,
                'billing_year' => (int) $row->billing_year,
                'billing_month' => (int) $row->billing_month,
                'expected_total' => (int) $row->expected_total,
                'paid_total' => (int) $row->paid_total,
                'course_count' => (int) $row->course_count,
                'paid_date' => $row->paid_date?->toDateString(),
                'status' => $row->group_status,
                'settled_by_name' => $row->settled_by_name ?? '—',
            ]);

        return Inertia::render('StudentPayments/Index', [
            'rows' => $rows,
            'summary' => [
                'unpaid_total' => $unpaidTotal,
                'paid_total' => $paidTotal,
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
        ]);
    }

    /** 新增收款／報名計價 */
    public function create(Request $request): Response
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $studentPayload = null;
        $subjects = [];
        $warnings = [];
        $hasPriorPayments = false;
        $suggestedStartDate = null;

        if (! empty($validated['student_id'])) {
            $student = Student::query()->findOrFail((int) $validated['student_id']);
            $this->authorizeTeacherCanViewStudent($student);
            $student->load(['gradeLevel:id,name', 'academicYear:id,year_code,name']);
            $subjects = EnrollmentPricing::subjectsForStudent($student);
            $warnings = $this->warnings($student, $subjects);
            $studentPayload = [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'grade_name' => $student->gradeLevel?->name,
                'academic_year_name' => $student->academicYear?->name,
            ];
            $hasPriorPayments = BillingRenewal::hasPriorPayments($student);
            $suggestedStartDate = BillingRenewal::suggestedStartDate($student);
        }

        $from = Carbon::today()->subMonths(1)->startOfDay();
        $to = Carbon::today()->addYear()->endOfDay();
        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get(['date', 'name'])
            ->map(fn (Holiday $holiday): array => [
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
            ])
            ->values()
            ->all();

        return Inertia::render('StudentPayments/Create', [
            'student' => $studentPayload,
            'subjects' => $subjects,
            'warnings' => $warnings,
            'holidays' => $holidays,
            'has_prior_payments' => $hasPriorPayments,
            'suggested_start_date' => $suggestedStartDate,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $q = trim((string) ($validated['q'] ?? ''));
        if ($q === '') {
            return response()->json(['students' => []]);
        }

        $query = Student::query()
            ->with(['gradeLevel:id,name'])
            ->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('student_code', 'like', '%'.$q.'%');
            })
            ->orderBy('name')
            ->limit(20);

        $user = auth()->user();
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                return response()->json(['students' => []]);
            }
            $query->whereHas('enrollments.classroom', fn ($builder) => $builder->where('teacher_id', $teacherId));
        }

        $students = $query->get(['id', 'student_code', 'name', 'grade_level_id', 'status'])
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'grade_name' => $student->gradeLevel?->name,
                'status' => $student->status,
            ])
            ->values()
            ->all();

        return response()->json(['students' => $students]);
    }

    /** 收款明細 */
    public function show(Request $request, Student $student): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100', 'required_with:month'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12', 'required_with:year'],
        ]);

        $this->authorizeTeacherCanViewStudent($student);
        $student->load(['gradeLevel:id,name', 'academicYear:id,year_code,name']);

        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $month = isset($validated['month']) ? (int) $validated['month'] : null;

        $baseQuery = Reconciliation::query()
            ->where('student_id', $student->id);

        if ($year !== null && $month !== null) {
            $baseQuery
                ->where('billing_year', $year)
                ->where('billing_month', $month);
        }

        $rows = (clone $baseQuery)
            ->with([
                'classroom.course.courseCategory',
                'course.courseCategory',
                'settledByUser:id,name',
            ])
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (Reconciliation $row): array => [
                'id' => $row->id,
                'billing_year' => $row->billing_year,
                'billing_month' => $row->billing_month,
                'classroom_name' => $row->classroom?->name ?? '—',
                'course_name' => $row->course?->name ?? $row->classroom?->course?->name ?? '—',
                'course_category_name' => $row->course?->courseCategory?->name
                    ?? $row->classroom?->course?->courseCategory?->name
                    ?? '—',
                'expected_amount' => (int) $row->expected_amount,
                'paid_amount' => (int) $row->paid_amount,
                'paid_date' => $row->paid_date?->toDateString(),
                'status' => $row->status,
                'settled_by_name' => $row->settledByUser?->name ?? '—',
                'note' => $row->note,
                'pay_cycle' => $row->pay_cycle,
            ]);

        $unpaidTotal = (int) (clone $baseQuery)
            ->where('status', 'unpaid')
            ->sum('expected_amount');
        $paidTotal = (int) (clone $baseQuery)
            ->where('status', 'paid')
            ->sum('paid_amount');

        $period = null;
        if ($year !== null && $month !== null) {
            $periodRows = (clone $baseQuery)->get([
                'expected_amount',
                'paid_amount',
                'paid_date',
                'status',
                'settled_by_user_id',
            ]);
            $latestPaid = (clone $baseQuery)
                ->whereNotNull('paid_date')
                ->with('settledByUser:id,name')
                ->orderByDesc('paid_date')
                ->orderByDesc('id')
                ->first();

            $period = [
                'billing_year' => $year,
                'billing_month' => $month,
                'expected_total' => (int) $periodRows->sum('expected_amount'),
                'paid_total' => (int) $periodRows->sum('paid_amount'),
                'course_count' => $periodRows->count(),
                'status' => $this->groupStatus($periodRows->pluck('status')->all()),
                'paid_date' => $latestPaid?->paid_date?->toDateString(),
                'settled_by_name' => $latestPaid?->settledByUser?->name ?? '—',
            ];
        }

        return Inertia::render('StudentPayments/Detail', [
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'grade_name' => $student->gradeLevel?->name,
                'academic_year_name' => $student->academicYear?->name,
            ],
            'rows' => $rows,
            'summary' => [
                'unpaid_total' => $unpaidTotal,
                'paid_total' => $paidTotal,
            ],
            'period' => $period,
            'renewal' => BillingRenewal::renewalSummary($student),
        ]);
    }

    /** 報名計價（導回首頁並帶入學生） */
    public function quote(Student $student): RedirectResponse
    {
        $this->authorizeTeacherCanViewStudent($student);

        return redirect()->route('student-payments.create', ['student_id' => $student->id]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeTeacherCanViewStudent($student);

        $validated = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'pay_cycle' => ['required', 'in:monthly,quarterly,annual'],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.date' => ['required', 'date'],
            'sessions.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'allowance' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
        ]);

        $suggested = BillingRenewal::suggestedStartDate($student);
        $startDate = $validated['start_date'] ?? null;
        if ($suggested !== null) {
            if ($startDate === null || Carbon::parse($startDate)->lt(Carbon::parse($suggested))) {
                $startDate = $suggested;
            }
        }

        $allowance = (int) ($validated['allowance'] ?? 0);
        $quote = EnrollmentPricing::quote(
            $student,
            $validated['course_ids'],
            $validated['pay_cycle'],
            $validated['sessions'],
            $allowance,
            $startDate,
        );

        if ($quote['lines'] === []) {
            return back()->withErrors(['course_ids' => '所選課目沒有適用的收費標準，請先在收費標準中勾選課目。']);
        }
        if (count($quote['lines']) !== count(array_unique(array_map('intval', $validated['course_ids'])))) {
            return back()->withErrors(['course_ids' => '部分所選課目沒有適用的收費標準，請先完成收費標準設定。']);
        }

        $months = $quote['months'] ?? [];
        if ($months === []) {
            return back()->withErrors(['sessions' => '請至少選擇一個上課日。']);
        }

        BillingRenewal::persistQuote($student, $validated['pay_cycle'], $quote, $allowance);

        return to_route('student-payments.show', $student)
            ->with('success', '已產生帳期，可至收款明細查看。');
    }

    /** 一鍵依最近一次科目＋繳別產生下一期帳 */
    public function renewNext(Student $student): RedirectResponse
    {
        $this->authorizeTeacherCanViewStudent($student);

        $snapshot = BillingRenewal::lastBillingSnapshot($student);
        if ($snapshot === null) {
            return back()->withErrors(['renewal' => '尚無可用的收款紀錄，請先用「新增收款」產生第一期帳。']);
        }

        $startDate = BillingRenewal::nextStartDate($snapshot['end_year'], $snapshot['end_month']);
        $sessions = BillingRenewal::defaultSessions(
            $student,
            $snapshot['course_ids'],
            $startDate,
            $snapshot['pay_cycle'],
        );

        if ($sessions === []) {
            return back()->withErrors(['renewal' => '無法依科目上課日預選堂次，請確認課程已設定上課時段後再試，或改用「新增收款」。']);
        }

        $quote = EnrollmentPricing::quote(
            $student,
            $snapshot['course_ids'],
            $snapshot['pay_cycle'],
            $sessions,
            0,
            $startDate,
        );

        if ($quote['lines'] === []) {
            return back()->withErrors(['renewal' => '所選課目沒有適用的收費標準，請先完成收費標準設定。']);
        }

        $months = $quote['months'] ?? [];
        if ($months === []) {
            return back()->withErrors(['renewal' => '無法推導下一期帳期月份。']);
        }

        if (BillingRenewal::hasPaidConflicts($student, $snapshot['course_ids'], $months)) {
            return back()->withErrors(['renewal' => '下一期帳期中已有已繳紀錄，請勿重複產生。']);
        }

        BillingRenewal::persistQuote($student, $snapshot['pay_cycle'], $quote, 0);

        $label = BillingRenewal::renewButtonLabel($snapshot['pay_cycle']);

        return to_route('student-payments.show', $student)
            ->with('success', "已{$label}（自 {$startDate} 起算）。");
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return list<string>
     */
    private function warnings(Student $student, array $subjects): array
    {
        $warnings = [];
        if ($student->grade_level_id === null) {
            $warnings[] = '此學生尚未設定年級，無法對應收費標準。';
        }
        if ($subjects === []) {
            $warnings[] = '沒有適用此學生年級的可報名課目。';
        }
        $missingPlan = collect($subjects)->contains(fn (array $s): bool => empty($s['fee_plan_id']));
        if ($missingPlan) {
            $warnings[] = '部分課目尚未綁定此學年、年級適用的收費標準，價格顯示為 0。';
        }
        $missingWeekdays = collect($subjects)->contains(
            fn (array $s): bool => ($s['material_unit'] ?? '') === 'class_day'
                && (int) ($s['material'] ?? 0) > 0
                && ($s['weekdays'] ?? []) === []
        );
        if ($missingWeekdays) {
            $warnings[] = '部分科目採「每日耗材」計費，但課程尚未設定上課日，耗材會算成 0。請至課程管理勾選上課日。';
        }

        return $warnings;
    }

    private function authorizeTeacherCanViewStudent(Student $student): void
    {
        $user = auth()->user();
        if ($user?->role !== User::ROLE_TEACHER) {
            return;
        }

        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            abort(403);
        }

        $ok = $student->enrollments()
            ->whereHas('classroom', fn ($q) => $q->where('teacher_id', $teacherId))
            ->exists();

        if (! $ok) {
            abort(403);
        }
    }

    private function restrictReconciliationsForTeacher($query): void
    {
        $user = auth()->user();
        if ($user?->role !== User::ROLE_TEACHER) {
            return;
        }

        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('student.enrollments.classroom', fn ($builder) => $builder->where('teacher_id', $teacherId));
    }

    /**
     * @param  list<string>  $statuses
     */
    private function groupStatus(array $statuses): string
    {
        if ($statuses !== [] && collect($statuses)->every(fn (string $status): bool => $status === 'paid')) {
            return 'paid';
        }
        if ($statuses !== [] && collect($statuses)->every(fn (string $status): bool => $status === 'cancelled')) {
            return 'cancelled';
        }
        if ($statuses !== [] && collect($statuses)->every(fn (string $status): bool => $status === 'unpaid')) {
            return 'unpaid';
        }

        return 'partial';
    }
}
