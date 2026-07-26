<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'course_category_id',
        'name',
        'color',
        'status',
        'pricing_group',
        'weekdays',
        'schedules',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'schedules' => 'array',
        ];
    }

    public function courseCategory(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function coursePrices(): HasMany
    {
        return $this->hasMany(CoursePrice::class)->orderBy('sort_order')->orderBy('id');
    }

    public function feePlans(): BelongsToMany
    {
        return $this->belongsToMany(FeePlan::class);
    }
}
