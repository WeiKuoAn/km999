<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPromotionLog extends Model
{
    protected $fillable = [
        'student_id',
        'action',
        'from_grade_level_id',
        'to_grade_level_id',
        'from_grade_name',
        'to_grade_name',
        'old_student_code',
        'new_student_code',
        'old_status',
        'new_status',
        'course_mode',
        'previous_course_ids',
        'new_course_ids',
        'performed_by_user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'previous_course_ids' => 'array',
            'new_course_ids' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function fromGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'from_grade_level_id');
    }

    public function toGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'to_grade_level_id');
    }
}
