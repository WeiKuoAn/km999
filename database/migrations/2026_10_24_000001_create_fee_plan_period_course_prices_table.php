<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_plan_period_course_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_plan_period_id')->constrained('fee_plan_periods')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_price')->comment('該科單科一期總價');
            $table->unsignedInteger('double_price')->nullable()->comment('雙科時該科一期總價');
            $table->unsignedInteger('triple_price')->nullable()->comment('三科時該科一期總價');
            $table->timestamps();

            $table->unique(['fee_plan_period_id', 'course_id'], 'fee_plan_period_course_unique');
        });

        // 舊資料：把期別統一總價複製到該收費設定的每一科
        $periods = DB::table('fee_plan_periods')->get(['id', 'fee_plan_id', 'total_price', 'double_price', 'triple_price']);
        foreach ($periods as $period) {
            $courseIds = DB::table('course_fee_plan')
                ->where('fee_plan_id', $period->fee_plan_id)
                ->pluck('course_id');
            foreach ($courseIds as $courseId) {
                DB::table('fee_plan_period_course_prices')->insert([
                    'fee_plan_period_id' => $period->id,
                    'course_id' => $courseId,
                    'total_price' => (int) $period->total_price,
                    'double_price' => $period->double_price,
                    'triple_price' => $period->triple_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_plan_period_course_prices');
    }
};
