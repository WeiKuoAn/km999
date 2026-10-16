<?php

namespace App\Support;

/**
 * 繳費通知條：實體 6cm（高）× 14cm（寬），輸出 PNG。
 */
final class PaymentNoticePng
{
    /** 列印解析度（DPI） */
    private const DPI = 300;

    /** 寬 14cm、高 6cm */
    private const WIDTH_CM = 14.0;

    private const HEIGHT_CM = 6.0;

    /**
     * @param  array{
     *   student_name:string,
     *   plan_label:string,
     *   list_price:int,
     *   start_year:int,
     *   start_month:int,
     *   end_year:int,
     *   end_month:int,
     *   tuition_unit:int,
     *   month_count:int,
     *   tuition_total:int,
     *   material_label:string,
     *   grand_total:int,
     *   deadline_month:int,
     *   deadline_day:int,
     * }  $notice
     */
    public static function render(array $notice): string
    {
        $width = (int) round(self::WIDTH_CM / 2.54 * self::DPI);
        $height = (int) round(self::HEIGHT_CM / 2.54 * self::DPI);
        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            throw new \RuntimeException('無法建立繳費條圖像。');
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 17, 17, 17);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $font = self::fontPath();
        $padX = (int) round($width * 0.045);
        $lineH = (int) round($height / 7.6);
        $sizeMain = 15.5; // pt-ish for imagettftext
        $sizeSmall = 12.5;
        $y = (int) round($height * 0.14);

        $name = (string) $notice['student_name'];
        $plan = (string) $notice['plan_label'];
        $listPrice = (int) $notice['list_price'];
        $rocStartY = self::toRocYear((int) $notice['start_year']);
        $rocEndY = self::toRocYear((int) $notice['end_year']);
        $startM = (int) $notice['start_month'];
        $endM = (int) $notice['end_month'];
        $tuitionUnit = (int) $notice['tuition_unit'];
        $monthCount = (int) $notice['month_count'];
        $tuitionTotal = (int) $notice['tuition_total'];
        $material = (string) $notice['material_label'];
        $grand = (int) $notice['grand_total'];
        $dlM = (int) $notice['deadline_month'];
        $dlD = (int) $notice['deadline_day'];

        // 1) 貴子弟 ___ 同學 數季繳【原價 …】
        $prefix = '貴子弟 ';
        $mid = ' 同學 '.$plan.'【原價 '.$listPrice.' 元/月/科】';
        self::text($img, $sizeMain, $padX, $y, $black, $font, $prefix);
        $nameX = $padX + self::textWidth($sizeMain, $font, $prefix);
        self::text($img, $sizeMain, $nameX, $y, $black, $font, $name);
        $nameW = self::textWidth($sizeMain, $font, $name);
        self::underline($img, $nameX, $y + 4, $nameW, $black);
        self::text($img, $sizeMain, $nameX + $nameW, $y, $black, $font, $mid);

        $y += $lineH;
        self::text(
            $img,
            $sizeMain,
            $padX,
            $y,
            $black,
            $font,
            sprintf('修業期間：%d 年 %d 月 1 日 至 %d 年 %d 月 %d 日', $rocStartY, $startM, $rocEndY, $endM, self::lastDay($notice['end_year'], $endM))
        );

        $y += $lineH;
        self::text(
            $img,
            $sizeMain,
            $padX,
            $y,
            $black,
            $font,
            sprintf('學費：%d 元 * %d 個月 = %d 元', $tuitionUnit, $monthCount, $tuitionTotal)
        );

        $y += $lineH;
        self::text($img, $sizeMain, $padX, $y, $black, $font, '教 材：'.$material);

        $y += $lineH;
        $totalPrefix = '合計：新台幣 ';
        $totalSuffix = ' 元整';
        self::text($img, $sizeMain, $padX, $y, $black, $font, $totalPrefix);
        $totalX = $padX + self::textWidth($sizeMain, $font, $totalPrefix);
        $totalStr = (string) $grand;
        $slotW = (int) round($width * 0.38);
        $totalW = self::textWidth($sizeMain, $font, $totalStr);
        $totalTextX = $totalX + (int) max(0, ($slotW - $totalW) / 2);
        self::text($img, $sizeMain, $totalTextX, $y, $black, $font, $totalStr);
        self::underline($img, $totalX, $y + 4, $slotW, $black);
        self::text($img, $sizeMain, $totalX + $slotW, $y, $black, $font, $totalSuffix);

        $y += $lineH;
        $dlPrefix = '※請於 ';
        $dlDate = sprintf('%d/%d', $dlM, $dlD);
        $dlSuffix = ' 日前繳清，以利作業';
        self::text($img, $sizeMain, $padX, $y, $black, $font, $dlPrefix);
        $dlX = $padX + self::textWidth($sizeMain, $font, $dlPrefix);
        self::text($img, $sizeMain, $dlX, $y, $black, $font, $dlDate);
        $dlW = self::textWidth($sizeMain, $font, $dlDate);
        self::underline($img, $dlX, $y + 4, $dlW, $black);
        self::text($img, $sizeMain, $dlX + $dlW, $y, $black, $font, $dlSuffix);

        $y += (int) round($lineH * 0.95);
        self::text(
            $img,
            $sizeSmall,
            $padX,
            $y,
            $black,
            $font,
            '若逾學費期限尚未繳費，新繳納月份將原價計費，感謝配合'
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
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/noto/NotoSansCJKtc-Regular.otf',
        ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('找不到可顯示中文的字型，請將 DroidSansFallbackFull.ttf 放到 resources/fonts/。');
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
     */
    private static function text($img, float $size, int $x, int $y, int $color, string $font, string $text): void
    {
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
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
