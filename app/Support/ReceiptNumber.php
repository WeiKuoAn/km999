<?php

namespace App\Support;

use App\Models\Reconciliation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * 單據編號：西元年月日 + 當日流水（例 2026/9/8 → 20260908001）。
 */
final class ReceiptNumber
{
    public static function prefixForDate(string $ymd): string
    {
        return Carbon::parse($ymd)->format('Ymd');
    }

    /** 預覽下一號（不加鎖；確認收款時請用 allocate） */
    public static function peekNext(?string $ymd = null): string
    {
        $ymd = $ymd ?? Carbon::today()->toDateString();
        $prefix = self::prefixForDate($ymd);
        $seq = self::nextSequence($prefix, lock: false);

        return self::format($prefix, $seq);
    }

    /** 在交易內配置下一號（lockForUpdate） */
    public static function allocate(?string $ymd = null): string
    {
        $ymd = $ymd ?? Carbon::today()->toDateString();
        $prefix = self::prefixForDate($ymd);
        $seq = self::nextSequence($prefix, lock: true);

        return self::format($prefix, $seq);
    }

    private static function format(string $prefix, int $seq): string
    {
        $width = $seq > 999 ? max(3, strlen((string) $seq)) : 3;

        return $prefix.str_pad((string) $seq, $width, '0', STR_PAD_LEFT);
    }

    private static function nextSequence(string $prefix, bool $lock): int
    {
        if (! Schema::hasColumn('reconciliations', 'receipt_no')) {
            return 1;
        }

        $query = Reconciliation::query()
            ->where('receipt_no', 'like', $prefix.'%')
            ->orderByDesc('receipt_no');

        if ($lock) {
            $query->lockForUpdate();
        }

        $latest = $query->value('receipt_no');
        if (! is_string($latest) || $latest === '') {
            return 1;
        }

        $suffix = substr($latest, strlen($prefix));
        if ($suffix === '' || ! ctype_digit($suffix)) {
            return 1;
        }

        return ((int) $suffix) + 1;
    }
}
