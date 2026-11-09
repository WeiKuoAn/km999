<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_roster_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedTinyInteger('start_month');
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->unsignedTinyInteger('end_month')->nullable();
            $table->unsignedInteger('fee')->comment('名單顯示金額（手動調整）');
            $table->unsignedInteger('original_fee')->nullable()->comment('調整前試算金額');
            $table->string('note', 500)->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'start_year', 'start_month'], 'roster_override_student_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_roster_overrides');
    }
};
