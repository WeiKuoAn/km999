<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Support\StudentCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentPromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'from_grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'to_grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'from_academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'to_academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'status' => ['nullable', 'in:active,paused,all'],
        ]);

        $fromGradeId = isset($validated['from_grade_level_id']) ? (int) $validated['from_grade_level_id'] : null;
        $toGradeId = isset($validated['to_grade_level_id']) ? (int) $validated['to_grade_level_id'] : null;
        $fromYearId = isset($validated['from_academic_year_id']) ? (int) $validated['from_academic_year_id'] : null;
        $toYearId = isset($validated['to_academic_year_id']) ? (int) $validated['to_academic_year_id'] : null;
        $status = (string) ($validated['status'] ?? 'active');

        $preview = [];
        $toGrade = $toGradeId !== null ? GradeLevel::query()->find($toGradeId) : null;
        $toYear = $toYearId !== null ? AcademicYear::query()->find($toYearId) : null;

        if ($fromGradeId !== null && $toGrade !== null) {
            $query = Student::query()
                ->with(['academicYear:id,year_code,name', 'gradeLevel:id,name,code'])
                ->where('grade_level_id', $fromGradeId)
                ->orderBy('student_code')
                ->orderBy('name');

            if ($fromYearId !== null) {
                $query->where('academic_year_id', $fromYearId);
            }

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $reservedCodes = [];
            $preview = $query->get()->map(function (Student $student) use ($toGrade, $toYear, &$reservedCodes): array {
                $year = $toYear ?? $student->academicYear;
                $newCode = null;
                $warning = null;

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
                        $warning = '目標學號已被占用，改配新流水 '.$newCode;
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
                    'warning' => $warning,
                ];
            })->values()->all();
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
            'filters' => [
                'from_grade_level_id' => $fromGradeId === null ? '' : (string) $fromGradeId,
                'to_grade_level_id' => $toGradeId === null ? '' : (string) $toGradeId,
                'from_academic_year_id' => $fromYearId === null ? '' : (string) $fromYearId,
                'to_academic_year_id' => $toYearId === null ? '' : (string) $toYearId,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'to_grade_level_id' => ['required', 'integer', 'exists:grade_levels,id', 'different:from_grade_level_id'],
            'from_academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'to_academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'status' => ['nullable', 'in:active,paused,all'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $fromGradeId = (int) $validated['from_grade_level_id'];
        $toGrade = GradeLevel::query()->findOrFail((int) $validated['to_grade_level_id']);
        $toYear = isset($validated['to_academic_year_id'])
            ? AcademicYear::query()->findOrFail((int) $validated['to_academic_year_id'])
            : null;
        $status = (string) ($validated['status'] ?? 'active');
        $studentIds = array_map('intval', $validated['student_ids']);

        $students = Student::query()
            ->with('academicYear')
            ->whereIn('id', $studentIds)
            ->where('grade_level_id', $fromGradeId)
            ->when(
                isset($validated['from_academic_year_id']),
                fn ($q) => $q->where('academic_year_id', (int) $validated['from_academic_year_id'])
            )
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->get();

        if ($students->isEmpty()) {
            return back()->with('error', '沒有符合條件可轉檔的學生。');
        }

        $count = 0;

        DB::transaction(function () use ($students, $toGrade, $toYear, &$count): void {
            $reservedCodes = [];
            foreach ($students as $student) {
                $year = $toYear ?? $student->academicYear;
                if ($year === null) {
                    continue;
                }

                $newCode = StudentCodeGenerator::rebuildKeepingSequence(
                    $student->student_code,
                    $year,
                    $toGrade,
                    $student->id,
                    $reservedCodes,
                );
                $reservedCodes[] = $newCode;

                $student->update([
                    'grade_level_id' => $toGrade->id,
                    'academic_year_id' => $year->id,
                    'student_code' => $newCode,
                ]);
                $count++;
            }
        });

        return to_route('student-promotions.index', [
            'from_grade_level_id' => $validated['to_grade_level_id'],
            'to_grade_level_id' => '',
            'from_academic_year_id' => $validated['to_academic_year_id'] ?? $validated['from_academic_year_id'] ?? '',
            'status' => $status,
        ])->with('success', "已完成轉檔 {$count} 位學生（年級＋學號已更新）。");
    }
}
