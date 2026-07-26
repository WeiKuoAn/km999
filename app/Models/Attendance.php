<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'classroom_id',
        'makeup_for_classroom_id',
        'student_id',
        'class_date',
        'status',
        'duration_hours',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'class_date' => 'date',
            'duration_hours' => 'float',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function makeupForClassroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'makeup_for_classroom_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
