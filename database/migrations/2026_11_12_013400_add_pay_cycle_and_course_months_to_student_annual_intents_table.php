<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_annual_intents', function (Blueprint $table) {
            $table->string('pay_cycle', 16)->nullable()->after('next_fee')->comment('monthly|quarterly|annual；不年繳試算用');
            $table->json('course_months')->nullable()->after('pay_cycle')->comment('不年繳勾選月份 Y-n');
        });
    }

    public function down(): void
    {
        Schema::table('student_annual_intents', function (Blueprint $table) {
            $table->dropColumn(['pay_cycle', 'course_months']);
        });
    }
};
