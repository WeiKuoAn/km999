<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ScheduleException extends Model
{
    public const TYPE_CLOSURE = 'closure';

    public const TYPE_MAKEUP = 'makeup';

    protected $fillable = [
        'type',
        'date_from',
        'date_to',
        'name',
        'grade_level_id',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'schedule_exception_course')
            ->with('courseCategory:id,name');
    }

    public function appliesToAllCourses(): bool
    {
        return ! $this->relationLoaded('courses')
            ? $this->courses()->count() === 0
            : $this->courses->isEmpty();
    }

    public function appliesToCourse(int $courseId): bool
    {
        if ($this->appliesToAllCourses()) {
            return true;
        }

        return $this->courses->contains(fn (Course $c) => (int) $c->id === $courseId);
    }

    public function appliesToGrade(?int $gradeLevelId): bool
    {
        if ($this->grade_level_id === null) {
            return true;
        }

        return $gradeLevelId !== null && (int) $this->grade_level_id === $gradeLevelId;
    }
}
