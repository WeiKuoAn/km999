<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\PaymentNoticeExport;
use App\Support\PaymentNoticePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePaymentNoticesPdfJob implements ShouldQueue
{
    use Queueable;

    /** 中文字型＋大量繳費條可能較久 */
    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public string $exportId,
        public int $userId,
        public ?string $q,
        public ?int $year,
        public ?string $periodStart,
        public ?int $studentId = null,
    ) {}

    public function handle(): void
    {
        $key = PaymentNoticeExport::cacheKey($this->exportId);

        Cache::put($key, [
            'status' => 'processing',
            'user_id' => $this->userId,
            'message' => '正在產生繳費條 PDF…',
        ], now()->addHours(2));

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $user = User::query()->with('teacher')->find($this->userId);
        if ($user === null) {
            Cache::put($key, [
                'status' => 'failed',
                'user_id' => $this->userId,
                'message' => '找不到匯出使用者。',
            ], now()->addHours(2));

            return;
        }

        $items = PaymentNoticeExport::buildItems(
            $this->q,
            $this->year,
            $this->periodStart,
            $user,
            $this->studentId,
        );

        if ($items === []) {
            Cache::put($key, [
                'status' => 'failed',
                'user_id' => $this->userId,
                'message' => '目前沒有可匯出的繳費條。',
            ], now()->addHours(2));

            return;
        }

        $pdf = PaymentNoticePdf::render($items);
        $path = PaymentNoticeExport::relativePath($this->exportId);
        Storage::disk('local')->put($path, $pdf);

        $filename = count($items) === 1
            ? $items[0]['filename'].'.pdf'
            : 'payment-notices_'.now()->format('Ymd_His').'.pdf';

        Cache::put($key, [
            'status' => 'ready',
            'user_id' => $this->userId,
            'path' => $path,
            'filename' => $filename,
            'count' => count($items),
            'message' => '已完成，可下載。',
        ], now()->addHours(2));
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put(PaymentNoticeExport::cacheKey($this->exportId), [
            'status' => 'failed',
            'user_id' => $this->userId,
            'message' => $exception?->getMessage() ?: '產生繳費條 PDF 失敗。',
        ], now()->addHours(2));
    }
}
