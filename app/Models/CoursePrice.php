<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePrice extends Model
{
    protected $fillable = [
        'course_id',
        'level',
        'duration_hours',
        'tuition',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'float',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
