export type FeeStandardItemKind = 'check' | 'table';

export type FeeStandardFormItem = {
    kind: FeeStandardItemKind;
    label: string;
    heading_color: string;
    detail: string;
    table_headers: string[];
    table_rows: string[][];
    sort_order: number;
};

export type FeeStandardFormData = {
    academic_year_id: string;
    grade_level_id: string;
    title: string;
    deadline_label: string;
    footer_note: string;
    is_active: boolean;
    sort_order: number;
    /** 勾選項目（□） */
    check_items: FeeStandardFormItem[];
    /** 收費表格（黃表頭） */
    tables: FeeStandardFormItem[];
};

export const emptyCheckItem = (sortOrder = 0): FeeStandardFormItem => ({
    kind: 'check',
    label: '',
    heading_color: '',
    detail: '',
    table_headers: [],
    table_rows: [],
    sort_order: sortOrder,
});

export const emptyTableItem = (sortOrder = 0): FeeStandardFormItem => ({
    kind: 'table',
    label: '',
    heading_color: '#1d4ed8',
    detail: '',
    table_headers: ['繳費月份', '收費標準', '教材費/學期'],
    table_rows: [['', '', '']],
    sort_order: sortOrder,
});

/** 存檔／預覽：勾選在前、表格在後 */
export const mergeFeeStandardItems = (
    data: Pick<FeeStandardFormData, 'check_items' | 'tables'>,
): FeeStandardFormItem[] => [
    ...data.check_items.map((item, i) => ({
        ...item,
        kind: 'check' as const,
        sort_order: i,
    })),
    ...data.tables.map((item, i) => ({
        ...item,
        kind: 'table' as const,
        sort_order: data.check_items.length + i,
    })),
];

export const feeStandardItemsPayload = (
    data: Pick<FeeStandardFormData, 'check_items' | 'tables'>,
) =>
    mergeFeeStandardItems(data).map((item, i) => ({
        kind: item.kind === 'table' ? 'table' : 'check',
        label: item.label,
        heading_color:
            item.kind === 'table' ? item.heading_color || null : null,
        detail: item.kind === 'table' ? null : item.detail,
        table_headers: item.kind === 'table' ? item.table_headers : null,
        table_rows: item.kind === 'table' ? item.table_rows : null,
        sort_order: i,
    }));

export const splitFeeStandardItems = (
    items: Array<{
        kind?: 'check' | 'table';
        label: string;
        heading_color?: string;
        detail?: string;
        table_headers?: string[];
        table_rows?: string[][];
        sort_order?: number;
    }>,
): { check_items: FeeStandardFormItem[]; tables: FeeStandardFormItem[] } => {
    const check_items: FeeStandardFormItem[] = [];
    const tables: FeeStandardFormItem[] = [];

    for (const [i, item] of items.entries()) {
        if (item.kind === 'table') {
            const base = emptyTableItem(item.sort_order ?? i);
            const headers =
                item.table_headers && item.table_headers.length >= 2
                    ? [...item.table_headers]
                    : base.table_headers;
            const rows =
                item.table_rows && item.table_rows.length > 0
                    ? item.table_rows.map((row) => {
                          const cells = [...row];
                          while (cells.length < headers.length) {
                              cells.push('');
                          }
                          return cells.slice(0, headers.length);
                      })
                    : [Array.from({ length: headers.length }, () => '')];
            tables.push({
                ...base,
                label: item.label,
                heading_color: item.heading_color || base.heading_color,
                table_headers: headers,
                table_rows: rows,
                sort_order: item.sort_order ?? i,
            });
        } else {
            check_items.push({
                ...emptyCheckItem(item.sort_order ?? i),
                label: item.label,
                detail: item.detail ?? '',
                sort_order: item.sort_order ?? i,
            });
        }
    }

    return {
        check_items:
            check_items.length > 0 ? check_items : [emptyCheckItem(0)],
        tables,
    };
};

export const FEE_STANDARD_HEADING_COLORS: Array<{
    value: string;
    label: string;
}> = [
    { value: '#1d4ed8', label: '藍' },
    { value: '#991b1b', label: '紅' },
    { value: '#166534', label: '綠' },
    { value: '#111827', label: '黑' },
];
