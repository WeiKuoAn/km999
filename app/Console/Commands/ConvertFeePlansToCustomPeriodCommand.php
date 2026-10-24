<?php

namespace App\Console\Commands;

use App\Support\FeePlanCustomPeriodConverter;
use Illuminate\Console\Command;

class ConvertFeePlansToCustomPeriodCommand extends Command
{
    protected $signature = 'fee-plans:convert-to-custom-period
                            {--force : 已是自訂期別也依月價重切期別}';

    protected $description = '將按月／按季／其他收費設定轉成自訂期別（本機轉換用）';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $this->info($force ? '強制重轉全部…' : '轉換尚未為自訂期別的收費設定…');

        $result = FeePlanCustomPeriodConverter::convertAll($force);

        foreach ($result['details'] as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
        $this->info("完成：轉換 {$result['converted']} 筆，略過 {$result['skipped']} 筆。");

        return self::SUCCESS;
    }
}
