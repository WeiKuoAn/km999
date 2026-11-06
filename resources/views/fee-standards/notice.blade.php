<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'NoticeCJK';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $fontUrl }}') format('truetype');
        }

        @font-face {
            font-family: 'NoticeLatin';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $latinFontUrl }}') format('truetype');
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* 上／右／下；左邊距改由 body padding 固定 1cm（Dompdf 較穩） */
        @page { margin: 10mm 12mm 10mm 0; }

        body {
            font-family: 'NoticeCJK', 'NoticeLatin', sans-serif;
            font-size: 11.5pt;
            color: #111;
            line-height: 1.5;
            padding-left: 10mm; /* 左邊留白 1 公分 */
        }

        .sheet {
            width: 100%;
        }

        .page-break {
            page-break-before: always;
        }

        .slot {
            width: 100%;
            min-height: 128mm;
        }

        .cut-line {
            border-top: 0.7pt dashed #888;
            margin: 3mm 0 5mm;
            height: 0;
        }

        /* Dompdf 對 CJK 粗體常缺字→????，標題改用字級＋字距強調 */
        .title {
            text-align: center;
            font-size: 16pt;
            font-weight: normal;
            letter-spacing: 0.18em;
            margin-bottom: 6mm;
        }

        .greeting {
            font-size: 12.5pt;
            margin-bottom: 5mm;
        }

        .name {
            text-decoration: underline;
            padding: 0 2mm;
        }

        .item {
            margin: 0 0 3.5mm;
        }

        .item-row {
            display: table;
            width: 100%;
        }

        .checkbox {
            display: table-cell;
            width: 7mm;
            vertical-align: top;
            font-family: 'NoticeLatin', 'NoticeCJK', sans-serif;
            font-size: 12.5pt;
            line-height: 1.4;
            padding-top: 0.2mm;
        }

        .item-body {
            display: table-cell;
            vertical-align: top;
        }

        .item-label {
            font-size: 11.5pt;
        }

        .item-detail {
            margin-top: 1mm;
            margin-left: 1mm;
            font-size: 10.5pt;
            color: #222;
            white-space: pre-wrap;
        }

        .table-block {
            margin: 0 0 4.5mm;
        }

        .table-heading {
            font-size: 12pt;
            margin-bottom: 1.5mm;
            letter-spacing: 0.04em;
        }

        .fee-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10pt;
        }

        .fee-table th,
        .fee-table td {
            border: 0.7pt solid #333;
            padding: 1.6mm 2mm;
            vertical-align: middle;
            text-align: center;
            word-wrap: break-word;
        }

        .fee-table th {
            background-color: #ffe566;
            font-weight: normal;
        }

        .total {
            margin-top: 5mm;
            font-size: 12pt;
        }

        .total-slot {
            display: inline-block;
            min-width: 32mm;
            border-bottom: 0.7pt solid #111;
            margin: 0 2mm;
            text-align: center;
        }

        .deadline {
            margin-top: 3.5mm;
            font-size: 11pt;
        }

        .footer {
            margin-top: 6mm;
            font-size: 10pt;
            color: #222;
        }
    </style>
</head>
<body>
@foreach (array_chunk($pages, 2) as $sheetIndex => $sheet)
    <div class="sheet{{ $sheetIndex > 0 ? ' page-break' : '' }}">
        @foreach ($sheet as $slotIndex => $page)
            @if ($slotIndex === 1)
                <div class="cut-line"></div>
            @endif
            <div class="slot">
                <div class="title">{{ $page['title'] }}</div>

                <div class="greeting">
                    貴弟子： <span class="name">{{ $page['student_name'] }}</span> 同學
                </div>

                @foreach ($page['items'] as $item)
                    @if (($item['kind'] ?? 'check') === 'table')
                        <div class="table-block">
                            @if (!empty($item['label']))
                                <div class="table-heading"@if (!empty($item['heading_color'])) style="color: {{ $item['heading_color'] }};"@endif>
                                    {{ $item['label'] }}
                                </div>
                            @endif
                            @if (!empty($item['table_headers']))
                                <table class="fee-table">
                                    <thead>
                                        <tr>
                                            @foreach ($item['table_headers'] as $header)
                                                <th>{{ $header }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (($item['table_rows'] ?? []) as $row)
                                            <tr>
                                                @foreach ($item['table_headers'] as $colIndex => $header)
                                                    <td>{{ $row[$colIndex] ?? '' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @else
                        <div class="item">
                            <div class="item-row">
                                <div class="checkbox">□</div>
                                <div class="item-body">
                                    <div class="item-label">{{ $item['label'] }}@if (!str_ends_with(rtrim($item['label']), '：') && !str_ends_with(rtrim($item['label']), ':'))：@endif</div>
                                    @if (!empty($item['detail']))
                                        <div class="item-detail">{{ $item['detail'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach

                <div class="total">合計：<span class="total-slot">&nbsp;</span>元</div>

                @if (!empty($page['deadline_label']))
                    <div class="deadline">請於 {{ $page['deadline_label'] }} 前繳納，以利作業</div>
                @endif

                <div class="footer">{{ $page['footer_note'] }}</div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
