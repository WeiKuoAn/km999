<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->boolean('annual_end_next_year')
                ->default(false)
                ->after('annual_end_month')
                ->comment('年繳結束月是否為隔年（例：7月～隔年8月）');
        });
    }

    public function down(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->dropColumn('annual_end_next_year');
        });
    }
};
