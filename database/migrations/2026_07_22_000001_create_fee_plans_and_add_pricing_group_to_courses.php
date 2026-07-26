<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->string('group_name');
            $table->string('pricing_group', 32);
            $table->string('unit', 16)->default('month')->comment('month|session_block');
            $table->unsignedTinyInteger('session_block_size')->nullable();
            $table->unsignedInteger('list_price');
            $table->unsignedInteger('quarter_price')->nullable();
            $table->unsignedInteger('quarter_single_price')->nullable();
            $table->unsignedInteger('quarter_double_price')->nullable();
            $table->unsignedInteger('material_fee')->default(0);
            $table->string('material_unit', 16)->default('term')->comment('term|subject');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['grade_level_id', 'pricing_group']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('pricing_group', 32)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('pricing_group');
        });

        Schema::dropIfExists('fee_plans');
    }
};
