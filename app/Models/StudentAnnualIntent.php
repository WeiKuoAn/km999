<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnnualIntent extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'student_id',
        'fee_plan_id',
        'package_label',
        'package_total',
        'status',
        'note',
        'recorded_by_user_id',
        'student_promotion_log_id',
    ];

    protected function casts(): array
    {
        return [
            'package_total' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function promotionLog(): BelongsTo
    {
        return $this->belongsTo(StudentPromotionLog::class, 'student_promotion_log_id');
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    /** @return array{fee_plan_id:?int, package_label:?string, package_total:?int, note:?string, is_annual:bool} */
    public function toPayload(): array
    {
        return [
            'fee_plan_id' => $this->fee_plan_id !== null ? (int) $this->fee_plan_id : null,
            'package_label' => $this->package_label,
            'package_total' => $this->package_total !== null ? (int) $this->package_total : null,
            'note' => $this->note,
            'is_annual' => $this->fee_plan_id !== null,
        ];
    }
}
