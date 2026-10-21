<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePlan extends Model
{
    protected $fillable = [
        'academic_year_id',
        'grade_level_id',
        'group_name',
        'pricing_group',
        'unit',
        'session_block_size',
        'list_price',
        'quarter_price',
        'quarter_single_price',
        'quarter_double_price',
        'material_fee',
        'material_unit',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'integer',
            'quarter_price' => 'integer',
            'quarter_single_price' => 'integer',
            'quarter_double_price' => 'integer',
            'material_fee' => 'integer',
            'session_block_size' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
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
        return $this->belongsToMany(Course::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FeePlanPeriod::class)
            ->orderBy('sort_order')
            ->orderBy('start_month');
    }

    public function isCustomPeriod(): bool
    {
        return $this->unit === 'custom_period';
    }

    public function unitLabel(): string
    {
        if ($this->unit === 'custom_period') {
            return '自訂期別';
        }

        if ($this->unit !== 'session_block') {
            return '月';
        }

        $size = (int) ($this->session_block_size ?? 3);

        return $size === 3 ? '季' : $size.'個月';
    }

    /** 列表顯示用：按期顯示一期總額（月價 × 期數） */
    public function periodAmount(int $monthlyPrice): int
    {
        if ($this->unit !== 'session_block') {
            return $monthlyPrice;
        }

        return $monthlyPrice * max(1, (int) ($this->session_block_size ?? 3));
    }

    public function listPriceLabel(): string
    {
        return number_format($this->periodAmount((int) $this->list_price)).'／'.$this->unitLabel();
    }

    public function quarterLabel(): string
    {
        if ($this->isCustomPeriod()) {
            $periods = $this->relationLoaded('periods') ? $this->periods : $this->periods()->get();
            if ($periods->isEmpty()) {
                return '—';
            }

            return $periods
                ->map(fn (FeePlanPeriod $period): string => $period->label().' $'.number_format($period->total_price))
                ->implode('；');
        }

        $quarterPrice = $this->quarter_single_price ?? $this->quarter_price;

        if ($quarterPrice !== null) {
            return number_format($this->periodAmount((int) $quarterPrice)).'／'.$this->unitLabel();
        }

        return '—';
    }

    public function materialLabel(): string
    {
        $price = number_format($this->material_fee);
        $unit = match ($this->material_unit) {
            'class_day' => '日',
            default => '年（半年收一次）',
        };

        return "{$price}／{$unit}";
    }
}
