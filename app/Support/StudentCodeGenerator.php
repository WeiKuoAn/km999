<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentCodeGenerator
{
    /**
     * 學號＝學年度＋年級兩碼＋流水三碼，例：11507001
     */
    public static function prefix(AcademicYear $year, GradeLevel $grade): string
    {
        return $year->year_code.$grade->codePadded();
    }

    public static function previewNext(AcademicYear $year, GradeLevel $grade): string
    {
        return self::prefix($year, $grade).str_pad((string) self::nextSequence($year, $grade), 3, '0', STR_PAD_LEFT);
    }

    public static function allocate(AcademicYear $year, GradeLevel $grade): string
    {
        return DB::transaction(function () use ($year, $grade) {
            // 鎖同年級流水，避免併發重號
            Student::query()
                ->where('academic_year_id', $year->id)
                ->where('grade_level_id', $grade->id)
                ->lockForUpdate()
                ->get(['id']);

            $code = self::previewNext($year, $grade);

            if (Student::query()->where('student_code', $code)->exists()) {
                throw new InvalidArgumentException('學號產生衝突，請重試。');
            }

            return $code;
        });
    }

    /**
     * 升級年級時重建學號：保留流水三碼，只改年級兩碼（與可選的學年碼）。
     * 例：11507001（國一）→ 11508001（國二）。
     * 若目標學號已被占用，改配該年級下一個可用流水。
     *
     * @param  list<string>  $reservedCodes 同批預覽／轉檔已預定的學號
     */
    public static function rebuildKeepingSequence(
        ?string $oldCode,
        AcademicYear $year,
        GradeLevel $newGrade,
        ?int $excludeStudentId = null,
        array $reservedCodes = [],
    ): string {
        $newPrefix = self::prefix($year, $newGrade);
        $reserved = array_fill_keys($reservedCodes, true);

        $seq = null;
        if (is_string($oldCode) && preg_match('/(\d{3})$/', $oldCode, $matches) === 1) {
            $seq = $matches[1];
        }

        if ($seq !== null) {
            $candidate = $newPrefix.$seq;
            if (! isset($reserved[$candidate]) && ! self::codeTaken($candidate, $excludeStudentId)) {
                return $candidate;
            }
        }

        // 找下一個未被占用的流水（含同批 reserved）
        $next = self::nextSequence($year, $newGrade);
        for ($i = 0; $i < 1000; $i++) {
            $candidate = $newPrefix.str_pad((string) ($next + $i), 3, '0', STR_PAD_LEFT);
            if (isset($reserved[$candidate])) {
                continue;
            }
            if (! self::codeTaken($candidate, $excludeStudentId)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('無法產生可用學號，請檢查年級流水是否已滿。');
    }

    private static function codeTaken(string $code, ?int $excludeStudentId): bool
    {
        $query = Student::query()->where('student_code', $code);
        if ($excludeStudentId !== null) {
            $query->where('id', '!=', $excludeStudentId);
        }

        return $query->exists();
    }

    public static function nextSequence(AcademicYear $year, GradeLevel $grade): int
    {
        $prefix = self::prefix($year, $grade);
        $prefixLength = strlen($prefix);

        $maxSeq = Student::query()
            ->where('student_code', 'like', $prefix.'%')
            ->get(['student_code'])
            ->map(function (Student $student) use ($prefix, $prefixLength) {
                $code = (string) $student->student_code;
                if (! str_starts_with($code, $prefix)) {
                    return 0;
                }
                $tail = substr($code, $prefixLength);
                if ($tail === '' || ! ctype_digit($tail)) {
                    return 0;
                }

                return (int) $tail;
            })
            ->max() ?? 0;

        return $maxSeq + 1;
    }
}
