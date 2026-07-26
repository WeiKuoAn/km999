<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'student_code',
        'academic_year_id',
        'grade_level_id',
        'name',
        'phone',
        'parent_name',
        'parent_phone',
        'parent_phones',
        'graduate_school',
        'current_school',
        'class_name',
        'id_number',
        'address',
        'address_city',
        'address_district',
        'address_zip',
        'address_detail',
        'gender',
        'status',
        'note',
        'school_segment',
    ];

    protected function casts(): array
    {
        return [
            'parent_phones' => 'array',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }
}
