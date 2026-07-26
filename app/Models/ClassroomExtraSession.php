<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClassroomExtraSession extends Model
{
    protected $fillable = [
        'classroom_id',
        'session_date',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * 若有關聯學生，僅這些人需點名／計入該日加課；若為空則代表全班在籍學生。
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'classroom_extra_session_student');
    }
}
