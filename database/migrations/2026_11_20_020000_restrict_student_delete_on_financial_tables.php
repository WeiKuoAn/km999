<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 帳務資料不可因刪學生而 cascade 消失。
 * student_id FK 改為 restrict：有收款／教材／訂金／對帳紀錄時資料庫也會擋刪。
 */
return new class extends Migration
{
    /** @var list<array{table:string, foreign:string}> */
    private array $targets = [
        ['table' => 'reconciliations', 'foreign' => 'reconciliations_student_id_foreign'],
        ['table' => 'student_material_payments', 'foreign' => 'student_material_payments_student_id_foreign'],
        ['table' => 'annual_deposit_records', 'foreign' => 'annual_deposit_records_student_id_foreign'],
        ['table' => 'reconciliation_logs', 'foreign' => 'reconciliation_logs_student_id_foreign'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $target) {
            if (! Schema::hasTable($target['table'])) {
                continue;
            }

            $this->dropForeignIfExists($target['table'], $target['foreign']);

            Schema::table($target['table'], function (Blueprint $table): void {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $target) {
            if (! Schema::hasTable($target['table'])) {
                continue;
            }

            $this->dropForeignIfExists($target['table'], $target['foreign']);

            Schema::table($target['table'], function (Blueprint $table): void {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->cascadeOnDelete();
            });
        }
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
