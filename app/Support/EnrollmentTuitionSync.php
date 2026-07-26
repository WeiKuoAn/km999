<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\EnrollmentTuition;
use Illuminate\Support\Collection;

final class EnrollmentTuitionSync
{
    /**
     * @param  array<int, array{duration_hours: float|int|string, tuition_amount: int|string}>  $rows
     */
    public static function sync(Enrollment $enrollment, array $rows): void
    {
        $normalized = collect($rows)
            ->map(function (array $row): array {
                return [
                    'duration_hours' => CourseTuition::normalizeDuration($row['duration_hours']),
                    'tuition_amount' => (int) $row['tuition_amount'],
                ];
            })
            ->unique('duration_hours')
            ->sortBy('duration_hours')
            ->values();

        if ($normalized->isEmpty()) {
            $enrollment->tuitionRates()->delete();

            return;
        }

        $keptDurations = [];
        foreach ($normalized as $row) {
            $keptDurations[] = $row['duration_hours'];
            EnrollmentTuition::query()->updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'duration_hours' => $row['duration_hours'],
                ],
                [
                    'tuition_amount' => $row['tuition_amount'],
                ]
            );
        }

        $enrollment->tuitionRates()
            ->whereNotIn('duration_hours', $keptDurations)
            ->delete();

        $enrollment->update([
            'tuition_amount' => (int) $normalized->first()['tuition_amount'],
        ]);
    }

    /**
     * 依課程價目與學段建立各時數學費（新報名或學段變更）。
     *
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     */
    public static function seedFromCoursePrices(Enrollment $enrollment, Collection $prices, ?string $segment): void
    {
        $durations = CourseTuition::distinctDurations($prices);
        if (count($durations) <= 1) {
            $enrollment->tuitionRates()->delete();
            $enrollment->update([
                'tuition_amount' => CourseTuition::fromSchoolSegment($prices, $segment),
            ]);

            return;
        }

        $rows = [];
        foreach ($durations as $duration) {
            $rows[] = [
                'duration_hours' => $duration,
                'tuition_amount' => CourseTuition::fromSchoolSegmentAndDuration($prices, $segment, $duration),
            ];
        }

        self::sync($enrollment, $rows);
    }

    /**
     * @param  Collection<int, EnrollmentTuition>  $stored
     * @param  Collection<int, object{level: ?string, duration_hours?: float|int|string|null, tuition: int|string, sort_order?: int}>  $prices
     * @return array<int, array{duration_hours: float, tuition_amount: int}>
     */
    public static function displayRows(Collection $stored, Collection $prices, ?string $segment): array
    {
        if ($stored->isNotEmpty()) {
            return $stored
                ->sortBy('duration_hours')
                ->map(fn (EnrollmentTuition $row): array => [
                    'duration_hours' => CourseTuition::normalizeDuration($row->duration_hours),
                    'tuition_amount' => (int) $row->tuition_amount,
                ])
                ->values()
                ->all();
        }

        $durations = CourseTuition::distinctDurations($prices);
        if ($durations === []) {
            return [];
        }

        return collect($durations)
            ->map(fn (float $duration): array => [
                'duration_hours' => $duration,
                'tuition_amount' => CourseTuition::fromSchoolSegmentAndDuration($prices, $segment, $duration),
            ])
            ->values()
            ->all();
    }
}
