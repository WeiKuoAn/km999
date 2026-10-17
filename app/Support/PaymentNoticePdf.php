<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * 繳費通知條 PDF：單筆預覽；多筆合併同一份以便列印裁剪。
 * 單條約寬 14cm、高 ≥6cm。
 */
final class PaymentNoticePdf
{
    /**
     * @param  list<array{filename?:string, notice:array<string, mixed>}>  $items
     */
    public static function render(array $items): string
    {
        if ($items === []) {
            throw new \InvalidArgumentException('沒有可匯出的繳費條。');
        }

        $fontPath = self::fontPath();
        $fontUrl = self::fontFileUrl($fontPath);
        $notices = array_map(
            fn (array $item): array => self::normalizeNotice($item['notice'] ?? []),
            $items
        );

        $html = view('payment-lists.notices', [
            'notices' => $notices,
            'fontUrl' => $fontUrl,
            'single' => count($notices) === 1,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'NoticeCJK');
        $options->setChroot([
            base_path(),
            resource_path(),
            storage_path(),
            dirname($fontPath),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');

        if (count($notices) === 1) {
            // 單張：接近實體繳費條尺寸（14cm × 6.5cm）
            $dompdf->setPaper([0.0, 0.0, self::mmToPt(140), self::mmToPt(65)]);
        } else {
            $dompdf->setPaper('A4', 'portrait');
        }

        $dompdf->render();
        $binary = $dompdf->output();
        if ($binary === null || $binary === '') {
            throw new \RuntimeException('無法輸出繳費條 PDF。');
        }

        return $binary;
    }

    public static function fontPath(): string
    {
        return PaymentNoticePng::fontPath();
    }

    private static function fontFileUrl(string $path): string
    {
        return 'file://'.$path;
    }

    private static function mmToPt(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    /**
     * @param  array<string, mixed>  $notice
     * @return array<string, mixed>
     */
    private static function normalizeNotice(array $notice): array
    {
        $subjectLines = $notice['subject_lines'] ?? [];
        if (! is_array($subjectLines) || $subjectLines === []) {
            $subjectLines = [[
                'name' => '課程',
                'preferential_total' => (int) ($notice['grand_total'] ?? 0),
                'list_price' => 0,
                'material_semester' => 0,
            ]];
        }

        $startYear = (int) ($notice['start_year'] ?? 0);
        $startMonth = (int) ($notice['start_month'] ?? 1);
        $endYear = (int) ($notice['end_year'] ?? 0);
        $endMonth = (int) ($notice['end_month'] ?? 1);

        return [
            'student_name' => (string) ($notice['student_name'] ?? ''),
            'tuition_heading' => (string) ($notice['tuition_heading'] ?? '季繳學費'),
            'prefer_label' => (string) ($notice['prefer_label'] ?? '季繳優惠含教材'),
            'period_label' => sprintf(
                '修業期間：%d 年 %d 月 1 日 至 %d 年 %d 月 %d 日',
                self::toRocYear($startYear),
                $startMonth,
                self::toRocYear($endYear),
                $endMonth,
                self::lastDay($endYear, $endMonth),
            ),
            'subject_lines' => array_map(function (array $line): array {
                return [
                    'name' => (string) ($line['name'] ?? '課程'),
                    'preferential_total' => self::money((int) ($line['preferential_total'] ?? 0)),
                    'list_price' => self::money((int) ($line['list_price'] ?? 0)),
                    'material_semester' => self::money((int) ($line['material_semester'] ?? 0)),
                ];
            }, $subjectLines),
            'grand_total' => self::money((int) ($notice['grand_total'] ?? 0)),
            'deadline_label' => sprintf(
                '%d／%d',
                (int) ($notice['deadline_month'] ?? 1),
                (int) ($notice['deadline_day'] ?? 20),
            ),
        ];
    }

    private static function money(int $value): string
    {
        return number_format(max(0, $value));
    }

    private static function toRocYear(int $gregorianYear): int
    {
        return $gregorianYear - 1911;
    }

    private static function lastDay(int $year, int $month): int
    {
        return (int) date('t', mktime(0, 0, 0, max(1, $month), 1, max(1970, $year)));
    }
}
