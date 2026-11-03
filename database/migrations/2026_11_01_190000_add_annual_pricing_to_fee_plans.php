<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->boolean('allow_annual')->default(false)->after('per_course_periods')
                ->comment('true＝開放年繳優惠（整學年 7–6）');
        });

        Schema::create('fee_plan_annual_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_plan_id')->constrained('fee_plans')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedInteger('amount')->comment('該科整學年年繳金額（元）');
            $table->timestamps();

            $table->unique(['fee_plan_id', 'course_id'], 'fee_plan_annual_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_plan_annual_prices');

        Schema::table('fee_plans', function (Blueprint $table) {
            $table->dropColumn('allow_annual');
        });
    }
};
