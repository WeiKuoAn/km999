<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * 收費標準勾選單 PDF：A4 直式，兩人一頁（虛線分隔），左邊留白，空白勾選格給家長紙本勾選。
 */
final class FeeStandardPdf
{
    /**
     * @param  list<array{
     *   student_name:string,
     *   title:string,
     *   deadline_label:?string,
     *   footer_note:string,
     *   items:list<array{label:string, detail:?string}>
     * }>  $pages
     */
    public static function render(array $pages): string
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('沒有可匯出的收費標準單。');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        @ini_set('max_execution_time', '180');
        @ini_set('memory_limit', '512M');

        $fontPath = PaymentNoticePdf::fontPath();
        $latinFontPath = PaymentNoticePdf::latinFontPath();
        $fontCacheDir = self::fontCacheDir();

        $html = view('fee-standards.notice', [
            'pages' => $pages,
            'fontUrl' => 'file://'.$fontPath,
            'latinFontUrl' => 'file://'.$latinFontPath,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', false);
        $options->set('defaultFont', 'NoticeCJK');
        $options->setFontDir($fontCacheDir);
        $options->setFontCache($fontCacheDir);
        $options->setChroot([
            base_path(),
            resource_path(),
            storage_path(),
            dirname($fontPath),
            dirname($latinFontPath),
            $fontCacheDir,
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $binary = $dompdf->output();
        if ($binary === null || $binary === '') {
            throw new \RuntimeException('無法輸出收費標準單 PDF。');
        }

        return $binary;
    }

    private static function fontCacheDir(): string
    {
        $dir = storage_path('app/dompdf/fonts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
