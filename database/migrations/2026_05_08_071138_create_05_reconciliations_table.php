<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('billing_year');
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedInteger('expected_amount');
            $table->unsignedInteger('paid_amount')->default(0);
            $table->date('paid_date')->nullable();
            $table->string('status', 16)->default('unpaid');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'classroom_id', 'billing_year', 'billing_month'], 'reco_unique_monthly');
            $table->index(['billing_year', 'billing_month']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
