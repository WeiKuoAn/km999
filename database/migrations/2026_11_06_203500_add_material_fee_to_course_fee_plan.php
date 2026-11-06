<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_fee_plan', function (Blueprint $table) {
            $table->unsignedInteger('material_fee')->nullable()->after('course_id')
                ->comment('該科教材年費／日耗材；null＝沿用收費設定預設 material_fee');
        });

        // 既有資料：各科先帶入方案上的教材費
        DB::table('fee_plans')->orderBy('id')->each(function (object $plan): void {
            DB::table('course_fee_plan')
                ->where('fee_plan_id', $plan->id)
                ->whereNull('material_fee')
                ->update(['material_fee' => (int) ($plan->material_fee ?? 0)]);
        });
    }

    public function down(): void
    {
        Schema::table('course_fee_plan', function (Blueprint $table) {
            $table->dropColumn('material_fee');
        });
    }
};
