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
            'session_block' => ($this->session_block_size ?: 4).'堂',
            default => '月',
        };
    }

    public function listPriceLabel(): string
    {
        $price = number_format($this->list_price);

        return match ($this->unit) {
            'session_block' => $price.'／'.$this->unitLabel(),
            default => $price.'／月',
        };
    }

    public function quarterLabel(): string
    {
        if ($this->quarter_single_price !== null || $this->quarter_double_price !== null) {
            $single = $this->quarter_single_price !== null
                ? number_format($this->quarter_single_price)
                : '—';
            $double = $this->quarter_double_price !== null
                ? number_format($this->quarter_double_price)
                : '—';

            return "單 {$single}／雙 {$double}";
        }

        if ($this->quarter_price !== null) {
            $price = number_format($this->quarter_price);

            return match ($this->unit) {
                'session_block' => $price.'／'.$this->unitLabel(),
                default => $price.'／月',
            };
        }

        return '—';
    }

    public function materialLabel(): string
    {
        $price = number_format($this->material_fee);
        $unit = match ($this->material_unit) {
            'subject' => '科',
            'class_day' => '日',
            default => '學期',
        };

        return "{$price}／{$unit}";
    }
}
