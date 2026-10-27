<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->boolean('per_course_periods')->default(false)->after('unit')
                ->comment('true＝各科各自期別；false＝共用期別（現況）');
        });

        Schema::table('fee_plan_periods', function (Blueprint $table) {
            $table->foreignId('course_id')
                ->nullable()
                ->after('fee_plan_id')
                ->constrained('courses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fee_plan_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
        });

        Schema::table('fee_plans', function (Blueprint $table) {
            $table->dropColumn('per_course_periods');
        });
    }
};
