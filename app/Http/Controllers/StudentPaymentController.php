<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\EnrollmentPricing;
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
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $studentPayload = null;
        $subjects = [];
        $warnings = [];

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

        return Inertia::render('StudentPayments/Index', [
            'student' => $studentPayload,
            'subjects' => $subjects,
            'warnings' => $warnings,
            'holidays' => $holidays,
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
    public function show(Student $student): Response
    {
        $this->authorizeTeacherCanViewStudent($student);
        $student->load(['gradeLevel:id,name', 'academicYear:id,year_code,name']);

        $rows = Reconciliation::query()
            ->where('student_id', $student->id)
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

        $unpaidTotal = (int) Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('status', 'unpaid')
            ->sum('expected_amount');
        $paidTotal = (int) Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->sum('paid_amount');

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
        ]);
    }

    /** 報名計價（導回首頁並帶入學生） */
    public function quote(Student $student): RedirectResponse
    {
        $this->authorizeTeacherCanViewStudent($student);

        return redirect()->route('student-payments.index', ['student_id' => $student->id]);
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

        $allowance = (int) ($validated['allowance'] ?? 0);
        $quote = EnrollmentPricing::quote(
            $student,
            $validated['course_ids'],
            $validated['pay_cycle'],
            $validated['sessions'],
            $allowance,
            $validated['start_date'] ?? null,
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

        DB::transaction(function () use ($student, $validated, $quote, $allowance, $months): void {
            $monthCount = count($months);
            $allowanceLeft = $allowance;

            foreach ($quote['lines'] as $line) {
                $tuition = (int) $line['tuition'];
                $material = (int) $line['material'];
                $courseTotal = $tuition + $material;

                $share = $quote['grand_total'] + $allowance > 0
                    ? (int) round($allowance * ($courseTotal / max(1, $quote['tuition_total'] + $quote['material_total'])))
                    : 0;
                $share = min($share, $allowanceLeft);
                $allowanceLeft -= $share;

                $tuitionPerMonth = $monthCount > 0 ? intdiv($tuition, $monthCount) : 0;
                $tuitionRemainder = $monthCount > 0 ? $tuition % $monthCount : 0;
                $allowancePerMonth = $monthCount > 0 ? intdiv($share, $monthCount) : 0;
                $allowanceRemainder = $monthCount > 0 ? $share % $monthCount : 0;

                /** @var array<string, array{amount:int, days:int}> $materialMonths */
                $materialMonths = $line['material_months'] ?? [];
                $materialNote = $line['material_note'] ?? null;

                foreach ($months as $index => $month) {
                    $y = (int) $month['y'];
                    $m = (int) $month['m'];
                    $key = $y.'-'.$m;
                    $monthMaterial = (int) ($materialMonths[$key]['amount'] ?? 0);
                    $monthTuition = $tuitionPerMonth + ($index === 0 ? $tuitionRemainder : 0);
                    $monthAllowance = $allowancePerMonth + ($index === 0 ? $allowanceRemainder : 0);
                    $amount = max(0, $monthTuition + $monthMaterial - $monthAllowance);

                    $noteParts = [
                        sprintf('報名計價｜%s｜單價 %s', $line['course_name'], number_format($line['unit_price'])),
                    ];
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
                            'paid_amount' => 0,
                            'paid_date' => null,
                            'status' => 'unpaid',
                            'pay_cycle' => $validated['pay_cycle'],
                            'note' => implode('｜', $noteParts),
                        ]
                    );
                }
            }
        });

        return to_route('student-payments.index', ['student_id' => $student->id])
            ->with('success', '已產生帳期，可至收款明細查看。');
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
}
