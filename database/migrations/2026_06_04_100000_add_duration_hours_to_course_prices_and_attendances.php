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
        Schema::table('course_prices', function (Blueprint $table) {
            $table->decimal('duration_hours', 3, 1)->default(1.0)->after('level');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('duration_hours', 3, 1)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('duration_hours');
        });

        Schema::table('course_prices', function (Blueprint $table) {
            $table->dropColumn('duration_hours');
        });
    }
};
