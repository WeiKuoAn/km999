export type StudentStatus = 'active' | 'paused' | 'graduated';

export const STUDENT_STATUSES: readonly StudentStatus[] = ['active', 'paused', 'graduated'];

export function studentStatusLabel(status: StudentStatus | string): string {
    return status === 'active'
        ? '在學'
        : status === 'paused'
          ? '停課'
          : status === 'graduated'
            ? '已畢業'
            : status;
}

export function studentStatusBadgeClass(status: StudentStatus | string): string {
    return status === 'active'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
        : status === 'paused'
          ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300'
          : status === 'graduated'
            ? 'bg-slate-100 text-slate-700 dark:bg-slate-950/40 dark:text-slate-300'
            : '';
}

export function studentStatusPillClass(status: StudentStatus | string): string {
    const base = studentStatusBadgeClass(status);
    if (!base) {
        return '';
    }

    return `inline-flex max-w-full shrink-0 truncate rounded-full px-2.5 py-0.5 text-xs font-medium ${base}`;
}

/** 國三（畢業年級）判斷：名稱為國三，或年級碼為 9 */
export function isGraduationGrade(grade: { name?: string | null; code?: number | null }): boolean {
    if (grade.name === '國三') {
        return true;
    }
    return Number(grade.code) === 9;
}
