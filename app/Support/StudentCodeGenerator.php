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
            self::releaseGraduatedHolders($code);

            if (Student::query()->where('student_code', $code)->where('status', '!=', 'graduated')->exists()) {
                throw new InvalidArgumentException('學號產生衝突，請重試。');
            }

            return $code;
        });
    }

    /**
     * 升級年級時重建學號：保留流水三碼，只改年級兩碼（與可選的學年碼）。
     * 例：11507001（國一）→ 11508001（國二）。
     * 若目標學號被「在學／暫停」占用，改配該年級下一個可用流水。
     * 若僅被「已畢業」占用，轉檔時會釋出該號給升留級學生（畢業生改存封存學號）。
     *
     * @param  list<string>  $reservedCodes 同批預覽／轉檔已預定的學號
     */
    public static function rebuildKeepingSequence(
        ?string $oldCode,
        AcademicYear $year,
        GradeLevel $newGrade,
        ?int $excludeStudentId = null,
        array $reservedCodes = [],
        bool $releaseGraduated = false,
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
                if ($releaseGraduated) {
                    self::releaseGraduatedHolders($candidate, $excludeStudentId);
                }

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
                if ($releaseGraduated) {
                    self::releaseGraduatedHolders($candidate, $excludeStudentId);
                }

                return $candidate;
            }
        }

        throw new InvalidArgumentException('無法產生可用學號，請檢查年級流水是否已滿。');
    }

    /**
     * 在學／暫停學生視為占用；已畢業不阻擋升留級保留流水。
     */
    private static function codeTaken(string $code, ?int $excludeStudentId): bool
    {
        $query = Student::query()
            ->where('student_code', $code)
            ->where('status', '!=', 'graduated');
        if ($excludeStudentId !== null) {
            $query->where('id', '!=', $excludeStudentId);
        }

        return $query->exists();
    }

    /**
     * 將占用該學號的已畢業生改為封存學號，釋出原號給升留級。
     */
    public static function releaseGraduatedHolders(string $code, ?int $excludeStudentId = null): void
    {
        $query = Student::query()
            ->where('student_code', $code)
            ->where('status', 'graduated');
        if ($excludeStudentId !== null) {
            $query->where('id', '!=', $excludeStudentId);
        }

        foreach ($query->get() as $holder) {
            $archived = self::archiveCode($code, (int) $holder->id);
            $holder->update(['student_code' => $archived]);
        }
    }

    private static function archiveCode(string $code, int $studentId): string
    {
        $candidate = $code.'g'.$studentId;
        $n = 0;
        while (Student::query()->where('student_code', $candidate)->exists()) {
            $n++;
            $candidate = $code.'g'.$studentId.'x'.$n;
        }

        return $candidate;
    }

    public static function nextSequence(AcademicYear $year, GradeLevel $grade): int
    {
        $prefix = self::prefix($year, $grade);
        $prefixLength = strlen($prefix);

        // 只看在學／暫停，避免已畢業占號把流水往後推
        $maxSeq = Student::query()
            ->where('student_code', 'like', $prefix.'%')
            ->where('status', '!=', 'graduated')
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
