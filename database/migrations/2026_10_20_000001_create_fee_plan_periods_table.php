<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_plan_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('start_month');
            $table->unsignedTinyInteger('end_month');
            $table->unsignedInteger('total_price')->comment('一期總價');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fee_plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_plan_periods');
    }
};
