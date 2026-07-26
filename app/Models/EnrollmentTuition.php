<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentTuition extends Model
{
    protected $fillable = [
        'enrollment_id',
        'duration_hours',
        'tuition_amount',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'float',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
