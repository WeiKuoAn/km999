<?php

namespace App\Support;

/**
 * 繳費通知條：寬 14cm；高度依科目列數伸展（單科約 6cm）。
 *
 * 金額一律用「全形數字」（與日期相同，主機中文字型可顯示）＋繪製半形千分位逗號，
 * 避免半形 0–9 / ● 在部分主機缺字變 □。
 */
final class PaymentNoticePng
{
    private const DPI = 300;

    private const WIDTH_CM = 14.0;

    private const MIN_HEIGHT_CM = 6.0;

    /**
     * @param  array{
     *   student_name:string,
     *   pay_cycle_label:string,
     *   tuition_heading:string,
     *   prefer_label:string,
     *   start_year:int,
     *   start_month:int,
     *   end_year:int,
     *   end_month:int,
     *   subject_lines:list<array{name:string, preferential_total:int, list_price:int, material_semester:int}>,
     *   grand_total:int,
     *   deadline_month:int,
     *   deadline_day:int,
     * }  $notice
     */
    public static function render(array $notice): string
    {
        $font = self::fontPath();
        $subjectLines = $notice['subject_lines'] ?? [];
        if ($subjectLines === []) {
            $subjectLines = [[
                'name' => '課程',
                'preferential_total' => (int) ($notice['grand_total'] ?? 0),
                'list_price' => 0,
                'material_semester' => 0,
            ]];
        }

        $width = (int) round(self::WIDTH_CM / 2.54 * self::DPI);
        $sizeMain = 20.0;
        $sizeSubject = 18.0;
        $sizeSmall = 14.5;
        $padX = (int) round($width * 0.03);
        $padTop = (int) round(0.32 / 2.54 * self::DPI);
        $lineH = (int) round(0.78 / 2.54 * self::DPI);
        $subjectLineH = (int) round(0.74 / 2.54 * self::DPI);
        $footerGap = (int) round(0.18 / 2.54 * self::DPI);

        $contentH = $padTop
            + $lineH * 3
            + $subjectLineH * count($subjectLines)
            + $lineH * 2
            + $footerGap
            + $lineH
            + $padTop;
        $minH = (int) round(self::MIN_HEIGHT_CM / 2.54 * self::DPI);
        $height = max($minH, $contentH);

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            throw new \RuntimeException('無法建立繳費條圖像。');
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 17, 17, 17);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $y = $padTop + (int) round($sizeMain);

        $name = (string) $notice['student_name'];
        $tuitionHeading = (string) ($notice['tuition_heading'] ?? '季繳學費');
        $preferLabel = (string) ($notice['prefer_label'] ?? '季繳優惠含教材');
        $rocStartY = self::num(self::toRocYear((int) $notice['start_year']));
        $rocEndY = self::num(self::toRocYear((int) $notice['end_year']));
        $startM = self::num((int) $notice['start_month']);
        $endM = self::num((int) $notice['end_month']);
        $endD = self::num(self::lastDay((int) $notice['end_year'], (int) $notice['end_month']));
        $grandTotal = (int) $notice['grand_total'];
        $dlM = self::num((int) $notice['deadline_month']);
        $dlD = self::num((int) $notice['deadline_day']);

        // 1) 貴子弟 ___ 同學
        $prefix = '貴子弟 ';
        $afterName = ' 同學';
        self::text($img, $sizeMain, $padX, $y, $black, $font, $prefix);
        $nameX = $padX + self::textWidth($sizeMain, $font, $prefix);
        self::text($img, $sizeMain, $nameX, $y, $black, $font, $name);
        $nameW = self::textWidth($sizeMain, $font, $name);
        self::underline($img, $nameX, $y + 5, $nameW, $black);
        self::text($img, $sizeMain, $nameX + $nameW, $y, $black, $font, $afterName);

        // 2) 修業期間
        $y += $lineH;
        self::text(
            $img,
            $sizeMain,
            $padX,
            $y,
            $black,
            $font,
            self::fw("修業期間：{$rocStartY} 年 {$startM} 月 １ 日 至 {$rocEndY} 年 {$endM} 月 {$endD} 日")
        );

        // 3) 季繳學費：
        $y += $lineH;
        self::text($img, $sizeMain, $padX, $y, $black, $font, self::fw($tuitionHeading.'：'));

