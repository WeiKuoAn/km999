<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCourseDrop extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'dropped_at',
    ];

    protected function casts(): array
    {
        return [
            'dropped_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
