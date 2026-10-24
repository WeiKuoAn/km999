<?php

namespace App\Support;

use App\Models\CourseBundle;
use App\Models\FeePlan;
use App\Models\FeePlanPeriod;
use Illuminate\Support\Facades\DB;

/**
 * 將按月／按季／其他（session_block）收費設定轉成自訂期別。
 * 期別自 7 月起切分；單科一期總價＝月優惠價 × 期月數；雙科價取自組合優惠（若有）。
 */
final class FeePlanCustomPeriodConverter
{
    /**
     * @return array{converted:int, skipped:int, details:list<string>}
     */
    public static function convertAll(bool $force = false): array
    {
        $converted = 0;
        $skipped = 0;
        $details = [];

        $plans = FeePlan::query()
            ->with(['courses:id', 'periods.coursePrices'])
            ->orderBy('id')
            ->get();

        foreach ($plans as $plan) {
            $result = self::convertPlan($plan, $force);
            if ($result['converted']) {
                $converted++;
                $details[] = $result['message'];
            } else {
                $skipped++;
                $details[] = $result['message'];
            }
        }

        return compact('converted', 'skipped', 'details');
    }

    /**
     * @return array{converted:bool, message:string}
     */
    public static function convertPlan(FeePlan $plan, bool $force = false): array
    {
        $plan->loadMissing(['courses:id', 'periods.coursePrices']);

        if ($plan->unit === 'custom_period' && $plan->periods->isNotEmpty() && ! $force) {
            return [
                'converted' => false,
                'message' => "#{$plan->id} {$plan->group_name}：已是自訂期別，略過",
            ];
        }

        $size = match ($plan->unit) {
            'custom_period' => max(1, (int) ($plan->session_block_size ?? 3)),
            'session_block' => max(1, (int) ($plan->session_block_size ?? 3)),
            'month' => 1,
            default => 1,
        };

        // 已是自訂但強制重轉、且沒有 block 資訊時：用既有期別長度推估，否則預設季
        if ($plan->unit === 'custom_period' && $force) {
            $first = $plan->periods->first();
            if ($first !== null) {
                $size = CustomBillingPeriod::monthCount((int) $first->start_month, (int) $first->end_month);
            } else {
                $size = 3;
            }
        }

        $blocks = self::blocksFromJuly($size);
        $singleMonthly = (int) ($plan->quarter_single_price ?? $plan->quarter_price ?? $plan->list_price);
        if ($singleMonthly < 1) {
            $singleMonthly = max(1, (int) $plan->list_price);
        }
        $doubleMonthlyByCourse = self::doubleMonthlyByCourse($plan);

        DB::transaction(function () use ($plan, $blocks, $singleMonthly, $doubleMonthlyByCourse): void {
            $plan->periods()->delete();

            foreach (array_values($blocks) as $i => $block) {
                [$start, $end, $months] = $block;
                $periodTotal = $singleMonthly * $months;

                $period = FeePlanPeriod::query()->create([
                    'fee_plan_id' => $plan->id,
                    'start_month' => $start,
                    'end_month' => $end,
                    'total_price' => $periodTotal,
                    'double_price' => null,
                    'triple_price' => null,
                    'sort_order' => $i,
                ]);

                foreach ($plan->courses as $course) {
                    $courseId = (int) $course->id;
                    $doubleMonthly = $doubleMonthlyByCourse[$courseId] ?? null;
                    $period->coursePrices()->create([
                        'course_id' => $courseId,
                        'total_price' => $periodTotal,
                        'double_price' => $doubleMonthly !== null
                            ? $doubleMonthly * $months
                            : null,
                        'triple_price' => null,
                    ]);
                }
            }

            $plan->update([
                'unit' => 'custom_period',
                'session_block_size' => null,
            ]);
        });

        $label = implode('、', array_map(
            fn (array $b): string => $b[0] === $b[1] ? $b[0].'月' : $b[0].'–'.$b[1].'月',
            $blocks
        ));

        return [
            'converted' => true,
            'message' => "#{$plan->id} {$plan->group_name}：→ 自訂期別（{$label}），單科月價 {$singleMonthly}",
        ];
    }

    /**
     * 自 7 月起，每 $size 個月切一期，涵蓋 12 個月。
     *
     * @return list<array{0:int,1:int,2:int}> [start, end, monthCount]
     */
    public static function blocksFromJuly(int $size): array
    {
        $size = max(1, min(12, $size));
        $blocks = [];
        $start = 7;
        $covered = 0;

        while ($covered < 12) {
            $len = min($size, 12 - $covered);
            $end = $start;
            for ($i = 1; $i < $len; $i++) {
                $end = ($end % 12) + 1;
            }
            $blocks[] = [$start, $end, $len];
            $start = ($end % 12) + 1;
            $covered += $len;
        }

        return $blocks;
    }

    /**
     * @return array<int, int> course_id => 雙科每月價
     */
    private static function doubleMonthlyByCourse(FeePlan $plan): array
    {
        $courseIds = $plan->courses->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($courseIds === []) {
            return [];
        }

        $bundles = CourseBundle::query()
            ->with('courses:id')
            ->where('is_active', true)
            ->where('min_courses', '<=', 2)
            ->where(function ($query) use ($plan): void {
                $query->whereNull('grade_level_id');
                if ($plan->grade_level_id !== null) {
                    $query->orWhere('grade_level_id', $plan->grade_level_id);
                }
            })
            ->where(function ($query) use ($plan): void {
                $query->whereNull('academic_year_id');
                if ($plan->academic_year_id !== null) {
                    $query->orWhere('academic_year_id', $plan->academic_year_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            // 同年級組合優先於「全年級」組合，避免誤套科英／高數價
            ->sortBy(fn (CourseBundle $bundle): array => [
                $bundle->grade_level_id !== null
                    && (int) $bundle->grade_level_id === (int) $plan->grade_level_id
                        ? 0
                        : 1,
                (int) $bundle->sort_order,
                (int) $bundle->id,
            ])
            ->values();

        $out = [];
        foreach ($bundles as $bundle) {
            if ((int) $bundle->min_courses > 2) {
                continue;
            }
            $bundleCourseIds = $bundle->courses->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $overlap = array_values(array_intersect($courseIds, $bundleCourseIds));
            if ($overlap === []) {
                continue;
            }
            // 全年級組合：至少要重疊到「雙科」門數，才套用到本收費設定
            if ($bundle->grade_level_id === null && count($overlap) < max(2, (int) $bundle->min_courses)) {
                continue;
            }
            foreach ($bundle->courses as $course) {
                $cid = (int) $course->id;
                if (! in_array($cid, $courseIds, true)) {
                    continue;
                }
                $price = $course->pivot->price ?? null;
                if ($price === null || (int) $price < 1) {
                    continue;
                }
                if (! isset($out[$cid])) {
                    $out[$cid] = (int) $price;
                }
            }
        }

        return $out;
    }
}
