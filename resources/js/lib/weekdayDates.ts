/** ISO 週一=1 … 週日=7 */
export const WEEKDAY_OPTIONS = [
    { value: 1, label: '一' },
    { value: 2, label: '二' },
    { value: 3, label: '三' },
    { value: 4, label: '四' },
    { value: 5, label: '五' },
    { value: 6, label: '六' },
    { value: 7, label: '日' },
] as const;

export function formatWeekdays(weekdays: number[] | null | undefined): string {
    if (!weekdays?.length) {
        return '—';
    }
    const labels = WEEKDAY_OPTIONS.filter((o) => weekdays.includes(o.value)).map((o) => o.label);
    return labels.length ? `週${labels.join('、')}` : '—';
}

export type CourseScheduleRow = {
    level?: string | null;
    weekday: number;
    start_time?: string | null;
    end_time?: string | null;
};

export function formatCourseSchedules(schedules: CourseScheduleRow[] | null | undefined): string {
    if (!schedules?.length) {
        return '—';
    }

    const byLevel = new Map<string, string[]>();
    for (const s of schedules) {
        const key = s.level?.trim() ? s.level.trim() : '不分年級';
        const day = WEEKDAY_OPTIONS.find((o) => o.value === s.weekday)?.label ?? String(s.weekday);
        const start = s.start_time?.slice(0, 5) ?? '';
        const end = s.end_time?.slice(0, 5) ?? '';
        const slot = start && end ? `週${day} ${start}-${end}` : `週${day}`;
        const list = byLevel.get(key) ?? [];
        list.push(slot);
        byLevel.set(key, list);
    }

    return [...byLevel.entries()].map(([level, slots]) => `${level}：${slots.join('、')}`).join('；');
}

/**
 * 月費基準堂數：每週上課日數 × 4。
 * 例：週三＋週六＝8；當月實際有 9 堂仍以 8 堂收全月費；只上 3 堂則 × 3/8。
 */
export function billingBaselineSessions(weekdays: number[] | null | undefined): number {
    const count = (weekdays ?? []).filter((d) => d >= 1 && d <= 7).length;
    return Math.max(1, count * 4);
}

/** 單月學費精確值（未四捨五入）：min(1, 實際堂次 / 基準堂數) × 月費 */
export function proratedMonthTuitionExact(
    unitPrice: number,
    attended: number,
    baseline: number,
): number {
    if (unitPrice <= 0 || attended <= 0) {
        return 0;
    }
    const base = Math.max(1, baseline);
    return Math.min(unitPrice, (unitPrice * attended) / base);
}

/**
 * 單月學費整數：先算精確值再四捨五入。
 * 多科同月請改用各科精確值加總後再 Math.round，避免 1162.5+1162.5 被各別進位成 2326。
 */
export function proratedMonthTuition(
    unitPrice: number,
    attended: number,
    baseline: number,
): number {
    return Math.round(proratedMonthTuitionExact(unitPrice, attended, baseline));
}

/** 計算某月符合上課星期的日期數；fromDate(Y-m-d) 有值時只算該日（含）之後 */
export function countClassDaysInMonth(
    year: number,
    month: number,
    weekdays: number[],
    fromDate?: string | null,
): number {
    return listClassDaysInMonth(year, month, weekdays, fromDate).length;
}

/** 列出某月符合上課星期的日期 Y-m-d */
export function listClassDaysInMonth(
    year: number,
    month: number,
    weekdays: number[],
    fromDate?: string | null,
): string[] {
    const set = new Set(weekdays.filter((d) => d >= 1 && d <= 7));
    if (set.size === 0) {
        return [];
    }

    const start = new Date(year, month - 1, 1);
    const end = new Date(year, month, 0);
    let from = start;
    if (fromDate) {
        const parsed = new Date(`${fromDate.slice(0, 10)}T12:00:00`);
        if (!Number.isNaN(parsed.getTime())) {
            const monthEnd = new Date(year, month, 0, 23, 59, 59);
            if (parsed > monthEnd) {
                return [];
            }
            if (parsed > start) {
                from = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
            }
        }
    }

    const out: string[] = [];
    const cursor = new Date(from.getFullYear(), from.getMonth(), from.getDate());
    while (cursor <= end) {
        const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
        if (set.has(iso)) {
            out.push(toYmd(cursor));
        }
        cursor.setDate(cursor.getDate() + 1);
    }

    return out;
}

