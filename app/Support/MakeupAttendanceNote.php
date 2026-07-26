<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * 補課出勤 note 欄位約定：
 * - 「補課日期:Y-m-d」後接「原缺席的上課日」（固定週期那一天）。
 * - 選填「補課已排:Y-m-d」：實際補課上課日（與缺席日不同時）；若省略且為拆開紀錄，則以 attendance.class_date 為補課日。
 */
final class MakeupAttendanceNote
{
    private const PREFIX_ORIGINAL = '補課日期:';

    private const PREFIX_SCHEDULED = '補課已排:';

    /** note 中「補課日期:」後的第一個 Y-m-d（原缺席日） */
    public static function originalMissedDate(?string $note): ?string
    {
        return self::firstYmdAfterPrefix((string) $note, self::PREFIX_ORIGINAL);
    }

    /** note 中「補課已排:」後的第一個 Y-m-d（實際補課日，點名頁選的日期） */
    public static function scheduledMakeupDate(?string $note): ?string
    {
        return self::firstYmdAfterPrefix((string) $note, self::PREFIX_SCHEDULED);
    }

    /**
     * @return array<string, string> original missed Y-m-d → makeup session Y-m-d
     */
    public static function originalMissedToMakeupSessionMap(iterable $makeupRecords): array
    {
        $map = [];

        foreach ($makeupRecords as $r) {
            if (($r->status ?? null) !== 'makeup') {
                continue;
            }
            $note = (string) ($r->note ?? '');
            $cd = Carbon::parse($r->class_date)->toDateString();

            $scheduled = self::scheduledMakeupDate($note);
            $orig = self::originalMissedDate($note);

            if ($scheduled !== null && $orig !== null) {
                $map[$orig] = $scheduled;

                continue;
            }

            if ($orig === null) {
                continue;
            }

            // 僅「補課日期:」單一日期（無補課已排）：可能是拆開紀錄或舊點名誤存
            if ($orig === $cd) {
                $map[$orig] = $cd;

                continue;
            }

            try {
                $cdCarbon = Carbon::parse($cd)->startOfDay();
                $origCarbon = Carbon::parse($orig)->startOfDay();
            } catch (\Throwable) {
                $map[$orig] = $cd;

                continue;
            }

            // note 日期早于 class_date：拆開紀錄（原日在 note，補課列 class_date）
            if ($origCarbon->lessThan($cdCarbon)) {
                $map[$orig] = $cd;

                continue;
            }

            // note 日期晚于 class_date：舊點名（當日點名存缺席堂 class_date，note 誤存成補課日）
            if ($origCarbon->greaterThan($cdCarbon)) {
                $map[$cd] = $orig;

                continue;
            }

            // 同日其他異常：保守對應
            $map[$orig] = $cd;
        }

        return $map;
    }

    /** 點名表單「補課日期」輸入欄應顯示的 Y-m-d */
    public static function rollCallMakeupDateInput(string $classDateYmd, ?string $note): string
    {
        $scheduled = self::scheduledMakeupDate($note);
        if ($scheduled !== null) {
            return $scheduled;
        }

        $orig = self::originalMissedDate($note);
        if ($orig === null) {
            return '';
        }

        if ($orig === $classDateYmd) {
            return $classDateYmd;
        }

        try {
            $d = Carbon::parse($classDateYmd)->startOfDay();
            $o = Carbon::parse($orig)->startOfDay();
            if ($d->greaterThan($o)) {
                return $classDateYmd;
            }
        } catch (\Throwable) {
            return $orig;
        }

        return $orig;
    }

    private static function firstYmdAfterPrefix(string $note, string $prefix): ?string
    {
        $pos = mb_strpos($note, $prefix);
        if ($pos === false) {
            return null;
        }
        $after = mb_substr($note, $pos + mb_strlen($prefix));
        $after = trim($after);
        if ($after === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $after, $m)) {
            try {
                return Carbon::parse($m[1])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
