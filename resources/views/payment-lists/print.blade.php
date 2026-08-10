<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>繳費名單</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 10mm 8mm;
            font-family: "Noto Sans TC", "Microsoft JhengHei", "PingFang TC", sans-serif;
            font-size: 14px;
            color: #111;
            line-height: 1.4;
        }
        .toolbar {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 1px solid #166534;
            background: #166534;
            color: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .toolbar a.secondary {
            background: #fff;
            color: #166534;
        }
        .meta {
            margin-bottom: 10px;
            color: #444;
            font-size: 13px;
            text-align: center;
        }
        .sheet {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
        }
        .cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #222;
            padding: 7px 4px;
            vertical-align: middle;
            text-align: center;
            word-break: break-word;
        }
        th {
            background: #f3f4f6;
            font-weight: 700;
            font-size: 14px;
        }
        td {
            font-size: 13px;
            height: 36px;
        }
        .name { width: 22%; }
        .subj { width: 34%; }
        .fee { width: 18%; font-variant-numeric: tabular-nums; font-weight: 600; }
        .note { width: 26%; }
        .subj-main { font-weight: 600; font-size: 13px; }
        .period {
            margin-top: 2px;
            color: #555;
            font-size: 12px;
            font-weight: 400;
        }
        .empty td {
            height: 36px;
            border: 1px solid #222;
        }
        .legend {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }
        .legend .box {
            border: 1px solid #333;
            padding: 8px 10px;
            text-align: center;
            font-size: 12px;
            line-height: 1.35;
        }
        .legend .fee { background: #fef9c3; }
        .legend .cycle { background: #dcfce7; }
        @media print {
            .toolbar { display: none !important; }
            body { padding: 6mm; }
            .sheet { max-width: none; }
            @page { size: A4 portrait; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">列印／另存 PDF</button>
        <a class="secondary" href="{{ route('payment-lists.index', array_filter(['q' => $q ?: null, 'year' => $yearLabel !== '全部' ? $yearLabel : null])) }}">返回繳費名單</a>
    </div>

    <div class="meta">
        繳費名單（尚未繳下一期）｜產生時間 {{ $generatedAt }}｜年份 {{ $yearLabel }}
        @if($q !== '')｜搜尋：{{ $q }}@endif
        ｜共 {{ count($rows) }} 人
    </div>

    @php
        $mid = (int) ceil(count($rows) / 2);
        $left = array_slice($rows, 0, $mid);
        $right = array_slice($rows, $mid);
        $rowCount = max(count($left), count($right), 1);
        $padTo = max($rowCount, 20);
    @endphp

    <div class="sheet">
        <div class="cols">
            @foreach ([$left, $right] as $columnRows)
                <table>
                    <thead>
                        <tr>
                            <th class="name">學生姓名</th>
                            <th class="subj">科目月份</th>
                            <th class="fee">費用</th>
                            <th class="note">備註</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($columnRows as $row)
                            <tr>
                                <td class="name">{{ $row['student_name'] }}</td>
                                <td class="subj">
                                    <div class="subj-main">{{ $row['subjects_label'] }}</div>
                                    <div class="period">{{ $row['period_label'] }}</div>
                                </td>
                                <td class="fee">{{ number_format($row['fee']) }}</td>
                                <td class="note">{{ $row['note'] !== '' ? $row['note'] : '' }}</td>
                            </tr>
                        @empty
                            @if ($loop->first && count($rows) === 0)
                                <tr>
                                    <td colspan="4" style="color:#666;padding:24px;">無待繳名單</td>
                                </tr>
                            @endif
                        @endforelse
                        @if (count($rows) > 0)
                            @for ($i = count($columnRows); $i < $padTo; $i++)
                                <tr class="empty">
                                    <td></td><td></td><td></td><td></td>
                                </tr>
                            @endfor
                        @endif
                    </tbody>
                </table>
            @endforeach
        </div>

        <div class="legend">
            <div class="box fee">教材費／學期<br>依收費標準</div>
            <div class="box fee">學費<br>依繳別與科目數計價</div>
            <div class="box cycle">繳費週期<br>季繳／三個月（首期可不足）</div>
        </div>
    </div>
</body>
</html>
