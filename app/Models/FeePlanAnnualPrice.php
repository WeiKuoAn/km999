<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 收費設定各科年繳金額（整學年 7–6 一次收完）。 */
class FeePlanAnnualPrice extends Model
{
    protected $fillable = [
        'fee_plan_id',
        'course_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
