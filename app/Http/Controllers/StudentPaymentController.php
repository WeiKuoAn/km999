<?php

namespace App\Http\Controllers;

use App\Models\FeeDiscount;
use App\Models\GradeLevel;
use App\Models\Holiday;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\BillingRenewal;
use App\Support\EnrollmentPricing;
use App\Support\Grade9AnnualPackage;
use App\Support\MaterialHalfYear;
use App\Support\PaymentRosterBuilder;
use App\Support\PromotionCourses;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $batchDateSql = 'COALESCE(DATE(reconciliations.paid_date), DATE(reconciliations.created_at))';
        $payCycleSql = "COALESCE(reconciliations.pay_cycle, 'quarterly')";
        $batchKeySql = "COALESCE(reconciliations.receipt_no, CONCAT('L:', reconciliations.student_id, ':', {$batchDateSql}, ':', {$payCycleSql}))";

        $query = Reconciliation::query()
            ->join('students', 'students.id', '=', 'reconciliations.student_id')
            ->leftJoin('grade_levels', 'grade_levels.id', '=', 'students.grade_level_id')
            ->leftJoin('users as settled_users', 'settled_users.id', '=', 'reconciliations.settled_by_user_id')
            ->selectRaw("
                MAX(reconciliations.id) as id,
                reconciliations.student_id,
                students.student_code,
                students.name as student_name,
                grade_levels.name as grade_name,
                {$payCycleSql} as pay_cycle,
                {$batchDateSql} as batch_date,
                MAX(reconciliations.receipt_no) as receipt_no,
                MIN(reconciliations.billing_year * 12 + reconciliations.billing_month) as start_key,
                MAX(reconciliations.billing_year * 12 + reconciliations.billing_month) as end_key,
                SUM(reconciliations.expected_amount) as expected_total,
                SUM(reconciliations.paid_amount) as paid_total,
                COUNT(DISTINCT reconciliations.course_id) as course_count,
                MAX(reconciliations.paid_date) as paid_date,
                MAX(settled_users.name) as settled_by_name,
                CASE
                    WHEN SUM(CASE WHEN reconciliations.status = 'paid' THEN 1 ELSE 0 END) = COUNT(*) THEN 'paid'
                    WHEN SUM(CASE WHEN reconciliations.status = 'cancelled' THEN 1 ELSE 0 END) = COUNT(*) THEN 'cancelled'
                    WHEN SUM(CASE WHEN reconciliations.status = 'unpaid' THEN 1 ELSE 0 END) = COUNT(*) THEN 'unpaid'
                    ELSE 'partial'
                END as group_status
            ")
            ->groupBy([
                'reconciliations.student_id',
                'students.student_code',
                'students.name',
                'grade_levels.name',
                DB::raw($payCycleSql),
                DB::raw($batchDateSql),
                DB::raw($batchKeySql),
            ]);

        $this->restrictReconciliationsForTeacher($query);

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('students.name', 'like', '%'.$q.'%')
                    ->orWhere('students.student_code', 'like', '%'.$q.'%')
                    ->orWhere('reconciliations.receipt_no', 'like', '%'.$q.'%');
            });
        }

        if ($status === 'paid') {
            $query->havingRaw(
                "SUM(CASE WHEN reconciliations.status = 'paid' THEN 1 ELSE 0 END) = COUNT(*)"
            );
        } elseif ($status === 'cancelled') {
            $query->havingRaw(
                "SUM(CASE WHEN reconciliations.status = 'cancelled' THEN 1 ELSE 0 END) = COUNT(*)"
            );
        } elseif ($status === 'unpaid') {
            $query->havingRaw(
                "SUM(CASE WHEN reconciliations.status = 'paid' THEN 1 ELSE 0 END) < COUNT(*)
                AND SUM(CASE WHEN reconciliations.status = 'cancelled' THEN 1 ELSE 0 END) < COUNT(*)"
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
            ->orderByDesc('end_key')
            ->orderByRaw('MAX(reconciliations.id) DESC')
            ->paginate(50)
            ->withQueryString()
            ->through(function (Reconciliation $row): array {
                $startKey = (int) $row->start_key;
                $endKey = (int) $row->end_key;
                $startYear = intdiv($startKey - 1, 12);
                $startMonth = (($startKey - 1) % 12) + 1;
                $endYear = intdiv($endKey - 1, 12);
                $endMonth = (($endKey - 1) % 12) + 1;

                return [
                    'id' => (int) $row->id,
                    'student_id' => (int) $row->student_id,
                    'student_code' => $row->student_code,
                    'student_name' => $row->student_name ?? '—',
                    'grade_name' => $row->grade_name,
                    'start_year' => $startYear,
                    'start_month' => $startMonth,
                    'end_year' => $endYear,
                    'end_month' => $endMonth,
                    'period_label' => $startKey === $endKey
                        ? sprintf('%d/%d', $startYear, $startMonth)
                        : sprintf('%d/%d — %d/%d', $startYear, $startMonth, $endYear, $endMonth),
                    'expected_total' => (int) $row->expected_total,
                    'paid_total' => (int) $row->paid_total,
                    'course_count' => (int) $row->course_count,
                    'paid_date' => $row->paid_date?->toDateString(),
                    'receipt_no' => $row->receipt_no,
                    'status' => $row->group_status,
                    'settled_by_name' => $row->settled_by_name ?? '—',
                    'pay_cycle' => $row->pay_cycle,
                ];
            });

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

    /**
     * 繳費名單：已繳過費、但下一期尚未繳的學生（例：已繳 7–9 → 列出 10–12）。
     */
    public function roster(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $year = isset($validated['year']) ? (int) $validated['year'] : null;

        $allRows = PaymentRosterBuilder::build($q === '' ? null : $q, $year);
        $allRows = $this->filterRosterRowsForTeacher($allRows);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $slice = array_slice($allRows, ($page - 1) * $perPage, $perPage);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            count($allRows),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('StudentPayments/Roster', [
            'rows' => $paginator,
            'total_count' => count($allRows),
            'filters' => [
                'q' => $q,
                'year' => $year === null ? '' : (string) $year,
            ],
        ]);
    }

    /** 繳費名單紙本 PDF（瀏覽器列印／另存 PDF） */
    public function rosterPdf(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $rows = PaymentRosterBuilder::build($q === '' ? null : $q, $year);
        $rows = $this->filterRosterRowsForTeacher($rows);

        return response()->view('payment-lists.print', [
            'gradeSheets' => $this->groupRosterRowsByGrade($rows),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'yearLabel' => $year === null ? '全部' : (string) $year,
            'q' => $q,
        ]);
    }

    /**
     * 依年級分組繳費名單（列印每年級一張；含尚無待繳的年級空表）。
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{grade_name:string, rows:list<array<string, mixed>>}>
     */
    private function groupRosterRowsByGrade(array $rows): array
    {
        $gradeNames = GradeLevel::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();

        $grouped = [];
        foreach ($gradeNames as $name) {
            $grouped[$name] = [];
        }

        $ungraded = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['grade_name'] ?? ''));
            if ($name === '' || ! array_key_exists($name, $grouped)) {
                $ungraded[] = $row;
                continue;
            }
            $grouped[$name][] = $row;
        }

        $sheets = [];
        foreach ($grouped as $gradeName => $gradeRows) {
            $sheets[] = [
                'grade_name' => $gradeName,
                'rows' => array_values($gradeRows),
            ];
        }

        if ($ungraded !== []) {
            $sheets[] = [
                'grade_name' => '未分年級',
                'rows' => array_values($ungraded),
            ];
        }

        return $sheets;
    }

    /** 新增收款／報名計價 */
    public function create(Request $request): Response
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'pay_cycle' => ['nullable', 'string', 'in:monthly,quarterly,annual'],
            'as_of' => ['nullable', 'date'],
        ]);

        $studentPayload = null;
        $subjects = [];
        $warnings = [];
        $hasPriorPayments = false;
        $suggestedStartDate = null;
        $suggestedCourseIds = [];
        $suggestedPayCycle = 'quarterly';

        if (! empty($validated['student_id'])) {
            $student = Student::query()->findOrFail((int) $validated['student_id']);
            $this->authorizeTeacherCanViewStudent($student);
            $student->load(['gradeLevel:id,name,code', 'academicYear:id,year_code,name']);
            $subjects = EnrollmentPricing::subjectsForStudent($student);
            $warnings = $this->warnings($student, $subjects);
            $studentPayload = [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'grade_name' => $student->gradeLevel?->name,
                'grade_code' => $student->gradeLevel?->code !== null ? (int) $student->gradeLevel->code : null,
                'grade_level_id' => $student->grade_level_id !== null ? (int) $student->grade_level_id : null,
                'academic_year_name' => $student->academicYear?->name,
            ];
            $hasPriorPayments = BillingRenewal::hasPriorPayments($student);
            $suggestedStartDate = BillingRenewal::suggestedStartDate($student);

            $subjectIdSet = collect($subjects)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $requestCourseIds = array_values(array_unique(array_map(
                'intval',
                $validated['course_ids'] ?? []
            )));
            $requestCourseIds = array_values(array_filter(
                $requestCourseIds,
                fn (int $id) => in_array($id, $subjectIdSet, true)
            ));

            if ($requestCourseIds !== []) {
                $suggestedCourseIds = $requestCourseIds;
            } else {
                $intended = PromotionCourses::intendedOrSnapshotCourseIds($student);
                $suggestedCourseIds = array_values(array_filter(
                    $intended,
                    fn (int $id) => in_array($id, $subjectIdSet, true)
                ));
                $snapshot = BillingRenewal::lastPaidRenewalSnapshot($student);
                if ($snapshot !== null) {
                    $suggestedPayCycle = $snapshot['pay_cycle'];
                }
            }

            if (! empty($validated['pay_cycle'])) {
                $suggestedPayCycle = $validated['pay_cycle'];
            }
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

        $gradeLevelId = is_array($studentPayload) && ! empty($studentPayload['grade_level_id'])
            ? (int) $studentPayload['grade_level_id']
            : null;
        $schedule = \App\Support\ScheduleCalendar::payloadForRange(
            $from->toDateString(),
            $to->toDateString(),
            $gradeLevelId
        );

        $materialStatus = [];
        if (! empty($validated['student_id'] ?? null) && isset($student)) {
            $asOf = $validated['as_of']
                ?? $suggestedStartDate
                ?? Carbon::today()->toDateString();
            $subjectsById = collect($subjects)->keyBy('id')->all();
            $statusCourseIds = $suggestedCourseIds !== []
                ? $suggestedCourseIds
                : collect($subjects)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $materialStatus = MaterialHalfYear::statusForCourses(
                $student,
                $statusCourseIds,
                $asOf,
                $subjectsById
            );
        }

        return Inertia::render('StudentPayments/Create', [
            'student' => $studentPayload,
            'subjects' => $subjects,
            'warnings' => $warnings,
            'holidays' => $holidays,
            'schedule_closures' => $schedule['closures'],
            'schedule_makeups' => $schedule['makeups'],
            'has_prior_payments' => $hasPriorPayments,
            'suggested_start_date' => $suggestedStartDate,
            'suggested_course_ids' => $suggestedCourseIds,
            'suggested_pay_cycle' => $suggestedPayCycle,
            'material_status' => $materialStatus,
            'fee_discounts' => FeeDiscount::query()
                ->active()
                ->availableOn(Carbon::today()->toDateString())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (FeeDiscount $row): array => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'type' => $row->type,
                    'value' => (int) $row->value,
                    'label' => $row->label(),
                ])
                ->values()
                ->all(),
            'grade9_package' => isset($student)
                ? Grade9AnnualPackage::metaForStudent($student)
                : null,
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
            'from_year' => ['nullable', 'integer', 'min:2000', 'max:2100', 'required_with:from_month'],
            'from_month' => ['nullable', 'integer', 'min:1', 'max:12', 'required_with:from_year'],
            'to_year' => ['nullable', 'integer', 'min:2000', 'max:2100', 'required_with:to_month'],
            'to_month' => ['nullable', 'integer', 'min:1', 'max:12', 'required_with:to_year'],
        ]);

        $this->authorizeTeacherCanViewStudent($student);
        $student->load(['gradeLevel:id,name', 'academicYear:id,year_code,name']);

        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $month = isset($validated['month']) ? (int) $validated['month'] : null;
        $fromYear = isset($validated['from_year']) ? (int) $validated['from_year'] : null;
        $fromMonth = isset($validated['from_month']) ? (int) $validated['from_month'] : null;
        $toYear = isset($validated['to_year']) ? (int) $validated['to_year'] : null;
        $toMonth = isset($validated['to_month']) ? (int) $validated['to_month'] : null;

        if ($fromYear === null && $year !== null && $month !== null) {
            $fromYear = $year;
            $fromMonth = $month;
            $toYear = $year;
            $toMonth = $month;
        }

        $baseQuery = Reconciliation::query()
            ->where('student_id', $student->id);

        if ($fromYear !== null && $fromMonth !== null && $toYear !== null && $toMonth !== null) {
            $startKey = $fromYear * 12 + $fromMonth;
            $endKey = $toYear * 12 + $toMonth;
            $baseQuery->whereRaw('(billing_year * 12 + billing_month) between ? and ?', [$startKey, $endKey]);
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
                'receipt_no' => $row->receipt_no,
            ]);

        $unpaidTotal = (int) (clone $baseQuery)
            ->where('status', 'unpaid')
            ->sum('expected_amount');
        $paidTotal = (int) (clone $baseQuery)
            ->where('status', 'paid')
            ->sum('paid_amount');

        $period = null;
        if ($fromYear !== null && $fromMonth !== null && $toYear !== null && $toMonth !== null) {
            $periodRows = (clone $baseQuery)->get([
                'course_id',
                'expected_amount',
                'paid_amount',
                'paid_date',
                'status',
                'settled_by_user_id',
                'receipt_no',
            ]);
            $latestPaid = (clone $baseQuery)
                ->whereNotNull('paid_date')
                ->with('settledByUser:id,name')
                ->orderByDesc('paid_date')
                ->orderByDesc('id')
                ->first();

            $periodLabel = ($fromYear === $toYear && $fromMonth === $toMonth)
                ? sprintf('%d/%d', $fromYear, $fromMonth)
                : sprintf('%d/%d — %d/%d', $fromYear, $fromMonth, $toYear, $toMonth);

            $receiptNos = $periodRows
                ->pluck('receipt_no')
                ->filter(fn ($no) => is_string($no) && $no !== '')
                ->unique()
                ->values()
                ->all();

            $period = [
                'start_year' => $fromYear,
                'start_month' => $fromMonth,
                'end_year' => $toYear,
                'end_month' => $toMonth,
                'billing_year' => $toYear,
                'billing_month' => $toMonth,
                'period_label' => $periodLabel,
                'expected_total' => (int) $periodRows->sum('expected_amount'),
                'paid_total' => (int) $periodRows->sum('paid_amount'),
                'course_count' => $periodRows->pluck('course_id')->filter()->unique()->count(),
                'status' => $this->groupStatus($periodRows->pluck('status')->all()),
                'paid_date' => $latestPaid?->paid_date?->toDateString(),
                'settled_by_name' => $latestPaid?->settledByUser?->name ?? '—',
                'receipt_no' => $receiptNos[0] ?? $latestPaid?->receipt_no,
                'receipt_nos' => $receiptNos,
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
            'sessions' => ['nullable', 'array'],
            'sessions.*.date' => ['required', 'date'],
            'sessions.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'allowance' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'charge_material_course_ids' => ['nullable', 'array'],
            'charge_material_course_ids.*' => ['integer', 'exists:courses,id'],
            'material_half_charges' => ['nullable', 'array'],
            'material_half_charges.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'material_half_charges.*.period_year' => ['required', 'integer', 'between:2000,2100'],
            'material_half_charges.*.period_half' => ['required', 'in:H1,H2'],
            'material_half_charges.*.amount' => ['required', 'integer', 'min:1'],
            'fee_discount_id' => ['nullable', 'integer', 'exists:fee_discounts,id'],
            'receipt_no' => ['nullable', 'string', 'max:20'],
        ]);

        $suggested = BillingRenewal::suggestedStartDate($student);
        $startDate = $validated['start_date'] ?? null;
        if ($suggested !== null) {
            if ($startDate === null || Carbon::parse($startDate)->lt(Carbon::parse($suggested))) {
                $startDate = $suggested;
            }
        }

        $courseIdSet = array_fill_keys(array_map('intval', $validated['course_ids']), true);

        $chargeMaterialCourseIds = array_values(array_unique(array_map(
            'intval',
            $validated['charge_material_course_ids'] ?? []
        )));
        $chargeMaterialCourseIds = array_values(array_filter(
            $chargeMaterialCourseIds,
            fn (int $id) => isset($courseIdSet[$id])
        ));

        /** @var list<array{course_id:int, period_year:int, period_half:string, amount:int}> $materialHalfCharges */
        $materialHalfCharges = [];
        foreach ($validated['material_half_charges'] ?? [] as $row) {
            $cid = (int) ($row['course_id'] ?? 0);
            if (! isset($courseIdSet[$cid])) {
                continue;
            }
            $materialHalfCharges[] = [
                'course_id' => $cid,
                'period_year' => (int) $row['period_year'],
                'period_half' => (string) $row['period_half'],
                'amount' => (int) $row['amount'],
            ];
        }

        $useGrade9Package = Grade9AnnualPackage::qualifies(
            $student,
            $validated['course_ids'],
            $validated['pay_cycle'],
            $startDate,
        );

        if ($useGrade9Package) {
            $chargeMaterialCourseIds = [];
            $materialHalfCharges = [];
        }

        // 擋下已收過的半年教材
        $asOf = $startDate ?? Carbon::today()->toDateString();
        $subjects = EnrollmentPricing::subjectsForStudent($student);
        $subjectsById = collect($subjects)->keyBy('id')->all();
        $statusCourseIds = array_values(array_unique(array_map(
            fn (array $row): int => (int) $row['course_id'],
            $materialHalfCharges
        )));
        $materialStatus = MaterialHalfYear::statusForCourses(
            $student,
            $statusCourseIds,
            $asOf,
            $subjectsById
        );
        $statusKey = [];
        foreach ($materialStatus as $row) {
            $statusKey[$row['course_id'].'-'.$row['period_half'].'-'.$row['period_year']] = $row;
        }
        foreach ($materialHalfCharges as $charge) {
            $key = $charge['course_id'].'-'.$charge['period_half'].'-'.$charge['period_year'];
            $status = $statusKey[$key] ?? null;
            if ($status !== null && ! $status['can_charge']) {
                return back()->withErrors([
                    'material_half_charges' => '部分科目該半年教材已收過，請取消勾選後再送出。',
                ]);
            }
        }

        $feeDiscount = null;
        $feeDiscountId = isset($validated['fee_discount_id'])
            ? (int) $validated['fee_discount_id']
            : null;
        if ($feeDiscountId !== null) {
            $feeDiscount = FeeDiscount::query()
                ->active()
                ->availableOn($asOf)
                ->whereKey($feeDiscountId)
                ->first();
            if ($feeDiscount === null) {
                return back()->withErrors([
                    'fee_discount_id' => '所選優惠不存在、已停用或已過期。',
                ]);
            }
        }

        if ($useGrade9Package) {
            $quote = Grade9AnnualPackage::quote(
                $student,
                $validated['course_ids'],
                (string) $startDate,
            );
        } else {
            $quote = EnrollmentPricing::quote(
                $student,
                $validated['course_ids'],
                $validated['pay_cycle'],
                $validated['sessions'],
                0,
                $startDate,
                $chargeMaterialCourseIds,
                $materialHalfCharges,
            );
        }

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

        // 方案模式可不依賴堂次；一般模式仍需堂次
        if (! $useGrade9Package && ($validated['sessions'] ?? []) === []) {
            return back()->withErrors(['sessions' => '請至少選擇一個上課日。']);
        }

        $subtotal = (int) $quote['tuition_total'] + (int) $quote['material_total'];
        $allowance = $feeDiscount !== null
            ? $feeDiscount->computeAllowance($subtotal)
            : (int) ($validated['allowance'] ?? 0);
        $allowance = min($subtotal, max(0, $allowance));
        $quote['grand_total'] = max(0, $subtotal - $allowance);

        $manualReceiptNo = isset($validated['receipt_no'])
            ? trim((string) $validated['receipt_no'])
            : '';
        $manualReceiptNo = $manualReceiptNo === '' ? null : $manualReceiptNo;

        $receiptNo = BillingRenewal::persistQuote(
            $student,
            $validated['pay_cycle'],
            $quote,
            $allowance,
            $feeDiscount?->id,
            $feeDiscount?->label(),
            $manualReceiptNo,
        );

        MaterialHalfYear::recordCharges(
            $student,
            $materialHalfCharges,
            $asOf,
            auth()->id(),
        );

        $message = $useGrade9Package
            ? '已確認國三全科年繳方案（7–12 月共 6 期，合計 120,000，含教材）。'
            : '已確認收款並產生帳期。';
        if (is_string($receiptNo) && $receiptNo !== '') {
            $message .= '單據編號：'.$receiptNo;
        }

        return to_route('student-payments.show', $student)
            ->with('success', $message);
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
            [],
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

        $receiptNo = BillingRenewal::persistQuote($student, $snapshot['pay_cycle'], $quote, 0);

        $label = BillingRenewal::renewButtonLabel($snapshot['pay_cycle']);
        $message = "已確認收款並{$label}（自 {$startDate} 起算）。";
        if (is_string($receiptNo) && $receiptNo !== '') {
            $message .= '單據編號：'.$receiptNo;
        }

        return to_route('student-payments.show', $student)
            ->with('success', $message);
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

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRosterRowsForTeacher(array $rows): array
    {
        $user = auth()->user();
        if ($user?->role !== User::ROLE_TEACHER) {
            return $rows;
        }

        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            return [];
        }

        $allowedIds = Student::query()
            ->whereHas('enrollments.classroom', fn ($q) => $q->where('teacher_id', $teacherId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowed = array_fill_keys($allowedIds, true);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => isset($allowed[(int) ($row['student_id'] ?? 0)])
        ));
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
