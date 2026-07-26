/** 點名／學生出勤篩選：出席、請假、補課、加課 */
export const ROLL_CALL_FILTER_STATUSES = ['present', 'excused', 'makeup', 'extra'] as const;
export type RollCallFilterStatus = (typeof ROLL_CALL_FILTER_STATUSES)[number];

/** 單筆出勤編輯可選狀態：出席、請假、補課、加課 */
export const EDITABLE_ATTENDANCE_STATUSES = ['present', 'excused', 'makeup', 'extra'] as const;
export type EditableAttendanceStatus = (typeof EDITABLE_ATTENDANCE_STATUSES)[number];

/** 出勤狀態：圓角標籤，白字（對應 Tailwind 色系） */
export function attendanceStatusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        present: 'bg-emerald-600 text-white',
        absent: 'bg-red-600 text-white',
        late: 'bg-amber-500 text-white',
        excused: 'bg-sky-600 text-white',
        makeup: 'bg-violet-600 text-white',
        extra: 'bg-orange-500 text-white',
    };

    return map[status] ?? 'bg-slate-600 text-white';
}

const STATUS_LABELS: Record<string, string> = {
    present: '出席',
    absent: '缺席',
    late: '遲到',
    excused: '請假',
    makeup: '補課',
    extra: '加課',
};

export function rollCallStatusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status;
}

export function editableAttendanceStatusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status;
}
