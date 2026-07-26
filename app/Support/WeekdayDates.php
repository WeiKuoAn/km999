<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * 依 ISO 星期（1=週一…7=週日）枚舉日期區間內的上課日。
 */
final class WeekdayDates
{
    /**
     * @param  array<int, int|string>  $weekdays
     * @return list<string> Y-m-d
     */
    public static function inRange(Carbon $start, Carbon $end, array $weekdays): array
    {
        $set = array_values(array_unique(array_map('intval', $weekdays)));
        $set = array_values(array_filter($set, fn (int $d): bool => $d >= 1 && $d <= 7));
        if ($set === []) {
            return [];
        }

        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        if ($cursor->greaterThan($last)) {
            return [];
        }

        $out = [];
        while ($cursor->lte($last)) {
            if (in_array((int) $cursor->dayOfWeekIso, $set, true)) {
                $out[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  array<int, int|string>  $weekdays
     * @return list<string> Y-m-d
     */
    public static function inMonth(int $year, int $month, array $weekdays, ?Carbon $fromDate = null): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();

        if ($fromDate !== null) {
            $from = $fromDate->copy()->startOfDay();
            if ($from->greaterThan($end)) {
                return [];
            }
            if ($from->greaterThan($start)) {
                $start = $from;
            }
        }

        return self::inRange($start, $end, $weekdays);
    }

    /**
     * @param  array<int, mixed>|null  $schedules
     * @return list<array{level:?string, weekday:int, start_time:?string, end_time:?string}>
     */
    public static function normalizeSchedules(?array $schedules): array
    {
        if ($schedules === null || $schedules === []) {
            return [];
        }

        $out = [];
        foreach ($schedules as $row) {
            if (! is_array($row)) {
                continue;
            }
            $weekday = (int) ($row['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                continue;
            }
            $level = $row['level'] ?? null;
            if ($level !== null) {
                $level = trim((string) $level);
                if ($level === '') {
                    $level = null;
                }
            }
            $start = self::normalizeHm($row['start_time'] ?? null);
            $end = self::normalizeHm($row['end_time'] ?? null);
            $out[] = [
                'level' => $level,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        usort(
            $out,
            fn (array $a, array $b): int => strcmp((string) ($a['level'] ?? ''), (string) ($b['level'] ?? ''))
                ?: $a['weekday'] <=> $b['weekday']
                ?: strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''))
        );

        return $out;
    }

    /**
     * 依年級篩選時段；無年級列（level=null）視為適用全部。
     *
     * @param  list<array{level:?string, weekday:int, start_time:?string, end_time:?string}>  $schedules
     * @return list<array{level:?string, weekday:int, start_time:?string, end_time:?string}>
     */
    public static function schedulesForLevel(array $schedules, ?string $level): array
    {
        $level = $level !== null ? trim($level) : null;
        if ($level === '') {
            $level = null;
        }

        $matched = array_values(array_filter(
            $schedules,
            function (array $row) use ($level): bool {
                $rowLevel = $row['level'] ?? null;
                if ($rowLevel === null || $rowLevel === '') {
                    return true;
                }
                if ($level === null) {
                    return false;
                }

                return $rowLevel === $level;
            }
        ));

        if ($matched !== []) {
            return $matched;
        }

        // 舊資料：全部沒有 level 時 normalize 已帶 null，上面會全中；
        // 若指定年級卻無專屬列，再退回「完全無 level 欄」的整份（相容）。
        return array_values(array_filter(
            $schedules,
            fn (array $row): bool => ($row['level'] ?? null) === null || ($row['level'] ?? '') === ''
        ));
    }

    /**
     * @param  list<array{weekday:int, start_time:?string, end_time:?string}>  $schedules
     * @return list<int>
     */
    public static function weekdaysFromSchedules(array $schedules): array
    {
        return self::normalize(array_column($schedules, 'weekday'));
    }

    private static function normalizeHm(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);
        if (preg_match('/^\d{2}:\d{2}/', $s) === 1) {
            return substr($s, 0, 5);
        }

        return null;
    }

    /**
     * @param  array<int, int|string>|null  $weekdays
     * @return list<int>
     */
    public static function normalize(?array $weekdays): array
    {
        if ($weekdays === null || $weekdays === []) {
            return [];
        }

        $set = array_values(array_unique(array_map('intval', $weekdays)));
        sort($set);

        return array_values(array_filter($set, fn (int $d): bool => $d >= 1 && $d <= 7));
    }
}
