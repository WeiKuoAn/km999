<?php

namespace App\Models;

use App\Support\StudentFinanceSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualDepositRecord extends Model
{
    protected $fillable = [
        'student_id',
        'student_code',
        'student_name',
        'grade_name',
        'fee_plan_id',
        'fee_label',
        'amount',
        'paid_on',
        'note',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            StudentFinanceSnapshot::apply(
                $model,
                $model->student_id !== null ? (int) $model->student_id : null
            );
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
