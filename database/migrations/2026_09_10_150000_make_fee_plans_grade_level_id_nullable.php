<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plans', function (Blueprint $table) {
            $table->dropForeign(['grade_level_id']);
        });

        DB::statement('ALTER TABLE fee_plans MODIFY grade_level_id BIGINT UNSIGNED NULL');

        Schema::table('fee_plans', function (Blueprint $table) {
            $table->foreign('grade_level_id')
                ->references('id')
                ->on('grade_levels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 還原前先把全年級（null）清掉，避免 NOT NULL 失敗
        DB::table('fee_plans')->whereNull('grade_level_id')->delete();

        Schema::table('fee_plans', function (Blueprint $table) {
            $table->dropForeign(['grade_level_id']);
        });

        DB::statement('ALTER TABLE fee_plans MODIFY grade_level_id BIGINT UNSIGNED NOT NULL');

        Schema::table('fee_plans', function (Blueprint $table) {
            $table->foreign('grade_level_id')
                ->references('id')
                ->on('grade_levels')
                ->cascadeOnDelete();
        });
    }
};