        // 4) 各科條列
        foreach ($subjectLines as $line) {
            $y += $subjectLineH;
            $subjectName = (string) ($line['name'] ?? '課程');
            $lineX = $padX + (int) round(0.15 / 2.54 * self::DPI);
            $lineX = self::drawBullet($img, $lineX, $y, $sizeSubject, $black);
            $lineX = self::text($img, $sizeSubject, $lineX, $y, $black, $font, self::fw("{$subjectName}：{$preferLabel}＄ "));
            $lineX = self::drawMoney($img, $sizeSubject, $lineX, $y, $black, $font, (int) ($line['preferential_total'] ?? 0));
            $materialSemester = (int) ($line['material_semester'] ?? 0);
            $materialCharged = (int) ($line['material_charged'] ?? 0);
            if ($materialCharged > 0 || $materialSemester > 0) {
                $lineX = self::text($img, $sizeSubject, $lineX, $y, $black, $font, self::fw('（教材＄ '));
                $lineX = self::drawMoney($img, $sizeSubject, $lineX, $y, $black, $font, $materialSemester);
                self::text($img, $sizeSubject, $lineX, $y, $black, $font, self::fw('／學期）'));
            }
        }

        // 5) 合計
        $y += $lineH;
        $totalPrefix = self::fw('合計：新台幣 ');
        $totalSuffix = self::fw(' 元整');
        self::text($img, $sizeMain, $padX, $y, $black, $font, $totalPrefix);
        $totalX = $padX + self::textWidth($sizeMain, $font, $totalPrefix);
        $slotW = (int) round($width * 0.34);
        $totalW = self::moneyWidth($sizeMain, $font, $grandTotal);
        $totalTextX = $totalX + (int) max(0, ($slotW - $totalW) / 2);
        self::drawMoney($img, $sizeMain, $totalTextX, $y, $black, $font, $grandTotal);
        self::underline($img, $totalX, $y + 5, $slotW, $black);
        self::text($img, $sizeMain, $totalX + $slotW, $y, $black, $font, $totalSuffix);

        // 6) 期限
        $y += $lineH;
        $dlPrefix = self::fw('請於 ');
        $dlDate = self::fw("{$dlM}／{$dlD}");
        $dlSuffix = self::fw(' 日前繳清，以利作業');
        self::text($img, $sizeMain, $padX, $y, $black, $font, $dlPrefix);
        $dlX = $padX + self::textWidth($sizeMain, $font, $dlPrefix);
        self::text($img, $sizeMain, $dlX, $y, $black, $font, $dlDate);
        $dlW = self::textWidth($sizeMain, $font, $dlDate);
        self::underline($img, $dlX, $y + 5, $dlW, $black);
        self::text($img, $sizeMain, $dlX + $dlW, $y, $black, $font, $dlSuffix);

