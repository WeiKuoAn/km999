<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_standard_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->string('title', 128);
            $table->string('deadline_label', 64)->nullable();
            $table->text('footer_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['grade_level_id', 'academic_year_id'], 'fee_std_tpl_grade_year_idx');
            $table->index(['is_active', 'sort_order'], 'fee_std_tpl_active_sort_idx');
        });

        Schema::create('fee_standard_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_standard_template_id')
                ->constrained('fee_standard_templates')
                ->cascadeOnDelete();
            $table->string('label', 255);
            $table->text('detail')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fee_standard_template_id', 'sort_order'], 'fee_std_tpl_item_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_standard_template_items');
        Schema::dropIfExists('fee_standard_templates');
    }
};
