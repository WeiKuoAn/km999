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

        /*
         * Dompdf：用 @page margin 留邊；表格用 width:100%（相對內容區）。
         * 不要再包一層固定 mm 寬的 .sheet 包住固定寬表格，會把內容頂到紙邊。
         */
        @page {
            margin: 18mm 16mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'NoticeCJK', 'NoticeLatin', sans-serif;
            font-size: 11.5pt;
            color: #111;
            line-height: 1.5;
        }

        .page-break {
            page-break-before: always;
        }

        .slot {
            min-height: 120mm;
        }

        .cut-line {
            border-top: 0.7pt dashed #888;
            margin: 3mm 0 4mm;
            height: 0;
        }

        .title {
            text-align: center;
            font-size: 16pt;
            font-weight: normal;
            letter-spacing: 0.18em;
            margin: 0 0 5mm;
        }

        .greeting {
            font-size: 12.5pt;
            margin: 0 0 5mm;
        }

        .name {
            text-decoration: underline;
            padding: 0 1.5mm;
        }

        .item {
            margin: 0 0 3.5mm;
        }

        .checkbox {
            display: inline-block;
            width: 6mm;
            vertical-align: top;
            font-family: 'NoticeLatin', 'NoticeCJK', sans-serif;
            font-size: 12.5pt;
            line-height: 1.4;
        }

        .item-body {
            display: inline-block;
            width: 92%;
            vertical-align: top;
        }

        .item-label {
            font-size: 11.5pt;
        }

        .item-detail {
            margin-top: 1mm;
            margin-left: 0.5mm;
            font-size: 10.5pt;
            color: #222;
            white-space: pre-wrap;
        }

        .table-block {
            margin: 0 0 4.5mm;
        }

        .table-heading {
            font-size: 12pt;
            margin: 0 0 2.5mm;
            letter-spacing: 0.04em;
        }

        .fee-table {
            width: 100%;
            border-collapse: collapse;
            border: 0.8pt solid #333;
            table-layout: fixed;
            font-size: 10pt;
        }

        .fee-table th,
        .fee-table td {
            border: 0.8pt solid #333;
            padding: 1.8mm 2mm;
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
            margin-top: 5mm;
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
                                <div class="table-heading"@if (!empty($item['heading_color']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $item['heading_color'])) style="color: {{ $item['heading_color'] }};"@endif>
                                    {{ $item['label'] }}
                                </div>
                            @endif
                            @if (!empty($item['table_headers']))
                                <table class="fee-table" cellpadding="0" cellspacing="0">
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
                            <span class="checkbox">□</span>
                            <span class="item-body">
                                <div class="item-label">{{ $item['label'] }}@if (!str_ends_with(rtrim($item['label']), '：') && !str_ends_with(rtrim($item['label']), ':'))：@endif</div>
                                @if (!empty($item['detail']))
                                    <div class="item-detail">{{ $item['detail'] }}</div>
                                @endif
                            </span>
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