        // 7) 備註
        $y += $footerGap + (int) round($sizeSmall);
        self::text(
            $img,
            $sizeSmall,
            $padX,
            $y,
            $black,
            $font,
            '若逾學費期限尚未繳費，新繳納月份將依當期標準費用計費，感謝配合'
        );

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);
        $binary = ob_get_clean();
        if ($binary === false || $binary === '') {
            throw new \RuntimeException('無法輸出繳費條 PNG。');
        }

        return $binary;
    }

    /**
     * @param  list<array{filename:string, notice:array<string, mixed>}>  $items
     */
    public static function zip(array $items): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'notices_');
        if ($tmp === false) {
            throw new \RuntimeException('無法建立暫存檔。');
        }
        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('無法建立 ZIP。');
        }

        $used = [];
        foreach ($items as $item) {
            $base = preg_replace('/[\\\\\/\:\*\?\"\<\>\|]+/u', '_', $item['filename']) ?: 'notice';
            $name = $base.'.png';
            $n = 2;
            while (isset($used[$name])) {
                $name = $base.'_'.$n.'.png';
                $n++;
            }
            $used[$name] = true;
            $zip->addFromString($name, self::render($item['notice']));
        }
        $zip->close();

        $binary = file_get_contents($zipPath);
        @unlink($zipPath);
        if ($binary === false) {
            throw new \RuntimeException('無法讀取 ZIP。');
        }

        return $binary;
    }

    public static function fontPath(): string
    {
        $candidates = [
            resource_path('fonts/DroidSansFallbackFull.ttf'),
            storage_path('fonts/DroidSansFallbackFull.ttf'),
            '/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
            '/usr/share/fonts/truetype/noto/NotoSansCJKtc-Regular.otf',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
        ];
        foreach ($candidates as $path) {
            if (! is_readable($path)) {
                continue;
            }
            if (str_ends_with(strtolower($path), '.ttc')) {
                continue;
            }

            return $path;
        }

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('找不到可顯示中文的字型，請將 DroidSansFallbackFull.ttf 放到 resources/fonts/。');
    }

    private static function num(int|string $value): string
    {
        return self::fw((string) $value);
    }

    private static function fw(string $text): string
    {
        $converted = mb_convert_kana($text, 'NAs', 'UTF-8');

        return strtr($converted, [
            '/' => '／',
            '*' => '＊',
            '=' => '＝',
            '-' => '－',
            ',' => '，',
            '$' => '＄',
            '[' => '［',
            ']' => '］',
            '(' => '（',
            ')' => '）',
            '|' => '｜',
        ]);
    }

    /**
     * 金額：全形數字 + 繪製千分位逗號（外觀近似 10,800）。
     *
     * @param  \GdImage  $img
     */
    private static function drawMoney($img, float $size, int $x, int $y, int $color, string $font, int $value): int
    {
        $formatted = number_format(max(0, $value));
        $len = strlen($formatted);
        for ($i = 0; $i < $len; $i++) {
            $ch = $formatted[$i];
            if ($ch === ',') {
                $x = self::drawThousandComma($img, $x, $y, $size, $color);
            } else {
                $x = self::text($img, $size, $x, $y, $color, $font, mb_convert_kana($ch, 'N', 'UTF-8'));
            }
        }

        return $x;
    }

    private static function moneyWidth(float $size, string $font, int $value): int
    {
        $formatted = number_format(max(0, $value));
        $width = 0;
        $len = strlen($formatted);
        for ($i = 0; $i < $len; $i++) {
            $ch = $formatted[$i];
            if ($ch === ',') {
                $width += max(4, (int) round($size * 0.28));
            } else {
                $width += self::textWidth($size, $font, mb_convert_kana($ch, 'N', 'UTF-8'));
            }
        }

        return $width;
    }

    /**
     * @param  \GdImage  $img
     */
    private static function drawThousandComma($img, int $x, int $baselineY, float $fontSize, int $color): int
    {
        $advance = max(4, (int) round($fontSize * 0.28));
        $cx = $x + (int) round($advance * 0.4);
        $cy = $baselineY - (int) round($fontSize * 0.06);
        $r = max(2, (int) round($fontSize * 0.09));
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        imageline(
            $img,
            $cx,
            $cy + $r,
            $cx - max(1, (int) round($r * 0.7)),
            $cy + (int) round($r * 2.4),
            $color
        );

        return $x + $advance;
    }

    /**
     * @param  \GdImage  $img
     */
    private static function drawBullet($img, int $x, int $baselineY, float $fontSize, int $color): int
    {
        $diameter = max(6, (int) round($fontSize * 0.42));
        $cx = $x + (int) round($diameter / 2);
        $cy = $baselineY - (int) round($fontSize * 0.32);
        imagefilledellipse($img, $cx, $cy, $diameter, $diameter, $color);

        return $cx + (int) round($diameter / 2) + (int) round($fontSize * 0.35);
    }

    private static function toRocYear(int $gregorianYear): int
    {
        return $gregorianYear - 1911;
    }

    private static function lastDay(int $year, int $month): int
    {
        return (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    /**
     * @param  \GdImage  $img
     * @return int 下一段文字的起始 X
     */
    private static function text($img, float $size, int $x, int $y, int $color, string $font, string $text): int
    {
        if ($text === '') {
            return $x;
        }
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);

        return $x + self::textWidth($size, $font, $text);
    }

    private static function textWidth(float $size, string $font, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);
        if ($box === false) {
            return 0;
        }

        return (int) abs($box[2] - $box[0]);
    }

    /**
     * @param  \GdImage  $img
     */
    private static function underline($img, int $x, int $y, int $width, int $color): void
    {
        imageline($img, $x, $y, $x + max(1, $width), $y, $color);
    }
}
