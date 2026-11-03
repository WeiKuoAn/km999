<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->string('plan_kind', 32)->default('period')->after('unit')
                ->comment('period＝期別月價；annual_package＝年繳固定方案');
            $table->unsignedInteger('package_total')->nullable()->after('annual_end_month')
                ->comment('年繳方案總額（含教材）；僅 plan_kind=annual_package');
        });
    }

    public function down(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->dropColumn(['plan_kind', 'package_total']);
        });
    }
};
