<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_deposit_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->nullable()->constrained('fee_plans')->nullOnDelete();
            $table->string('fee_label');
            $table->unsignedInteger('amount');
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['paid_on', 'student_id']);
            $table->index('fee_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_deposit_records');
    }
};
