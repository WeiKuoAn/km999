export type StudentStatus = 'active' | 'paused';

export const STUDENT_STATUSES: readonly StudentStatus[] = ['active', 'paused'];

export function studentStatusLabel(status: StudentStatus | string): string {
    return status === 'active' ? '在學' : status === 'paused' ? '停課' : status;
}

export function studentStatusBadgeClass(status: StudentStatus | string): string {
    return status === 'active'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
        : status === 'paused'
          ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300'
          : '';
}

export function studentStatusPillClass(status: StudentStatus | string): string {
    const base = studentStatusBadgeClass(status);
    if (!base) {
        return '';
    }

    return `inline-flex max-w-full shrink-0 truncate rounded-full px-2.5 py-0.5 text-xs font-medium ${base}`;
}
