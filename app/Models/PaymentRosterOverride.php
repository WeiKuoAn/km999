<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRosterOverride extends Model
{
    protected $fillable = [
        'student_id',
        'start_year',
        'start_month',
        'end_year',
        'end_month',
        'course_ids',
        'course_months',
        'pay_cycle',
        'material_half_charges',
        'fee',
        'original_fee',
        'allowance',
        'note',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'start_month' => 'integer',
            'end_year' => 'integer',
            'end_month' => 'integer',
            'course_ids' => 'array',
            'course_months' => 'array',
            'material_half_charges' => 'array',
            'fee' => 'integer',
            'original_fee' => 'integer',
            'allowance' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
