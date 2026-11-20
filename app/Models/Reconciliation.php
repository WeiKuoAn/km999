<?php

namespace App\Models;

use App\Support\StudentFinanceSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    protected $fillable = [
        'student_id',
        'student_code',
        'student_name',
        'grade_name',
        'classroom_id',
        'course_id',
        'pay_cycle',
        'billing_year',
        'billing_month',
        'expected_amount',
        'paid_amount',
        'paid_date',
        'status',
        'settled_by_user_id',
        'note',
        'receipt_no',
        'fee_discount_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_date' => 'date',
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

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }

    public function feeDiscount(): BelongsTo
    {
        return $this->belongsTo(FeeDiscount::class);
    }
}
