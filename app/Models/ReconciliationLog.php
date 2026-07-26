<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationLog extends Model
{
    public const ACTION_CONFIRM = 'confirm';

    public const ACTION_UPDATE = 'update';

    public const ACTION_CANCEL = 'cancel';

    protected $fillable = [
        'student_id',
        'classroom_id',
        'billing_year',
        'billing_month',
        'expected_amount',
        'paid_amount',
        'paid_date',
        'status',
        'action',
        'performed_by_user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'paid_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
