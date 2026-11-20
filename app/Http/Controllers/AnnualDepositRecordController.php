<?php

namespace App\Http\Controllers;

use App\Models\AnnualDepositRecord;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnnualDepositRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $month = $this->resolveMonth($validated['month'] ?? null);
        $monthStart = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
        $q = trim((string) ($validated['q'] ?? ''));

        $query = AnnualDepositRecord::query()
            ->with([
                'student:id,student_code,name,grade_level_id',
                'student.gradeLevel:id,name',
                'feePlan:id,group_name',
                'recordedBy:id,name',
            ])
            ->whereBetween('paid_on', [$monthStart, $monthEnd])
            ->orderByDesc('paid_on')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('fee_label', 'like', '%'.$q.'%')
                    ->orWhere('note', 'like', '%'.$q.'%')
                    ->orWhere('student_name', 'like', '%'.$q.'%')
                    ->orWhere('student_code', 'like', '%'.$q.'%')
                    ->orWhereHas('student', function ($studentQuery) use ($q): void {
                        $studentQuery->where('name', 'like', '%'.$q.'%')
                            ->orWhere('student_code', 'like', '%'.$q.'%');
                    });
            });
        }

        $user = $request->user();
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas(
                    'student.enrollments.classroom',
                    fn ($builder) => $builder->where('teacher_id', $teacherId)
                );
            }
        }

        $monthTotal = (clone $query)->sum('amount');

        return Inertia::render('AnnualDepositRecords/Index', [
            'filters' => [
                'month' => $month,
                'q' => $q,
            ],
            'month_total' => (int) $monthTotal,
            'feePlans' => $this->feePlanOptions(),
            'records' => $query
                ->paginate(50)
                ->withQueryString()
                ->through(fn (AnnualDepositRecord $row): array => $this->payload($row)),
        ]);
    }

    public function create(): RedirectResponse
    {
        return to_route('annual-deposit-records.index');
    }

    /** 年繳訂金多選：僅國二在籍生；預設隱藏已有訂金紀錄者 */
    public function students(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'include_paid' => ['nullable', 'boolean'],
        ]);
        $q = trim((string) ($validated['q'] ?? ''));
        $includePaid = $request->boolean('include_paid');

        $grade2Id = GradeLevel::query()
            ->where(function ($builder): void {
                $builder->where('code', 8)->orWhere('name', '國二');
            })
            ->value('id');

        if ($grade2Id === null) {
            return response()->json([
                'grade_name' => '國二',
                'students' => [],
                'paid_count' => 0,
                'include_paid' => $includePaid,
            ]);
        }

        $query = Student::query()
            ->where('status', 'active')
            ->where('grade_level_id', $grade2Id)
            ->orderBy('student_code')
            ->orderBy('name');

        $user = $request->user();
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                return response()->json([
                    'grade_name' => '國二',
                    'students' => [],
                    'paid_count' => 0,
                    'include_paid' => $includePaid,
                ]);
            }
            $query->whereHas(
                'enrollments.classroom',
                fn ($builder) => $builder->where('teacher_id', $teacherId)
            );
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('student_code', 'like', '%'.$q.'%');
            });
        }

        $candidateIds = (clone $query)->pluck('id');
        $paidIds = AnnualDepositRecord::query()
            ->whereIn('student_id', $candidateIds)
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $paidSet = array_fill_keys($paidIds, true);
        $paidCount = count($paidIds);

        if (! $includePaid && $paidIds !== []) {
            $query->whereNotIn('id', $paidIds);
        }

        $students = $query
            ->get(['id', 'student_code', 'name'])
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'paid' => isset($paidSet[(int) $student->id]),
            ])
            ->values()
            ->all();

        return response()->json([
            'grade_name' => '國二',
            'students' => $students,
            'paid_count' => $paidCount,
            'include_paid' => $includePaid,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'exists:students,id'],
            'fee_plan_id' => ['nullable', 'integer', 'exists:fee_plans,id'],
            'fee_label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $grade2Id = GradeLevel::query()
            ->where(function ($builder): void {
                $builder->where('code', 8)->orWhere('name', '國二');
            })
            ->value('id');

        $studentIds = array_values(array_unique(array_map('intval', $validated['student_ids'])));
        $allowedIds = Student::query()
            ->whereIn('id', $studentIds)
            ->where('status', 'active')
            ->when(
                $grade2Id !== null,
                fn ($q) => $q->where('grade_level_id', $grade2Id)
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($allowedIds === []) {
            return back()->withErrors([
                'student_ids' => '請至少勾選一位國二學生。',
            ]);
        }

        $userId = $request->user()?->id;
        $paidOn = Carbon::parse($validated['paid_on'])->toDateString();
        $feePlanId = isset($validated['fee_plan_id']) ? (int) $validated['fee_plan_id'] : null;
        $label = trim((string) $validated['fee_label']);
        if ($feePlanId !== null && $label === '') {
            $label = (string) (FeePlan::query()->whereKey($feePlanId)->value('group_name') ?? '年繳訂金');
        }
        $label = $label !== '' ? $label : '年繳訂金';
        $note = isset($validated['note']) && trim((string) $validated['note']) !== ''
            ? trim((string) $validated['note'])
            : null;
        $amount = (int) $validated['amount'];

        DB::transaction(function () use ($allowedIds, $paidOn, $userId, $feePlanId, $label, $note, $amount): void {
            foreach ($allowedIds as $studentId) {
                AnnualDepositRecord::query()->create([
                    'student_id' => $studentId,
                    'fee_plan_id' => $feePlanId,
                    'fee_label' => $label,
                    'amount' => $amount,
                    'paid_on' => $paidOn,
                    'note' => $note,
                    'recorded_by_user_id' => $userId,
                ]);
            }
        });

        return to_route('annual-deposit-records.index', [
            'month' => Carbon::parse($paidOn)->format('Y-m'),
        ])->with('success', '已登記 '.count($allowedIds).' 筆年繳訂金。');
    }

    public function edit(AnnualDepositRecord $annualDepositRecord): Response
    {
        $annualDepositRecord->load([
            'student:id,student_code,name,grade_level_id',
            'student.gradeLevel:id,name',
        ]);

        return Inertia::render('AnnualDepositRecords/Edit', [
            'record' => $this->payload($annualDepositRecord),
            'feePlans' => $this->feePlanOptions(),
        ]);
    }

    public function update(Request $request, AnnualDepositRecord $annualDepositRecord): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'fee_plan_id' => ['nullable', 'integer', 'exists:fee_plans,id'],
            'fee_label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $studentId = isset($validated['student_id']) ? (int) $validated['student_id'] : null;
        // 學生已刪的歷史訂金：允許只改金額／備註，保留原快照
        if ($studentId === null && $annualDepositRecord->student_id !== null) {
            $studentId = (int) $annualDepositRecord->student_id;
        }

        $annualDepositRecord->update([
            'student_id' => $studentId,
            'fee_plan_id' => $validated['fee_plan_id'] ?? null,
            'fee_label' => trim($validated['fee_label']),
            'amount' => (int) $validated['amount'],
            'paid_on' => Carbon::parse($validated['paid_on'])->toDateString(),
            'note' => isset($validated['note']) && trim((string) $validated['note']) !== ''
                ? trim((string) $validated['note'])
                : null,
        ]);

        $month = $annualDepositRecord->paid_on?->format('Y-m');

        return to_route('annual-deposit-records.index', array_filter([
            'month' => $month,
        ]))->with('success', '已更新年繳訂金紀錄。');
    }

    public function destroy(AnnualDepositRecord $annualDepositRecord): RedirectResponse
    {
        $month = $annualDepositRecord->paid_on?->format('Y-m');
        $annualDepositRecord->delete();

        return to_route('annual-deposit-records.index', array_filter([
            'month' => $month,
        ]))->with('success', '已刪除年繳訂金紀錄。');
    }

    /**
     * @return array{
     *   id:int,
     *   student_id:int,
     *   student_code:?string,
     *   student_name:string,
     *   grade_name:?string,
     *   fee_plan_id:?int,
     *   fee_plan_name:?string,
     *   fee_label:string,
     *   amount:int,
     *   paid_on:string,
     *   note:?string,
     *   recorded_by:?string,
     *   created_at:?string
     * }
     */
    private function payload(AnnualDepositRecord $row): array
    {
        return [
            'id' => $row->id,
            'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
            'student_code' => $row->student?->student_code ?? $row->student_code,
            'student_name' => (string) ($row->student?->name ?? $row->student_name ?? '（已刪除學生）'),
            'grade_name' => $row->student?->gradeLevel?->name ?? $row->grade_name,
            'fee_plan_id' => $row->fee_plan_id !== null ? (int) $row->fee_plan_id : null,
            'fee_plan_name' => $row->feePlan?->group_name,
            'fee_label' => $row->fee_label,
            'amount' => (int) $row->amount,
            'paid_on' => $row->paid_on?->toDateString() ?? '',
            'note' => $row->note,
            'recorded_by' => $row->recordedBy?->name,
            'created_at' => $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }

    private function resolveMonth(?string $month): string
    {
        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->format('Y-m');
            } catch (\Throwable) {
                // fall through
            }
        }

        return Carbon::today()->format('Y-m');
    }

    /**
     * 國二訂金通常對應國三（或未指定年級）年繳方案，兩者皆列出。
     *
     * @return list<array{id:int, label:string, package_total:int}>
     */
    private function feePlanOptions(): array
    {
        $gradeIds = GradeLevel::query()
            ->where(function ($builder): void {
                $builder->whereIn('code', [8, 9])
                    ->orWhereIn('name', ['國二', '國三']);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return FeePlan::query()
            ->where('is_active', true)
            ->where('plan_kind', 'annual_package')
            ->when(
                $gradeIds !== [],
                fn ($q) => $q->where(function ($builder) use ($gradeIds): void {
                    $builder->whereNull('grade_level_id')
                        ->orWhereIn('grade_level_id', $gradeIds);
                })
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'group_name', 'package_total'])
            ->map(fn (FeePlan $plan): array => [
                'id' => $plan->id,
                'label' => $plan->group_name,
                'package_total' => (int) ($plan->package_total ?? 0),
            ])
            ->values()
            ->all();
    }
}
