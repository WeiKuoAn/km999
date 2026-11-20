<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;

/** 帳務列寫入時帶上學號／姓名／年級，刪學生後仍可顯示與加總。 */
final class StudentFinanceSnapshot
{
    /**
     * @return array{student_code:?string, student_name:string, grade_name:?string}
     */
    public static function attrs(Student $student): array
    {
        $student->loadMissing('gradeLevel:id,name');

        return [
            'student_code' => $student->student_code,
            'student_name' => (string) $student->name,
            'grade_name' => $student->gradeLevel?->name,
        ];
    }

    public static function apply(Model $model, ?int $studentId): void
    {
        if ($studentId === null || $studentId <= 0) {
            return;
        }

        $student = Student::query()->find($studentId);
        if ($student === null) {
            return;
        }

        $fillable = $model->getFillable();
        foreach (self::attrs($student) as $key => $value) {
            if (in_array($key, $fillable, true)) {
                $model->setAttribute($key, $value);
            }
        }
    }
}
