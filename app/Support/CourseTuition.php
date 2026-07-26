<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class CourseTuition
{
    /** @var array<float> 常用時數建議（非限制） */
    public const SUGGESTED_DURATIONS = [1.0, 1.5, 2.0];

    /**
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     */
    public static function defaultFromPrices(Collection $prices): int
    {
        $match = self::sortedPrices($prices)->first();
        if ($match === null) {
            return 0;
        }

        return (int) $match->tuition;
    }

    /**
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     */
    public static function fromSchoolSegment(Collection $prices, ?string $segment): int
    {
        if ($prices->isEmpty()) {
            return 0;
        }

        $filtered = self::pricesForSegment($prices, $segment);
        $match = self::sortedPrices($filtered)->first();
        if ($match === null) {
            return self::defaultFromPrices($prices);
        }

        return (int) $match->tuition;
    }

    /**
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     */
    public static function fromSchoolSegmentAndDuration(Collection $prices, ?string $segment, ?float $durationHours): int
    {
        if ($prices->isEmpty()) {
            return 0;
        }

        if ($durationHours !== null) {
            $duration = self::normalizeDuration($durationHours);
            $forDuration = $prices->filter(
                fn ($p) => self::normalizeDuration($p->duration_hours ?? 1.0) === $duration
            );
            if ($forDuration->isNotEmpty()) {
                $match = self::pricesForSegment($forDuration, $segment)->first()
                    ?? self::sortedPrices($forDuration)->first();
                if ($match !== null) {
                    return (int) $match->tuition;
                }
            }
        }

        return self::fromSchoolSegment($prices, $segment);
    }

    /**
     * @param  Collection<int, object{duration_hours?: float|int|string|null}>  $prices
     * @return array<float>
     */
    public static function distinctDurations(Collection $prices): array
    {
        return $prices
            ->map(fn ($p) => self::normalizeDuration($p->duration_hours ?? 1.0))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object{duration_hours?: float|int|string|null}>  $prices
     */
    public static function hasMultipleDurations(Collection $prices): bool
    {
        return count(self::distinctDurations($prices)) > 1;
    }

    public static function durationLabel(?float $hours): string
    {
        if ($hours === null) {
            return '';
        }

        $normalized = self::normalizeDuration($hours);
        $formatted = rtrim(rtrim(number_format($normalized, 1, '.', ''), '0'), '.');

        return $formatted.'hr';
    }

    public static function normalizeDuration(float|int|string|null $value): float
    {
        return round((float) ($value ?? 1.0), 1);
    }

    /**
     * 課程列表／下拉：多種時數時依時數分行；單一時數不顯示 hr。
     *
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string}>  $prices
     * @return array<int, string>
     */
    public static function formatPriceDisplayLines(Collection $prices): array
    {
        if ($prices->isEmpty()) {
            return [];
        }

        $durations = self::distinctDurations($prices);
        $multi = count($durations) > 1;

        if (! $multi) {
            $parts = $prices->map(function ($p) {
                $level = ($p->level === null || $p->level === '') ? '不分' : $p->level;

                return "{$level} {$p->tuition}";
            })->all();

            return [implode('、', $parts)];
        }

        $lines = [];
        foreach ($durations as $d) {
            $tiers = $prices
                ->filter(fn ($p) => self::normalizeDuration($p->duration_hours ?? 1.0) === $d)
                ->map(function ($p) {
                    $level = ($p->level === null || $p->level === '') ? '不分' : $p->level;

                    return "{$level} {$p->tuition}";
                })
                ->all();

            $lines[] = self::durationLabel($d).'：'.implode('、', $tiers);
        }

        return $lines;
    }

    /**
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string}>  $prices
     */
    public static function formatPriceTiersSummary(Collection $prices): string
    {
        return implode('；', self::formatPriceDisplayLines($prices));
    }

    /**
     * 單堂計費金額：優先使用報名自訂時數學費，其次課程價目，最後 legacy 單一學費。
     *
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     * @param  Collection<int, EnrollmentTuition>|null  $enrollmentTuitionRates
     */
    public static function sessionAmount(
        Collection $prices,
        ?string $segment,
        ?float $durationHours,
        ?int $fallbackTuition,
        ?Collection $enrollmentTuitionRates = null,
    ): int {
        if ($durationHours !== null && $enrollmentTuitionRates !== null && $enrollmentTuitionRates->isNotEmpty()) {
            $duration = self::normalizeDuration($durationHours);
            foreach ($enrollmentTuitionRates as $rate) {
                if (self::normalizeDuration($rate->duration_hours) === $duration) {
                    return (int) $rate->tuition_amount;
                }
            }
        }

        if ($durationHours !== null) {
            return self::fromSchoolSegmentAndDuration($prices, $segment, $durationHours);
        }

        return (int) ($fallbackTuition ?? 0);
    }

    /**
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     * @return Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>
     */
    private static function sortedPrices(Collection $prices): Collection
    {
        return $prices->sortBy([
            fn ($p) => self::normalizeDuration($p->duration_hours ?? 1.0),
            fn ($p) => $p->sort_order ?? 0,
            fn ($p) => $p->level ?? '',
        ])->values();
    }

    /**
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     * @return Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>
     */
    private static function pricesForSegment(Collection $prices, ?string $segment): Collection
    {
        $normalized = is_string($segment) ? trim($segment) : '';
        if ($normalized !== '') {
            $match = $prices->filter(function ($p) use ($normalized) {
                $level = $p->level;

                return $level !== null && $level !== '' && $level === $normalized;
            });
            if ($match->isNotEmpty()) {
                return self::sortedPrices($match);
            }
        }

        $none = $prices->filter(fn ($p) => $p->level === null || $p->level === '');
        if ($none->isNotEmpty()) {
            return self::sortedPrices($none);
        }

        return self::sortedPrices($prices);
    }
}
