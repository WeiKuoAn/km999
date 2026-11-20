<?php

namespace App\Models;

use App\Support\StudentFinanceSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMaterialPayment extends Model
{
    protected $fillable = [
        'student_id',
        'student_code',
        'student_name',
        'grade_name',
        'course_id',
        'period_year',
        'period_half',
        'amount',
        'paid_at',
        'paid_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'amount' => 'integer',
            'period_year' => 'integer',
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
