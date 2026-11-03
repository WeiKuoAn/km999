<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStandardTemplate extends Model
{
    public const DEFAULT_FOOTER = '費用上有任何疑問，歡迎來電 07-5524889，感謝家長配合。';

    protected $fillable = [
        'academic_year_id',
        'grade_level_id',
        'title',
        'deadline_label',
        'footer_note',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
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

    public function items(): HasMany
    {
        return $this->hasMany(FeeStandardTemplateItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(FeeStandardTemplateExport::class);
    }

    public function footerText(): string
    {
        $note = trim((string) ($this->footer_note ?? ''));

        return $note !== '' ? $note : self::DEFAULT_FOOTER;
    }
}
