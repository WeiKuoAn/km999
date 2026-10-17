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

        * {
            box-sizing: border-box;
        }

        @page {
            margin: {{ $single ? '4mm' : '8mm 10mm' }};
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'NoticeCJK', sans-serif;
            font-size: 11pt;
            color: #111;
            line-height: 1.45;
        }

        .slip {
            width: 140mm;
            min-height: {{ $single ? '56mm' : '58mm' }};
            padding: 4mm 5mm;
            margin: 0 auto {{ $single ? '0' : '3mm' }};
            border: 0.4pt dashed #999;
            page-break-inside: avoid;
        }

        .slip:last-child {
            margin-bottom: 0;
        }

        .line {
            margin: 0 0 2.2mm;
        }

        .name-line .name {
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .subjects {
            margin: 0 0 2.2mm 3mm;
        }

        .subject {
            margin: 0 0 1.4mm;
        }

        .bullet {
            font-size: 9pt;
            margin-right: 1.5mm;
        }

        .total-line {
            margin: 2.5mm 0 2.2mm;
        }

        .total-slot {
            display: inline-block;
            min-width: 42mm;
            text-align: center;
            border-bottom: 0.7pt solid #111;
            padding: 0 2mm 1px;
            margin: 0 1mm;
        }

        .deadline .date {
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .note {
            font-size: 9pt;
            margin-top: 2mm;
            color: #222;
        }
    </style>
</head>
<body>
@foreach ($notices as $notice)
    <div class="slip">
        <div class="line name-line">
            貴子弟 <span class="name">{{ $notice['student_name'] }}</span> 同學
        </div>
        <div class="line">{{ $notice['period_label'] }}</div>
        <div class="line">{{ $notice['tuition_heading'] }}：</div>
        <div class="subjects">
            @foreach ($notice['subject_lines'] as $line)
                <div class="subject">
                    <span class="bullet">●</span>{{ $line['name'] }}：{{ $notice['prefer_label'] }}$ {{ $line['preferential_total'] }}（原價$ {{ $line['list_price'] }}／月）（教材$ {{ $line['material_semester'] }}／學期）
                </div>
            @endforeach
        </div>
        <div class="line total-line">
            合計：新台幣<span class="total-slot">{{ $notice['grand_total'] }}</span>元整
        </div>
        <div class="line deadline">
            請於 <span class="date">{{ $notice['deadline_label'] }}</span> 日前繳清，以利作業
        </div>
        <div class="note">若逾學費期限尚未繳費，新繳納月份將原價計費，感謝配合</div>
    </div>
@endforeach
</body>
</html>
