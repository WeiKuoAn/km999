<?php

namespace App\Jobs;

use App\Models\FeeStandardTemplate;
use App\Models\User;
use App\Support\FeeStandardExport;
use App\Support\FeeStandardPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateFeeStandardPdfJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    /**
     * @param  list<int>|null  $studentIds
     */
    public function __construct(
        public string $exportId,
        public int $userId,
        public int $templateId,
        public ?array $studentIds = null,
        public bool $includeExported = false,
    ) {}

    public function handle(): void
    {
        $key = FeeStandardExport::cacheKey($this->exportId);

        Cache::put($key, [
            'status' => 'processing',
            'user_id' => $this->userId,
            'message' => '正在產生收費標準單 PDF…',
        ], now()->addHours(2));

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $user = User::query()->with('teacher')->find($this->userId);
        $template = FeeStandardTemplate::query()->with('items')->find($this->templateId);
        if ($user === null || $template === null) {
            Cache::put($key, [
                'status' => 'failed',
                'user_id' => $this->userId,
                'message' => '找不到匯出資料。',
            ], now()->addHours(2));

            return;
        }

        try {
            $pages = FeeStandardExport::buildPages(
                $template,
                $user,
                $this->studentIds,
                $this->includeExported,
            );
            if ($pages === []) {
                Cache::put($key, [
                    'status' => 'failed',
                    'user_id' => $this->userId,
                    'message' => '目前沒有可匯出的學生（可能都已匯出過）。',
                ], now()->addHours(2));

                return;
            }

            $pdf = FeeStandardPdf::render($pages);
            $path = FeeStandardExport::relativePath($this->exportId);
            Storage::disk('local')->put($path, $pdf);

            FeeStandardExport::markExported(
                $template,
                array_map(fn (array $page): int => (int) $page['student_id'], $pages),
                $this->userId,
            );

            Cache::put($key, [
                'status' => 'ready',
                'user_id' => $this->userId,
                'path' => $path,
                'filename' => $template->title.'_收費標準.pdf',
                'message' => '已完成，可下載。',
            ], now()->addHours(2));
        } catch (Throwable $e) {
            Cache::put($key, [
                'status' => 'failed',
                'user_id' => $this->userId,
                'message' => '產生失敗：'.$e->getMessage(),
            ], now()->addHours(2));
        }
    }
}
