<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('reconciliations', 'reconciliations_classroom_id_foreign');

        // student_id FK 目前靠 reco_unique_monthly 當索引，先補獨立索引再 drop unique
        $indexes = $this->indexNames('reconciliations');
        if (! in_array('reconciliations_student_id_index', $indexes, true)) {
            Schema::table('reconciliations', function (Blueprint $table) {
                $table->index('student_id', 'reconciliations_student_id_index');
            });
        }

        $indexes = $this->indexNames('reconciliations');
        if (in_array('reco_unique_monthly', $indexes, true)) {
            Schema::table('reconciliations', function (Blueprint $table) {
                $table->dropUnique('reco_unique_monthly');
            });
        }

        DB::statement('ALTER TABLE reconciliations MODIFY classroom_id BIGINT UNSIGNED NULL');

        if (! Schema::hasColumn('reconciliations', 'course_id')) {
            Schema::table('reconciliations', function (Blueprint $table) {
                $table->foreignId('course_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('reconciliations', 'pay_cycle')) {
            Schema::table('reconciliations', function (Blueprint $table) {
                $table->string('pay_cycle', 16)->nullable()->after('course_id');
            });
        }

        $indexes = $this->indexNames('reconciliations');
        if (! in_array('reco_unique_student_course_month', $indexes, true)) {
            Schema::table('reconciliations', function (Blueprint $table) {
                $table->unique(
                    ['student_id', 'course_id', 'billing_year', 'billing_month'],
                    'reco_unique_student_course_month'
                );
            });
        }

        $this->dropForeignIfExists('reconciliations', 'reconciliations_classroom_id_foreign');
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->foreign('classroom_id')->references('id')->on('classrooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('reconciliations', 'reconciliations_classroom_id_foreign');

        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropUnique('reco_unique_student_course_month');
            $table->dropColumn(['course_id', 'pay_cycle']);
        });

        DB::statement('ALTER TABLE reconciliations MODIFY classroom_id BIGINT UNSIGNED NOT NULL');

        Schema::table('reconciliations', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'classroom_id', 'billing_year', 'billing_month'],
                'reco_unique_monthly'
            );
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
        });
    }

    /**
     * @return list<string>
     */
    private function indexNames(string $table): array
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();
    }

    private function dropForeignIfExists(string $table, string $foreignName): void
    {
        $db = DB::getDatabaseName();
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$db, $table, $foreignName, 'FOREIGN KEY']
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignName}`");
        }
    }
};
