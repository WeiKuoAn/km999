<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 組合優惠：報了組合內至少 min_courses 科時生效。
 * 各科優惠價存在 pivot course_bundle_course.price（null＝該科不優惠，收原價）。
 * course_bundles.price 僅作列表摘要（各科有填優惠價的最低值）。
 */
class CourseBundle extends Model
{
    protected $fillable = [
        'name',
        'academic_year_id',
        'grade_level_id',
        'min_courses',
        'price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_courses' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_bundle_course')
            ->withPivot('price');
    }
}
