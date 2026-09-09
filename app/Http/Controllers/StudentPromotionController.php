<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudentPromotionLog;
use App\Support\PromotionCourses;
use App\Support\StudentCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentPromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'from_grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'to_grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'graduate' => ['nullable', 'boolean'],
            'from_academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'status' => ['nullable', 'in:active,paused,all'],
        ]);

        $fromGradeId = isset($validated['from_grade_level_id']) ? (int) $validated['from_grade_level_id'] : null;
        $toGradeId = isset($validated['to_grade_level_id']) ? (int) $validated['to_grade_level_id'] : null;
        $graduate = (bool) ($validated['graduate'] ?? false);
        $fromYearId = isset($validated['from_academic_year_id']) ? (int) $validated['from_academic_year_id'] : null;
        $status = (string) ($validated['status'] ?? 'active');

        $preview = [];
        $targetSubjects = [];
        $fromGrade = $fromGradeId !== null ? GradeLevel::query()->find($fromGradeId) : null;
        $toGrade = $toGradeId !== null ? GradeLevel::query()->find($toGradeId) : null;

        if ($fromGrade !== null && ($graduate || $toGrade !== null)) {
            if ($graduate && ! $this->isGraduationGrade($fromGrade)) {
                $graduate = false;
            }

            $query = Student::query()
                ->with(['academicYear:id,year_code,name', 'gradeLevel:id,name,code'])
                ->where('grade_level_id', $fromGrade->id)
                ->orderBy('student_code')
                ->orderBy('name');

            if ($fromYearId !== null) {
                $query->where('academic_year_id', $fromYearId);
            }

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($graduate) {
                $preview = $query->get()->map(function (Student $student): array {
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'student_code' => $student->student_code,
                        'new_student_code' => $student->student_code,
                        'grade_name' => $student->gradeLevel?->name,
                        'academic_year_name' => $student->academicYear?->displayName(),
                        'status' => $student->status,
                        'new_status' => 'graduated',
                        'action' => 'graduate',
                        'course_mode' => null,
                        'current_labels' => [],
                        'transferable_labels' => [],
                        'blocked_labels' => [],
                        'transferable_ids' => [],
                        'course_note' => null,
                        'warning' => $student->status === 'graduated' ? '已是畢業狀態' : null,
                    ];
                })->values()->all();
            } elseif ($toGrade !== null) {
                $reservedCodes = [];
                $students = $query->get();
                $analyses = [];
                $subjectMap = [];
                foreach ($students as $student) {
                    $analysis = PromotionCourses::analyze($student, $toGrade);
                    $analyses[$student->id] = $analysis;
                    foreach ($analysis['target_subjects'] as $subject) {
                        $subjectMap[(int) $subject['id']] = $subject;
                    }
                }
                $targetSubjects = array_values($subjectMap);

                $preview = $students->map(function (Student $student) use ($toGrade, &$reservedCodes, $analyses): array {
                    $year = $student->academicYear;
                    $newCode = null;
                    $warning = null;
                    $courses = $analyses[$student->id];

                    if ($year === null) {
                        $warning = '缺少學年，無法預覽學號';
                    } else {
                        $desiredSeq = is_string($student->student_code) && preg_match('/(\d{3})$/', $student->student_code, $m)
                            ? $m[1]
                            : null;
                        $newCode = StudentCodeGenerator::rebuildKeepingSequence(
                            $student->student_code,
                            $year,
                            $toGrade,
                            $student->id,
                            $reservedCodes,
                        );
                        $reservedCodes[] = $newCode;

                        if ($student->student_code === null) {
                            $warning = '原無學號，將新編流水';
                        } elseif ($desiredSeq !== null && ! str_ends_with($newCode, $desiredSeq)) {
                            $warning = '新學號已被占用，改配新流水 '.$newCode;
                        }
                    }

                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'student_code' => $student->student_code,
                        'new_student_code' => $newCode,
                        'grade_name' => $student->gradeLevel?->name,
                        'academic_year_name' => $student->academicYear?->displayName(),
                        'status' => $student->status,
                        'new_status' => $student->status,
                        'action' => 'promote',
                        'course_mode' => $courses['mode'],
                        'current_labels' => $courses['current_labels'],
                        'transferable_labels' => $courses['transferable_labels'],
                        'blocked_labels' => $courses['blocked_labels'],
                        'transferable_ids' => $courses['transferable_ids'],
                        'course_note' => $courses['note'],
                        'warning' => $warning,
                    ];
                })->values()->all();
            }
        }

        $grades = GradeLevel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'name', 'code'])
            ->map(fn (GradeLevel $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'code' => $g->code,
                'code_padded' => $g->codePadded(),
                'is_graduation_grade' => $this->isGraduationGrade($g),
            ])
            ->all();

        $years = AcademicYear::query()
            ->orderByDesc('is_current')
            ->orderBy('sort_order')
            ->orderBy('year_code')
            ->get(['id', 'year_code', 'name', 'is_current'])
            ->map(fn (AcademicYear $y) => [
                'id' => $y->id,
                'year_code' => $y->year_code,
                'name' => $y->displayName(),
                'is_current' => $y->is_current,
            ])
            ->all();

        return Inertia::render('StudentPromotions/Index', [
            'grades' => $grades,
            'years' => $years,
            'preview' => $preview,
            'target_subjects' => $targetSubjects,
            'logs' => $this->recentLogs(),
            'filters' => [
                'from_grade_level_id' => $fromGradeId === null ? '' : (string) $fromGradeId,
                'to_grade_level_id' => $graduate ? '' : ($toGradeId === null ? '' : (string) $toGradeId),
                'graduate' => $graduate,
                'from_academic_year_id' => $fromYearId === null ? '' : (string) $fromYearId,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'to_grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id', 'different:from_grade_level_id'],
            'graduate' => ['nullable', 'boolean'],
            'from_academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'status' => ['nullable', 'in:active,paused,all'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'course_selections' => ['nullable', 'array'],
            'course_selections.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'course_selections.*.course_ids' => ['nullable', 'array'],
            'course_selections.*.course_ids.*' => ['integer', 'exists:courses,id'],
        ]);

        $graduate = (bool) ($validated['graduate'] ?? false);
        if (! $graduate && empty($validated['to_grade_level_id'])) {
            throw ValidationException::withMessages([
                'to_grade_level_id' => '請選擇新的年級，或改為畢業轉檔。',
            ]);
        }

        $fromGrade = GradeLevel::query()->findOrFail((int) $validated['from_grade_level_id']);
        $status = (string) ($validated['status'] ?? 'active');
        $studentIds = array_map('intval', $validated['student_ids']);

        if ($graduate && ! $this->isGraduationGrade($fromGrade)) {
            return back()->with('error', '只有國三可以轉成已畢業。');
        }

        $toGrade = null;
        if (! $graduate) {
            $toGrade = GradeLevel::query()->findOrFail((int) $validated['to_grade_level_id']);
        }

        $students = Student::query()
            ->with('academicYear')
            ->whereIn('id', $studentIds)
            ->where('grade_level_id', $fromGrade->id)
            ->when(
                isset($validated['from_academic_year_id']),
                fn ($q) => $q->where('academic_year_id', (int) $validated['from_academic_year_id'])
            )
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->get()
            ->keyBy('id');

        if ($students->isEmpty()) {
            return back()->with('error', '沒有符合條件可轉檔的學生。');
        }

        /** @var array<int, list<int>> $selectionMap */
        $selectionMap = [];
        foreach ($validated['course_selections'] ?? [] as $row) {
            $sid = (int) ($row['student_id'] ?? 0);
            $selectionMap[$sid] = array_values(array_unique(array_map('intval', $row['course_ids'] ?? [])));
        }

        if (! $graduate && $toGrade !== null) {
            $errors = [];
            foreach ($studentIds as $sid) {
                $student = $students->get($sid);
                if ($student === null) {
                    continue;
                }
                $analysis = PromotionCourses::analyze($student, $toGrade);
                $hasExplicit = array_key_exists($sid, $selectionMap);
                // 需手動選課、或個別轉檔有送選課：都要驗證
                if ($analysis['mode'] !== 'manual' && ! $hasExplicit) {
                    continue;
                }
                if (! $hasExplicit || $selectionMap[$sid] === []) {
                    $errors['course_selections.'.$sid] = $student->name.'：請選擇'.$toGrade->name.'要修的課程。';
                } else {
                    $allowed = array_fill_keys(
                        array_map(fn (array $s): int => (int) $s['id'], $analysis['target_subjects']),
                        true
                    );
                    foreach ($selectionMap[$sid] as $cid) {
                        if (! isset($allowed[$cid])) {
                            $errors['course_selections.'.$sid] = $student->name.'：含有'.$toGrade->name.'不可選的課程。';
                            break;
                        }
                    }
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $count = 0;
        $autoCount = 0;
        $manualCount = 0;
        $actorId = auth()->id();

        DB::transaction(function () use (
            $students,
            $studentIds,
            $fromGrade,
            $toGrade,
            $graduate,
            $selectionMap,
            $actorId,
            &$count,
            &$autoCount,
            &$manualCount
        ): void {
            if ($graduate) {
                foreach ($studentIds as $sid) {
                    $student = $students->get($sid);
                    if ($student === null) {
                        continue;
                    }
                    $oldStatus = $student->status;
                    $oldCode = $student->student_code;
                    $student->update(['status' => 'graduated']);

                    $this->writePromotionLog([
                        'student_id' => $student->id,
                        'action' => 'graduate',
                        'from_grade_level_id' => $fromGrade->id,
                        'to_grade_level_id' => null,
                        'from_grade_name' => $fromGrade->name,
                        'to_grade_name' => '已畢業',
                        'old_student_code' => $oldCode,
                        'new_student_code' => $oldCode,
                        'old_status' => $oldStatus,
                        'new_status' => 'graduated',
                        'course_mode' => null,
                        'previous_course_ids' => null,
                        'new_course_ids' => null,
                        'performed_by_user_id' => $actorId,
                        'note' => '國三轉已畢業',
                    ]);
                    $count++;
                }

                return;
            }

            $reservedCodes = [];
            foreach ($studentIds as $sid) {
                $student = $students->get($sid);
                if ($student === null || $toGrade === null) {
                    continue;
                }
                $year = $student->academicYear;
                if ($year === null) {
                    continue;
                }

                $analysis = PromotionCourses::analyze($student, $toGrade);
                $previousIds = $analysis['current_course_ids'];

                if (array_key_exists($sid, $selectionMap)) {
                    $courseIds = $selectionMap[$sid];
                    $courseMode = 'manual';
                    $manualCount++;
                    $courseNote = '個別／手動選課';
                } elseif ($analysis['mode'] === 'auto') {
                    $courseIds = $analysis['transferable_ids'];
                    $courseMode = 'auto';
                    $autoCount++;
                    $courseNote = '科目不變，自動帶課';
                } else {
                    $courseIds = $selectionMap[$sid] ?? [];
                    $courseMode = 'manual';
                    $manualCount++;
                    $courseNote = '科目有變，手動選課';
                }

                $oldCode = $student->student_code;
                $oldStatus = $student->status;
                $newCode = StudentCodeGenerator::rebuildKeepingSequence(
                    $student->student_code,
                    $year,
                    $toGrade,
                    $student->id,
                    $reservedCodes,
                    true,
                );
                $reservedCodes[] = $newCode;

                $student->update([
                    'grade_level_id' => $toGrade->id,
                    'student_code' => $newCode,
                ]);

                PromotionCourses::applyAfterPromote($student->fresh(), $courseIds, $previousIds);

                $this->writePromotionLog([
                    'student_id' => $student->id,
                    'action' => 'promote',
                    'from_grade_level_id' => $fromGrade->id,
                    'to_grade_level_id' => $toGrade->id,
                    'from_grade_name' => $fromGrade->name,
                    'to_grade_name' => $toGrade->name,
                    'old_student_code' => $oldCode,
                    'new_student_code' => $newCode,
                    'old_status' => $oldStatus,
                    'new_status' => $oldStatus,
                    'course_mode' => $courseMode,
                    'previous_course_ids' => $previousIds,
                    'new_course_ids' => $courseIds,
                    'performed_by_user_id' => $actorId,
                    'note' => $courseNote,
                ]);
                $count++;
            }
        });

        if ($graduate) {
            $message = "已將 {$count} 位國三學生標記為已畢業。";
        } else {
            $parts = ["已完成轉檔 {$count} 位學生（年級＋學號已更新）"];
            if ($autoCount > 0) {
                $parts[] = "自動帶課 {$autoCount} 人";
            }
            if ($manualCount > 0) {
                $parts[] = "手動選課 {$manualCount} 人";
            }
            $message = implode('；', $parts).'。';
        }

        return to_route('student-promotions.index', [
            'from_grade_level_id' => $graduate ? $fromGrade->id : (string) $validated['to_grade_level_id'],
            'to_grade_level_id' => '',
            'graduate' => false,
            'from_academic_year_id' => $validated['from_academic_year_id'] ?? '',
            'status' => $status,
        ])->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePromotionLog(array $payload): void
    {
        if (! Schema::hasTable('student_promotion_logs')) {
            return;
        }

        StudentPromotionLog::query()->create($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentLogs(): array
    {
        if (! Schema::hasTable('student_promotion_logs')) {
            return [];
        }

        return StudentPromotionLog::query()
            ->with([
                'student:id,name,student_code',
                'performedByUser:id,name',
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (StudentPromotionLog $log): array {
                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->format('Y-m-d H:i'),
                    'action' => $log->action,
                    'action_label' => $log->action === 'graduate' ? '已畢業' : '升級轉檔',
                    'student_name' => $log->student?->name ?? '—',
                    'from_grade_name' => $log->from_grade_name,
                    'to_grade_name' => $log->to_grade_name,
                    'old_student_code' => $log->old_student_code,
                    'new_student_code' => $log->new_student_code,
                    'course_mode' => $log->course_mode,
                    'course_mode_label' => match ($log->course_mode) {
                        'auto' => '自動帶課',
                        'manual' => '手動選課',
                        default => '—',
                    },
                    'note' => $log->note,
                    'performed_by' => $log->performedByUser?->name ?? '—',
                ];
            })
            ->values()
            ->all();
    }

    private function isGraduationGrade(GradeLevel $grade): bool
    {
        return $grade->name === '國三' || (int) $grade->code === 9;
    }
}
