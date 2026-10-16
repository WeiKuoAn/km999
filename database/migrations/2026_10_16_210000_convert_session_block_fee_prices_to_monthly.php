<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 按期（session_block）舊資料存的是「一期總額」；
 * 改為與表單一致：一律存「一個月」單價，收款時再 × 期數。
 */
return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('fee_plans')
            ->where('unit', 'session_block')
            ->whereNotNull('session_block_size')
            ->where('session_block_size', '>', 1)
            ->get(['id', 'session_block_size', 'list_price', 'quarter_price', 'quarter_single_price', 'quarter_double_price']);

        foreach ($plans as $plan) {
            $size = (int) $plan->session_block_size;
            if ($size <= 1) {
                continue;
            }

            DB::table('fee_plans')->where('id', $plan->id)->update([
                'list_price' => (int) round(((int) $plan->list_price) / $size),
                'quarter_price' => $plan->quarter_price === null
                    ? null
                    : (int) round(((int) $plan->quarter_price) / $size),
                'quarter_single_price' => $plan->quarter_single_price === null
                    ? null
                    : (int) round(((int) $plan->quarter_single_price) / $size),
                'quarter_double_price' => $plan->quarter_double_price === null
                    ? null
                    : (int) round(((int) $plan->quarter_double_price) / $size),
            ]);
        }
    }

    public function down(): void
    {
        $plans = DB::table('fee_plans')
            ->where('unit', 'session_block')
            ->whereNotNull('session_block_size')
            ->where('session_block_size', '>', 1)
            ->get(['id', 'session_block_size', 'list_price', 'quarter_price', 'quarter_single_price', 'quarter_double_price']);

        foreach ($plans as $plan) {
            $size = (int) $plan->session_block_size;
            if ($size <= 1) {
                continue;
            }

            DB::table('fee_plans')->where('id', $plan->id)->update([
                'list_price' => ((int) $plan->list_price) * $size,
                'quarter_price' => $plan->quarter_price === null
                    ? null
                    : ((int) $plan->quarter_price) * $size,
                'quarter_single_price' => $plan->quarter_single_price === null
                    ? null
                    : ((int) $plan->quarter_single_price) * $size,
                'quarter_double_price' => $plan->quarter_double_price === null
                    ? null
                    : ((int) $plan->quarter_double_price) * $size,
            ]);
        }
    }
};
