<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePlanPeriod extends Model
{
    protected $fillable = [
        'fee_plan_id',
        'start_month',
        'end_month',
        'total_price',
        'double_price',
        'triple_price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'start_month' => 'integer',
            'end_month' => 'integer',
            'total_price' => 'integer',
            'double_price' => 'integer',
            'triple_price' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }

    public function coursePrices(): HasMany
    {
        return $this->hasMany(FeePlanPeriodCoursePrice::class, 'fee_plan_period_id');
    }

    public function label(): string
    {
        return $this->start_month === $this->end_month
            ? $this->start_month.'月'
            : $this->start_month.'–'.$this->end_month.'月';
    }
}
