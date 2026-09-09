<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->comment('closure=停課；makeup=補課');
            $table->date('date_from');
            $table->date('date_to');
            $table->string('name');
            $table->foreignId('grade_level_id')
                ->nullable()
                ->constrained('grade_levels')
                ->nullOnDelete()
                ->comment('null=全部年級');
            $table->time('start_time')->nullable()->comment('補課選填時段');
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->index(['type', 'date_from', 'date_to']);
            $table->index('grade_level_id');
        });

        Schema::create('schedule_exception_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_exception_id')
                ->constrained('schedule_exceptions')
                ->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unique(['schedule_exception_id', 'course_id'], 'sched_exc_course_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exception_course');
        Schema::dropIfExists('schedule_exceptions');
    }
};
