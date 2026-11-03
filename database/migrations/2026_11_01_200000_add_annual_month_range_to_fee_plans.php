<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('annual_start_month')->nullable()->after('allow_annual')
                ->comment('年繳帳期起始月（1–12）；預設 7');
            $table->unsignedTinyInteger('annual_end_month')->nullable()->after('annual_start_month')
                ->comment('年繳帳期結束月（1–12）；預設 6');
        });
    }

    public function down(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->dropColumn(['annual_start_month', 'annual_end_month']);
        });
    }
};
