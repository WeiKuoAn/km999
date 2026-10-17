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

        @page { margin: 4mm; }

        body {
            font-family: 'NoticeCJK', 'NoticeLatin', sans-serif;
            font-size: 8pt;
            color: #111;
            line-height: 1.4;
        }

        .page { width: 100%; }
        .page-break { page-break-before: always; }

        table.sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td.cell {
            width: 50%;
            height: 64mm;
            padding: 0;
            vertical-align: middle;
            border: 0.3pt dashed #999;
        }

        table.slip-inner {
            width: 100%;
            height: 64mm;
            border-collapse: collapse;
        }

        table.slip-inner td.inner {
            height: 64mm;
            padding: 1.8mm 2.2mm;
            vertical-align: middle;
        }

        .slip-body {
            width: 100%;
        }

        .line { margin: 0 0 0.9mm; }
        .name { text-decoration: underline; }
        .subjects { margin: 0 0 0.9mm; }
        .subject {
            margin: 0 0 0.9mm;
            font-size: 6.8pt;
            line-height: 1.4;
            white-space: nowrap;
        }
        .bullet {
            font-family: 'NoticeLatin', 'NoticeCJK', sans-serif;
            font-size: 6.5pt;
            margin-right: 0.5mm;
            vertical-align: middle;
        }
        .total-line { margin: 1mm 0; }
        .total-slot {
            display: inline-block;
            min-width: 22mm;
            text-align: center;
            border-bottom: 0.55pt solid #111;
            padding: 0 1mm;
            margin: 0 0.7mm;
        }
        .date { text-decoration: underline; }
        .note { font-size: 6.5pt; margin-top: 0.7mm; color: #222; }
    </style>
</head>
<body>
@php
    $chunks = array_chunk($notices, 6);
@endphp
@foreach ($chunks as $chunkIndex => $chunk)
    <div class="page{{ $chunkIndex > 0 ? ' page-break' : '' }}">
        <table class="sheet">
            {{-- 固定 2×3，空白格保留裁切線 --}}
            @for ($row = 0; $row < 3; $row++)
                <tr>
                    @for ($col = 0; $col < 2; $col++)
                        @php $notice = $chunk[$row * 2 + $col] ?? null; @endphp
                        <td class="cell">
                            @if ($notice)
                                <table class="slip-inner">
                                    <tr>
                                        <td class="inner">
                                            <div class="slip-body">
                                                <div class="line">貴子弟 <span class="name">{{ $notice['student_name'] }}</span> 同學</div>
                                                <div class="line">{{ $notice['period_label'] }}</div>
                                                <div class="line">{{ $notice['tuition_heading'] }}：</div>
                                                <div class="subjects">
                                                    @foreach ($notice['subject_lines'] as $line)
                                                        <div class="subject">
                                                            <span class="bullet">●</span>{{ $line['name'] }}：{{ $notice['prefer_label'] }}@if ($line['has_material'])含教材@endif$ {{ $line['preferential_total'] }}（原價$ {{ $line['list_price'] }}／月）@if ($line['has_material'])（教材$ {{ $line['material_semester'] }}／學期）@endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="line total-line">
                                                    合計：新台幣<span class="total-slot">{{ $notice['grand_total'] }}</span>元整
                                                </div>
                                                <div class="line">
                                                    請於 <span class="date">{{ $notice['deadline_label'] }}</span> 日前繳清，以利作業
                                                </div>
                                                <div class="note">若逾學費期限尚未繳費，新繳納月份將原價計費，感謝配合</div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>
    </div>
@endforeach
</body>
</html>
