<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plan_periods', function (Blueprint $table) {
            $table->unsignedInteger('double_price')->nullable()->after('total_price')->comment('雙科時每科一期總價');
            $table->unsignedInteger('triple_price')->nullable()->after('double_price')->comment('三科時每科一期總價');
        });
    }

    public function down(): void
    {
        Schema::table('fee_plan_periods', function (Blueprint $table) {
            $table->dropColumn(['double_price', 'triple_price']);
        });
    }
};
