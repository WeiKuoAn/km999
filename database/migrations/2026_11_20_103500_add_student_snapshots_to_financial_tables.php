<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 帳務列自帶學號／姓名／年級快照；刪學生時 student_id 置 null，營收與訂金仍保留。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->prepareTable('reconciliations', 'reconciliations_student_id_foreign', withGrade: true);
        $this->prepareTable('student_material_payments', 'student_material_payments_student_id_foreign', withGrade: true);
        $this->prepareTable('annual_deposit_records', 'annual_deposit_records_student_id_foreign', withGrade: true);
        $this->prepareTable('reconciliation_logs', 'reconciliation_logs_student_id_foreign', withGrade: false);

        $this->backfill('reconciliations');
        $this->backfill('student_material_payments');
        $this->backfill('annual_deposit_records');
        $this->backfill('reconciliation_logs', withGrade: false);
    }

    public function down(): void
    {
        foreach ([
            ['reconciliations', 'reconciliations_student_id_foreign', true],
            ['student_material_payments', 'student_material_payments_student_id_foreign', true],
            ['annual_deposit_records', 'annual_deposit_records_student_id_foreign', true],
            ['reconciliation_logs', 'reconciliation_logs_student_id_foreign', false],
        ] as [$table, $foreign, $withGrade]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropForeignIfExists($table, $foreign);

            Schema::table($table, function (Blueprint $blueprint) use ($withGrade): void {
                if ($withGrade && Schema::hasColumn($blueprint->getTable(), 'grade_name')) {
                    $blueprint->dropColumn('grade_name');
                }
                if (Schema::hasColumn($blueprint->getTable(), 'student_name')) {
                    $blueprint->dropColumn(['student_code', 'student_name']);
                }
            });

            // 無法安全還原 cascade：留下可空 student_id，避免 down 時刪到孤兒列失敗
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->nullOnDelete();
            });
        }
    }

    private function prepareTable(string $table, string $foreign, bool $withGrade): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $this->dropForeignIfExists($table, $foreign);

        if (! Schema::hasColumn($table, 'student_code')) {
            Schema::table($table, function (Blueprint $blueprint) use ($withGrade): void {
                $blueprint->string('student_code', 64)->nullable()->after('student_id');
                $blueprint->string('student_name', 255)->nullable()->after('student_code');
                if ($withGrade) {
                    $blueprint->string('grade_name', 64)->nullable()->after('student_name');
                }
            });
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `student_id` BIGINT UNSIGNED NULL");

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreign('student_id')
                ->references('id')
                ->on('students')
                ->nullOnDelete();
        });
    }

    private function backfill(string $table, bool $withGrade = true): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'student_name')) {
            return;
        }

        $gradeSet = $withGrade ? ', `t`.`grade_name` = `g`.`name`' : '';

        DB::statement("
            UPDATE `{$table}` AS `t`
            INNER JOIN `students` AS `s` ON `s`.`id` = `t`.`student_id`
            LEFT JOIN `grade_levels` AS `g` ON `g`.`id` = `s`.`grade_level_id`
            SET
                `t`.`student_code` = `s`.`student_code`,
                `t`.`student_name` = `s`.`name`
                {$gradeSet}
            WHERE `t`.`student_id` IS NOT NULL
              AND (`t`.`student_name` IS NULL OR `t`.`student_name` = '')
        ");
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
