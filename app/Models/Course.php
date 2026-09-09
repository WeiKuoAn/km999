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
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'schedules' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** 未設定起迄日＝長期課，行事曆不限制日期 */
    public function dateRangeUnrestricted(): bool
    {
        return $this->start_date === null && $this->end_date === null;
    }

    /**
     * @return array{
     *   start_date:?string,
     *   end_date:?string,
     *   date_range_unrestricted:bool,
     *   teaching_periods:list<array{start_date:?string,end_date:?string}>
     * }
     */
    public function scheduleDatePayload(): array
    {
        $start = $this->start_date?->toDateString();
        $end = $this->end_date?->toDateString();
        $unrestricted = $start === null && $end === null;

        return [
            'start_date' => $start,
            'end_date' => $end,
            'date_range_unrestricted' => $unrestricted,
            'teaching_periods' => $unrestricted
                ? []
                : [['start_date' => $start, 'end_date' => $end]],
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
