<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePlanPeriodCoursePrice extends Model
{
    protected $fillable = [
        'fee_plan_period_id',
        'course_id',
        'total_price',
        'double_price',
        'triple_price',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'integer',
            'double_price' => 'integer',
            'triple_price' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FeePlanPeriod::class, 'fee_plan_period_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
