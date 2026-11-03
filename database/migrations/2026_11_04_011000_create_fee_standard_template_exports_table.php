<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_standard_template_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_standard_template_id')
                ->constrained('fee_standard_templates')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('exported_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('exported_at');
            $table->timestamps();

            $table->unique(
                ['fee_standard_template_id', 'student_id'],
                'fee_std_tpl_export_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_standard_template_exports');
    }
};
