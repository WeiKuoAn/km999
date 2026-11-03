<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeDiscount extends Model
{
    public const TYPE_AMOUNT = 'amount';

    public const TYPE_PERCENT = 'percent';

    protected $fillable = [
        'name',
        'grade_level_id',
        'type',
        'value',
        'is_active',
        'sort_order',
        'starts_on',
        'ends_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailableOn(Builder $query, ?string $ymd = null): Builder
    {
        $date = Carbon::parse($ymd ?? Carbon::today()->toDateString())->toDateString();

        return $query
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('starts_on')->orWhereDate('starts_on', '<=', $date);
            })
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date);
            });
    }

    /** 適用指定年級：未設年級＝全年級；設了則僅該年級 */
    public function scopeForGrade(Builder $query, ?int $gradeLevelId): Builder
    {
        return $query->where(function (Builder $builder) use ($gradeLevelId): void {
            $builder->whereNull('grade_level_id');
            if ($gradeLevelId !== null) {
                $builder->orWhere('grade_level_id', $gradeLevelId);
            }
        });
    }

    public function appliesToGrade(?int $gradeLevelId): bool
    {
        if ($this->grade_level_id === null) {
            return true;
        }

        return $gradeLevelId !== null && (int) $this->grade_level_id === (int) $gradeLevelId;
    }

    public function label(): string
    {
        if ($this->type === self::TYPE_PERCENT) {
            return sprintf('%s（%d%%）', $this->name, $this->value);
        }

        return sprintf('%s（折讓 %s）', $this->name, number_format($this->value));
    }

    /** 依學費＋教材小計計算應折讓金額（整元、不超過小計） */
    public function computeAllowance(int $subtotal): int
    {
        $subtotal = max(0, $subtotal);
        if ($subtotal <= 0 || $this->value <= 0) {
            return 0;
        }

        if ($this->type === self::TYPE_PERCENT) {
            $pct = min(100, max(0, $this->value));

            return (int) min($subtotal, round($subtotal * $pct / 100));
        }

        return (int) min($subtotal, $this->value);
    }
}
