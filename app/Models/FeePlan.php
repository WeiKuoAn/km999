<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function unitLabel(): string
    {
        return match ($this->unit) {
            'session_block' => '期',
            default => '月',
        };
    }

    public function listPriceLabel(): string
    {
        return number_format($this->list_price).'／'.$this->unitLabel();
    }

    public function quarterLabel(): string
    {
        $quarterPrice = $this->quarter_single_price ?? $this->quarter_price;

        if ($quarterPrice !== null) {
            $price = number_format($quarterPrice);

            return match ($this->unit) {
                'session_block' => $price.'／季',
                default => $price.'／月',
            };
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
