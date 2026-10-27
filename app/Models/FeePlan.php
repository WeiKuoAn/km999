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
        $lines = $this->periodSummaryLines();
        if ($lines === []) {
            $quarterPrice = $this->quarter_single_price ?? $this->quarter_price;

            return $quarterPrice !== null
                ? number_format($this->periodAmount((int) $quarterPrice)).'／'.$this->unitLabel()
                : '—';
        }

        return collect($lines)
            ->map(fn (array $line): string => $line['label'].' '.$line['monthly'].'／月（×'.$line['months'].'）')
            ->implode('；');
    }

    /**
     * 列表用：各期單科月價摘要（一行一期）。
     *
     * @return list<array{label:string, monthly:string, months:int}>
     */
    public function periodSummaryLines(): array
    {
        if (! $this->isCustomPeriod()) {
            return [];
        }

        $periods = $this->relationLoaded('periods') ? $this->periods : $this->periods()->get();
        if ($periods->isEmpty()) {
            return [];
        }

        return $periods
            ->map(function (FeePlanPeriod $period): array {
                $months = max(1, $period->monthCount());
                if ($period->relationLoaded('coursePrices') && $period->coursePrices->isNotEmpty()) {
                    $amounts = $period->coursePrices
                        ->pluck('total_price')
                        ->map(fn ($price): int => (int) $price)
                        ->unique()
                        ->sort()
                        ->values();
                    $monthly = $amounts->count() === 1
                        ? '$'.number_format((int) $amounts[0])
                        : '$'.number_format((int) $amounts->first()).'～'.number_format((int) $amounts->last());
                } else {
                    $monthly = '$'.number_format((int) $period->total_price);
                }

                return [
                    'label' => $period->label(),
                    'monthly' => $monthly,
                    'months' => $months,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 列表用：雙科／多科月價摘要（取各期各科有填的價）。
     *
     * @return list<array{tier:string, monthly:string}>
     */
    public function tierSummaryLines(): array
    {
        if (! $this->isCustomPeriod()) {
            return [];
        }

        $periods = $this->relationLoaded('periods') ? $this->periods : $this->periods()->with('coursePrices')->get();
        $doubles = [];
        $triples = [];

        foreach ($periods as $period) {
            if (! $period->relationLoaded('coursePrices')) {
                continue;
            }
            foreach ($period->coursePrices as $row) {
                if ($row->double_price !== null && (int) $row->double_price > 0) {
                    $doubles[] = (int) $row->double_price;
                }
                if ($row->triple_price !== null && (int) $row->triple_price > 0) {
                    $triples[] = (int) $row->triple_price;
                }
            }
        }

        $formatRange = function (array $amounts): string {
            $unique = collect($amounts)->unique()->sort()->values();
            if ($unique->isEmpty()) {
                return '';
            }
            if ($unique->count() === 1) {
                return '$'.number_format((int) $unique[0]);
            }

            return '$'.number_format((int) $unique->first()).'～'.number_format((int) $unique->last());
        };

        $lines = [];
        if ($doubles !== []) {
            $lines[] = ['tier' => '雙科', 'monthly' => $formatRange($doubles)];
        }
        if ($triples !== []) {
            $lines[] = ['tier' => '多科', 'monthly' => $formatRange($triples)];
        }

        return $lines;
    }

    public function materialLabel(): string
    {
        $price = number_format($this->material_fee);
        $unit = match ($this->material_unit) {
            'class_day' => '日',
            default => '年（半年）',
        };

        return "{$price}／{$unit}";
    }
}
