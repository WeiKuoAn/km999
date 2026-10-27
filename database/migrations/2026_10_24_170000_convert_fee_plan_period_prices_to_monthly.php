<?php

use App\Support\CustomBillingPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 自訂期別價格語意：一期總價 → 每月單價（一期應收＝月價×月數）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_plan_periods')) {
            return;
        }

        $periods = DB::table('fee_plan_periods')->get([
            'id', 'start_month', 'end_month', 'total_price', 'double_price', 'triple_price',
        ]);

        foreach ($periods as $period) {
            $months = max(1, CustomBillingPeriod::monthCount(
                (int) $period->start_month,
                (int) $period->end_month
            ));
            if ($months === 1) {
                continue;
            }

            DB::table('fee_plan_periods')->where('id', $period->id)->update([
                'total_price' => (int) round(((int) $period->total_price) / $months),
                'double_price' => $period->double_price === null
                    ? null
                    : (int) round(((int) $period->double_price) / $months),
                'triple_price' => $period->triple_price === null
                    ? null
                    : (int) round(((int) $period->triple_price) / $months),
                'updated_at' => now(),
            ]);

            if (! Schema::hasTable('fee_plan_period_course_prices')) {
                continue;
            }

            $rows = DB::table('fee_plan_period_course_prices')
                ->where('fee_plan_period_id', $period->id)
                ->get(['id', 'total_price', 'double_price', 'triple_price']);

            foreach ($rows as $row) {
                DB::table('fee_plan_period_course_prices')->where('id', $row->id)->update([
                    'total_price' => (int) round(((int) $row->total_price) / $months),
                    'double_price' => $row->double_price === null
                        ? null
                        : (int) round(((int) $row->double_price) / $months),
                    'triple_price' => $row->triple_price === null
                        ? null
                        : (int) round(((int) $row->triple_price) / $months),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fee_plan_periods')) {
            return;
        }

        $periods = DB::table('fee_plan_periods')->get([
            'id', 'start_month', 'end_month', 'total_price', 'double_price', 'triple_price',
        ]);

        foreach ($periods as $period) {
            $months = max(1, CustomBillingPeriod::monthCount(
                (int) $period->start_month,
                (int) $period->end_month
            ));
            if ($months === 1) {
                continue;
            }

            DB::table('fee_plan_periods')->where('id', $period->id)->update([
                'total_price' => ((int) $period->total_price) * $months,
                'double_price' => $period->double_price === null
                    ? null
                    : ((int) $period->double_price) * $months,
                'triple_price' => $period->triple_price === null
                    ? null
                    : ((int) $period->triple_price) * $months,
                'updated_at' => now(),
            ]);

            if (! Schema::hasTable('fee_plan_period_course_prices')) {
                continue;
            }

            $rows = DB::table('fee_plan_period_course_prices')
                ->where('fee_plan_period_id', $period->id)
                ->get(['id', 'total_price', 'double_price', 'triple_price']);

            foreach ($rows as $row) {
                DB::table('fee_plan_period_course_prices')->where('id', $row->id)->update([
                    'total_price' => ((int) $row->total_price) * $months,
                    'double_price' => $row->double_price === null
                        ? null
                        : ((int) $row->double_price) * $months,
                    'triple_price' => $row->triple_price === null
                        ? null
                        : ((int) $row->triple_price) * $months,
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