export function toYmd(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function parseYmd(value: string): Date {
    return new Date(`${value.slice(0, 10)}T12:00:00`);
}

export function isoWeekday(date: Date): number {
    const d = date.getDay();
    return d === 0 ? 7 : d;
}

/** 自起算日起，往後 monthSpan 個月內，符合星期的預設上課日 */
export function buildDefaultSessionDates(
    startDate: string,
    weekdays: number[],
    monthSpan = 3,
    holidays?: Set<string> | string[],
): string[] {
    const start = parseYmd(startDate);
    if (Number.isNaN(start.getTime()) || weekdays.length === 0) {
        return [];
    }
    const holidaySet =
        holidays instanceof Set
            ? holidays
            : new Set((holidays ?? []).map((d) => d.slice(0, 10)));
    const end = new Date(start.getFullYear(), start.getMonth() + monthSpan, 0);
    const set = new Set(weekdays.filter((d) => d >= 1 && d <= 7));
    const out: string[] = [];
    const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
    while (cursor <= end) {
        const ymd = toYmd(cursor);
        if (set.has(isoWeekday(cursor)) && !holidaySet.has(ymd)) {
            out.push(ymd);
        }
        cursor.setDate(cursor.getDate() + 1);
    }
    return out;
}

/** 報名／調課用：某一天上的某一科 */
export type SessionEntry = {
    date: string;
    course_id: number;
};

/** 依各科上課日預填堂次（含 course_id） */
export function buildDefaultSessionEntries(
    startDate: string,
    courses: Array<{ id: number; weekdays: number[] }>,
    monthSpan = 3,
    holidays?: Set<string> | string[],
): SessionEntry[] {
    const out: SessionEntry[] = [];
    for (const course of courses) {
        for (const date of buildDefaultSessionDates(
            startDate,
            course.weekdays ?? [],
            monthSpan,
            holidays,
        )) {
            out.push({ date, course_id: course.id });
        }
    }
    return sortSessionEntries(out);
}

export function sortSessionEntries(entries: SessionEntry[]): SessionEntry[] {
    return [...entries].sort(
        (a, b) => a.date.localeCompare(b.date) || a.course_id - b.course_id,
    );
}

export function uniqueDatesFromSessions(entries: SessionEntry[]): string[] {
    return [...new Set(entries.map((e) => e.date))].sort();
}

export function countSessionsForCourse(entries: SessionEntry[], courseId: number): number {
    return entries.filter((e) => e.course_id === courseId).length;
}

export function hasSession(entries: SessionEntry[], date: string, courseId: number): boolean {
    return entries.some((e) => e.date === date && e.course_id === courseId);
}

export function toggleSession(
    entries: SessionEntry[],
    date: string,
    courseId: number,
): SessionEntry[] {
    if (hasSession(entries, date, courseId)) {
        return sortSessionEntries(entries.filter((e) => !(e.date === date && e.course_id === courseId)));
    }
    return sortSessionEntries([...entries, { date, course_id: courseId }]);
}

/** 由日期列表推導不重複年月 */
export function monthsFromDates(dates: string[]): Array<{ y: number; m: number }> {
    const map = new Map<string, { y: number; m: number }>();
    for (const d of dates) {
        const [y, m] = d.slice(0, 10).split('-').map(Number);
        if (!y || !m) continue;
        map.set(`${y}-${m}`, { y, m });
    }
    return [...map.values()].sort((a, b) => a.y - b.y || a.m - b.m);
}

/** 計算符合指定星期的選中日數 */
export function countDatesMatchingWeekdays(dates: string[], weekdays: number[]): number {
    const set = new Set(weekdays.filter((d) => d >= 1 && d <= 7));
    if (set.size === 0) {
        return 0;
    }
    return dates.filter((d) => set.has(isoWeekday(parseYmd(d)))).length;
}
