<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_promotion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('action', 16)->comment('promote|graduate');
            $table->foreignId('from_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('to_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->string('from_grade_name')->nullable();
            $table->string('to_grade_name')->nullable();
            $table->string('old_student_code', 32)->nullable();
            $table->string('new_student_code', 32)->nullable();
            $table->string('old_status', 16)->nullable();
            $table->string('new_status', 16)->nullable();
            $table->string('course_mode', 16)->nullable()->comment('auto|manual');
            $table->json('previous_course_ids')->nullable();
            $table->json('new_course_ids')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['student_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotion_logs');
    }
};
