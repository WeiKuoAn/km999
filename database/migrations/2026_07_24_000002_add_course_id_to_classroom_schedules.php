<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_schedules', function (Blueprint $table) {
            $table->foreignId('course_id')
                ->nullable()
                ->after('classroom_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->index(['classroom_id', 'course_id']);
        });

        // 既有時段：從班級單一 course_id 回填
        DB::statement('
            UPDATE classroom_schedules cs
            INNER JOIN classrooms c ON c.id = cs.classroom_id
            SET cs.course_id = c.course_id
            WHERE cs.course_id IS NULL AND c.course_id IS NOT NULL
        ');

        // classrooms.course_id 改可空（主課改由 schedules 決定；保留第一科作相容）
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        DB::statement('ALTER TABLE classrooms MODIFY course_id BIGINT UNSIGNED NULL');
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 把第一筆 schedule 的 course 寫回班級，避免還原失敗
        DB::statement('
            UPDATE classrooms c
            INNER JOIN (
                SELECT classroom_id, MIN(id) AS mid
                FROM classroom_schedules
                WHERE course_id IS NOT NULL
                GROUP BY classroom_id
            ) x ON x.classroom_id = c.id
            INNER JOIN classroom_schedules cs ON cs.id = x.mid
            SET c.course_id = cs.course_id
            WHERE c.course_id IS NULL
        ');

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        DB::statement('ALTER TABLE classrooms MODIFY course_id BIGINT UNSIGNED NOT NULL');
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::table('classroom_schedules', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropIndex(['classroom_id', 'course_id']);
            $table->dropColumn('course_id');
        });
    }
};
