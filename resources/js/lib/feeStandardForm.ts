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
    items: FeeStandardFormItem[];
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

export const FEE_STANDARD_HEADING_COLORS: Array<{
    value: string;
    label: string;
}> = [
    { value: '#1d4ed8', label: '藍' },
    { value: '#991b1b', label: '紅' },
    { value: '#166534', label: '綠' },
    { value: '#111827', label: '黑' },
];
