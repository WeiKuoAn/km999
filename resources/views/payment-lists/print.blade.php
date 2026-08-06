<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>繳費名單</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12mm 10mm;
            font-family: "Noto Sans TC", "Microsoft JhengHei", "PingFang TC", sans-serif;
            font-size: 11px;
            color: #111;
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
            font-size: 13px;
            cursor: pointer;
        }
        .toolbar a.secondary {
            background: #fff;
            color: #166534;
        }
        .meta {
            margin-bottom: 8px;
            color: #444;
            font-size: 12px;
        }
        .sheet {
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }
        .col {
            flex: 1 1 0;
            min-width: 0;
        }
        .side {
            width: 78px;
            flex: 0 0 78px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 22px;
        }
        .side .box {
            border: 1px solid #333;
            padding: 8px 6px;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            font-size: 11px;
            line-height: 1.35;
            min-height: 110px;
            text-align: center;
        }
        .side .box.fee { background: #fef9c3; }
        .side .box.cycle { background: #dcfce7; }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #222;
            padding: 4px 5px;
            vertical-align: top;
            height: 28px;
        }
        th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: center;
        }
        .name { width: 22%; }
        .subj { width: 34%; }
        .fee { width: 16%; text-align: right; font-variant-numeric: tabular-nums; }
        .note { width: 28%; }
        .marks {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 8px;
            margin-bottom: 2px;
        }
        .mark {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            white-space: nowrap;
        }
        .mark .box {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1px solid #111;
            text-align: center;
            line-height: 10px;
            font-size: 10px;
        }
        .period { color: #444; font-size: 10px; }
        .empty td {
            height: 28px;
            border: 1px solid #222;
        }
        @media print {
            .toolbar { display: none !important; }
            body { padding: 6mm; }
            @page { size: A4 landscape; margin: 8mm; }
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
        ｜共 {{ count($left) + count($right) }} 人
    </div>

    <div class="sheet">
        @foreach ([['rows' => $left], ['rows' => $right]] as $column)
            <div class="col">
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
                        @forelse ($column['rows'] as $row)
                            <tr>
                                <td class="name">{{ $row['student_name'] }}</td>
                                <td class="subj">
                                    <div class="marks">
                                        @foreach ($row['subject_marks'] as $mark)
                                            <span class="mark"><span class="box">✓</span>{{ mb_substr($mark, 0, 1) }}</span>
                                        @endforeach
                                    </div>
                                    <div class="period">{{ $row['period_label'] }}</div>
                                </td>
                                <td class="fee">{{ number_format($row['fee']) }}</td>
                                <td class="note">{{ $row['note'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center;color:#666;padding:24px;">無待繳名單</td>
                            </tr>
                        @endforelse
                        @if (count($column['rows']) > 0)
                            @for ($i = count($column['rows']); $i < max(count($left), count($right), 18); $i++)
                                <tr class="empty">
                                    <td></td><td></td><td></td><td></td>
                                </tr>
                            @endfor
                        @endif
                    </tbody>
                </table>
            </div>
        @endforeach

        <div class="side">
            <div class="box fee">教材費／學期 依收費標準</div>
            <div class="box fee">學費／依繳別與科目數計價</div>
            <div class="box cycle">繳費週期 季繳／三個月（首期可不足）</div>
        </div>
    </div>

    <script>
        // 可選：開啟後自動跳出列印對話框（註解掉則手動按按鈕）
        // window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
</body>
</html>
