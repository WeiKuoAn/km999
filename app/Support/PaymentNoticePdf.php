<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * 繳費通知條 PDF：A4 橫式，每頁 2×3＝6 格，方便列印裁剪。
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
        $latinFontPath = self::latinFontPath();
        $notices = array_map(
            fn (array $item): array => self::normalizeNotice($item['notice'] ?? []),
            $items
        );

        $html = view('payment-lists.notices', [
            'notices' => $notices,
            'fontUrl' => self::fontFileUrl($fontPath),
            'latinFontUrl' => self::fontFileUrl($latinFontPath),
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
            dirname($latinFontPath),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
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

    /** ● 等符號用（中文字型在部分主機缺字） */
    public static function latinFontPath(): string
    {
        $candidates = [
            resource_path('fonts/DejaVuSans.ttf'),
            storage_path('fonts/DejaVuSans.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return self::fontPath();
    }

    private static function fontFileUrl(string $path): string
    {
        return 'file://'.$path;
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
                $materialCharged = (int) ($line['material_charged'] ?? 0);

                return [
                    'name' => (string) ($line['name'] ?? '課程'),
                    'preferential_total' => self::money((int) ($line['preferential_total'] ?? 0)),
                    'list_price' => self::money((int) ($line['list_price'] ?? 0)),
                    'has_material' => $materialCharged > 0,
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
