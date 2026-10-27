<?php

/**
 * 本機：把國文／生物相關收費設定改為 2 個月＋4 個月交錯期別。
 * 7–8（2）、9–12（4）、1–2（2）、3–6（4）；月價沿用原單科價。
 *
 * 用法：php scripts/update-chinese-bio-periods.php
 */

use App\Models\FeePlan;
use App\Models\FeePlanPeriodCoursePrice;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$targetPlanIds = [2, 5]; // 國一國／生、國二國文
$newPeriods = [
    ['start_month' => 7, 'end_month' => 8],
    ['start_month' => 9, 'end_month' => 12],
    ['start_month' => 1, 'end_month' => 2],
    ['start_month' => 3, 'end_month' => 6],
];

DB::transaction(function () use ($targetPlanIds, $newPeriods): void {
    foreach ($targetPlanIds as $planId) {
        $plan = FeePlan::query()->with(['courses', 'periods.coursePrices'])->find($planId);
        if ($plan === null) {
            echo "略過：找不到 plan #{$planId}\n";

            continue;
        }

        // 各科沿用第一期既有月價（沒有則 1800）
        $defaults = [];
        $first = $plan->periods->first();
        foreach ($plan->courses as $course) {
            $cid = (int) $course->id;
            $row = $first?->coursePrices->firstWhere('course_id', $cid);
            $defaults[$cid] = [
                'total_price' => (int) ($row?->total_price ?: 1800),
                'double_price' => $row?->double_price !== null ? (int) $row->double_price : null,
                'triple_price' => $row?->triple_price !== null ? (int) $row->triple_price : null,
            ];
        }

        $plan->periods()->delete();

        foreach (array_values($newPeriods) as $i => $period) {
            $created = $plan->periods()->create([
                'start_month' => $period['start_month'],
                'end_month' => $period['end_month'],
                'total_price' => min(array_column($defaults, 'total_price') ?: [1800]),
                'double_price' => null,
                'triple_price' => null,
                'sort_order' => $i,
            ]);

            foreach ($defaults as $courseId => $prices) {
                FeePlanPeriodCoursePrice::query()->create([
                    'fee_plan_period_id' => $created->id,
                    'course_id' => $courseId,
                    'total_price' => $prices['total_price'],
                    'double_price' => $prices['double_price'],
                    'triple_price' => $prices['triple_price'],
                ]);
            }
        }

        $names = $plan->courses->pluck('name')->implode('、');
        echo "已更新 #{$plan->id} {$plan->group_name}（{$names}）\n";
        $plan->load('periods');
        foreach ($plan->periods as $p) {
            echo "  {$p->label()}（{$p->monthCount()} 個月）\n";
        }
    }
});

echo "完成。\n";
