<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_annual_intents', function (Blueprint $table) {
            $table->unsignedInteger('deposit')->nullable()->after('package_total')->comment('訂金（只登記意向）');
            $table->unsignedInteger('next_fee')->nullable()->after('deposit')->comment('建議下次應收');
        });
    }

    public function down(): void
    {
        Schema::table('student_annual_intents', function (Blueprint $table) {
            $table->dropColumn(['deposit', 'next_fee']);
        });
    }
};
