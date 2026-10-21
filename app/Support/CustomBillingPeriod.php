<?php

namespace App\Support;

/**
 * 自訂期別（每年固定循環）：如 7–8、9–12、1–3、4–6 月，
 * 每期填一期總價；期中加入依剩餘月份折算。
 */
final class CustomBillingPeriod
{
    /**
     * 期別包含的月份（依序，可跨年如 11–2 → [11,12,1,2]）。
     *
     * @return list<int>
     */
    public static function monthNumbers(int $startMonth, int $endMonth): array
    {
        $months = [];
        $month = $startMonth;
        for ($i = 0; $i < 12; $i++) {
            $months[] = $month;
            if ($month === $endMonth) {
                break;
            }
            $month = $month % 12 + 1;
        }

        return $months;
    }

    public static function monthCount(int $startMonth, int $endMonth): int
    {
        return count(self::monthNumbers($startMonth, $endMonth));
    }

    /**
     * @param  list<array{start_month:int|string, end_month:int|string, total_price:int|string}>  $periods
     * @return array{
     *   start_month:int,
     *   end_month:int,
     *   total_price:int,
     *   month_count:int,
     *   start_year:int,
     *   end_year:int,
     *   start_key:int,
     *   end_key:int,
     *   label:string
     * }|null
     */
    public static function periodForMonth(array $periods, int $year, int $month): ?array
    {
        foreach ($periods as $period) {
            $start = (int) ($period['start_month'] ?? 0);
            $end = (int) ($period['end_month'] ?? 0);
            if ($start < 1 || $start > 12 || $end < 1 || $end > 12) {
                continue;
            }
            $numbers = self::monthNumbers($start, $end);
            if (! in_array($month, $numbers, true)) {
                continue;
            }

            $crossesYear = $start > $end;
            $startYear = $crossesYear && $month < $start ? $year - 1 : $year;
            $endYear = $crossesYear ? $startYear + 1 : $startYear;

            return [
                'start_month' => $start,
                'end_month' => $end,
                'total_price' => (int) ($period['total_price'] ?? 0),
                'month_count' => count($numbers),
                'start_year' => $startYear,
                'end_year' => $endYear,
                'start_key' => $startYear * 12 + $start,
                'end_key' => $endYear * 12 + $end,
                'label' => $start === $end ? $start.'月' : $start.'–'.$end.'月',
            ];
        }

        return null;
    }

    /**
     * 從 $fromKey（year*12+month）起到該期結束的月份 key 清單（"Y-n"）。
     * 期中加入時即為剩餘月份。
     *
     * @param  list<array{start_month:int|string, end_month:int|string, total_price:int|string}>  $periods
     * @return list<string>
     */
    public static function remainingMonthLabels(array $periods, int $fromKey): array
    {
        $year = intdiv($fromKey - 1, 12);
        $month = (($fromKey - 1) % 12) + 1;
        $period = self::periodForMonth($periods, $year, $month);
        if ($period === null) {
            return [];
        }

        $labels = [];
        for ($key = $fromKey; $key <= $period['end_key']; $key++) {
            $labels[] = intdiv($key - 1, 12).'-'.((($key - 1) % 12) + 1);
        }

        return $labels;
    }

    /**
     * 該期完整的月份 key 清單（以期別起始年月起算）。
     *
     * @param  array{start_key:int, end_key:int}  $period
     * @return list<string>
     */
    public static function monthLabelsForPeriod(array $period): array
    {
        $labels = [];
        for ($key = $period['start_key']; $key <= $period['end_key']; $key++) {
            $labels[] = intdiv($key - 1, 12).'-'.((($key - 1) % 12) + 1);
        }

        return $labels;
    }
}
