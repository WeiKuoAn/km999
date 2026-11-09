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
        'fee',
        'original_fee',
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
            'fee' => 'integer',
            'original_fee' => 'integer',
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
