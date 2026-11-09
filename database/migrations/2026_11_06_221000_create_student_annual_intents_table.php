<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_annual_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->nullable()->constrained('fee_plans')->nullOnDelete();
            $table->string('package_label')->nullable()->comment('方案名稱快照；null＝不年繳');
            $table->unsignedInteger('package_total')->nullable()->comment('方案總額快照');
            $table->string('status', 16)->default('planned')->comment('planned|applied|cancelled');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_promotion_log_id')->nullable()->constrained('student_promotion_logs')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_annual_intents');
    }
};
