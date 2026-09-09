<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearStudentPaymentsCommand extends Command
{
    protected $signature = 'payments:clear
                            {--force : 略過確認直接清空}';

    protected $description = '清空學生收款資料（帳期、教材收費紀錄等；不刪學生／課程主檔）';

    /** @var list<string> */
    private const TABLES = [
        'reconciliation_logs',
        'reconciliations',
        'student_material_payments',
        'student_course_drops',
    ];

    public function handle(): int
    {
        $existing = [];
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("略過不存在的資料表：{$table}");

                continue;
            }
            $existing[$table] = DB::table($table)->count();
        }

        if ($existing === []) {
            $this->error('找不到可清空的收款相關資料表。');

            return self::FAILURE;
        }

        $this->table(
            ['資料表', '筆數'],
            collect($existing)->map(fn (int $count, string $table) => [$table, $count])->values()->all(),
        );

        if (! $this->option('force') && ! $this->confirm('確定要清空以上收款資料？此操作無法復原。', false)) {
            $this->info('已取消。');

            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (array_keys($existing) as $table) {
                $before = $existing[$table];
                DB::table($table)->truncate();
                $this->line("{$table}: {$before} → 0");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('收款資料已清空。');

        return self::SUCCESS;
    }
}
