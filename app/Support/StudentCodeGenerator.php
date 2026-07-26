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
