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

export type ScheduleExceptionRange = {
    date_from: string;
    date_to: string;
    name?: string;
    grade_level_id?: number | null;
    /** 空陣列＝該範圍內全部課程 */
    course_ids?: number[];
    start_time?: string | null;
    end_time?: string | null;
};

function ymdInRange(ymd: string, from: string, to: string): boolean {
    return ymd >= from.slice(0, 10) && ymd <= to.slice(0, 10);
}

function exceptionAppliesToCourse(
    ex: ScheduleExceptionRange,
    courseId: number,
): boolean {
    const ids = ex.course_ids ?? [];
    return ids.length === 0 || ids.includes(courseId);
}

function expandExceptionDates(
    ex: ScheduleExceptionRange,
    rangeStart: string,
    rangeEnd: string,
): string[] {
    const from = maxYmd(ex.date_from.slice(0, 10), rangeStart.slice(0, 10));
    const to = minYmd(ex.date_to.slice(0, 10), rangeEnd.slice(0, 10));
    if (from > to) {
        return [];
    }
    const out: string[] = [];
    const cursor = parseYmd(from);
    const end = parseYmd(to);
    while (cursor <= end) {
        out.push(toYmd(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }
    return out;
}

function maxYmd(a: string, b: string): string {
    return a >= b ? a : b;
}

function minYmd(a: string, b: string): string {
    return a <= b ? a : b;
}

function monthSpanEndDate(startDate: string, monthSpan: number): string {
    const start = parseYmd(startDate);
    const end = new Date(start.getFullYear(), start.getMonth() + monthSpan, 0);
    return toYmd(end);
}

export type YearMonth = { y: number; m: number };

export function monthKey(y: number, m: number): string {
    return `${y}-${m}`;
}

export function parseMonthKey(key: string): YearMonth | null {
    const [ys, ms] = key.split('-');
    const y = Number(ys);
    const m = Number(ms);
    if (!Number.isFinite(y) || !Number.isFinite(m) || m < 1 || m > 12) {
        return null;
    }
    return { y, m };
}

/** 自起算日起連續 monthSpan 個曆月（含起算月） */
export function defaultBillingMonths(
    startDate: string,
    monthSpan: number,
): YearMonth[] {
    const start = parseYmd(startDate);
    if (Number.isNaN(start.getTime()) || monthSpan <= 0) {
        return [];
    }
    const out: YearMonth[] = [];
    for (let i = 0; i < monthSpan; i++) {
        const d = new Date(start.getFullYear(), start.getMonth() + i, 1);
        out.push({ y: d.getFullYear(), m: d.getMonth() + 1 });
    }
    return out;
}

/** 自起算月起列出可勾選的月份選項（預設 12 個月） */
export function billingMonthOptions(
    startDate: string,
    count = 12,
): YearMonth[] {
    return defaultBillingMonths(startDate, count);
}

function lastDayOfYearMonth(y: number, m: number): string {
    return toYmd(new Date(y, m, 0));
}

function monthsSpanFromStart(startDate: string, months: YearMonth[]): number {
    if (months.length === 0) {
        return 1;
    }
    const start = parseYmd(startDate);
    const sorted = [...months].sort((a, b) => a.y - b.y || a.m - b.m);
    const last = sorted[sorted.length - 1];
    const end = new Date(last.y, last.m - 1, 1);
    return (
        (end.getFullYear() - start.getFullYear()) * 12 +
        (end.getMonth() - start.getMonth()) +
        1
    );
}

/** 報名／調課用：某一天上的某一科 */
export type SessionEntry = {
    date: string;
    course_id: number;
};

/** 依各科上課日預填堂次（含 course_id）；可套用年級／科目停課與補課 */
export function buildDefaultSessionEntries(
    startDate: string,
    courses: Array<{
        id: number;
        weekdays: number[];
        start_date?: string | null;
        end_date?: string | null;
    }>,
    monthSpan = 3,
    holidays?: Set<string> | string[],
    options?: {
        closures?: ScheduleExceptionRange[];
        makeups?: ScheduleExceptionRange[];
        gradeLevelId?: number | null;
        /** 若有指定，只產生這些曆月的堂次（起算日前仍不計） */
        months?: YearMonth[];
    },
): SessionEntry[] {
    const holidaySet =
        holidays instanceof Set
            ? holidays
            : new Set((holidays ?? []).map((d) => d.slice(0, 10)));

    const selectedMonths = options?.months ?? null;
    const monthFilter =
        selectedMonths && selectedMonths.length > 0
            ? new Set(selectedMonths.map((m) => monthKey(m.y, m.m)))
            : null;

    const effectiveSpan =
        selectedMonths && selectedMonths.length > 0
            ? Math.max(1, monthsSpanFromStart(startDate, selectedMonths))
            : monthSpan;

    const rangeEnd =
        selectedMonths && selectedMonths.length > 0
            ? (() => {
                  const sorted = [...selectedMonths].sort(
                      (a, b) => a.y - b.y || a.m - b.m,
                  );
                  const last = sorted[sorted.length - 1];
                  return lastDayOfYearMonth(last.y, last.m);
              })()
            : monthSpanEndDate(startDate, effectiveSpan);

    const gradeLevelId = options?.gradeLevelId ?? null;
    const closures = (options?.closures ?? []).filter(
        (ex) =>
            ex.grade_level_id == null ||
            gradeLevelId == null ||
            ex.grade_level_id === gradeLevelId,
    );
    const makeups = (options?.makeups ?? []).filter(
        (ex) =>
            ex.grade_level_id == null ||
            gradeLevelId == null ||
            ex.grade_level_id === gradeLevelId,
    );

    const out: SessionEntry[] = [];
    const seen = new Set<string>();

    const acceptDate = (date: string, courseStart?: string | null, courseEnd?: string | null) => {
        if (monthFilter) {
            const [y, m] = date.slice(0, 10).split('-').map(Number);
            if (!monthFilter.has(monthKey(y, m))) {
                return false;
            }
        }
        if (date > rangeEnd) {
            return false;
        }
        const cs = courseStart?.slice(0, 10);
        const ce = courseEnd?.slice(0, 10);
        if (cs && date < cs) {
            return false;
        }
        if (ce && date > ce) {
            return false;
        }
        return true;
    };

    for (const course of courses) {
        const courseStart = course.start_date ?? null;
        const courseEnd = course.end_date ?? null;
        let effectiveStart = startDate.slice(0, 10);
        if (courseStart && courseStart.slice(0, 10) > effectiveStart) {
            effectiveStart = courseStart.slice(0, 10);
        }
        let effectiveEnd = rangeEnd;
        if (courseEnd && courseEnd.slice(0, 10) < effectiveEnd) {
            effectiveEnd = courseEnd.slice(0, 10);
        }
        if (effectiveStart > effectiveEnd) {
            continue;
        }

        const closed = new Set<string>();
        for (const ex of closures) {
            if (!exceptionAppliesToCourse(ex, course.id)) {
                continue;
            }
            for (const d of expandExceptionDates(ex, effectiveStart, effectiveEnd)) {
                closed.add(d);
            }
        }

        for (const date of buildDefaultSessionDates(
            effectiveStart,
            course.weekdays ?? [],
            effectiveSpan,
            holidaySet,
        )) {
            if (closed.has(date) || !acceptDate(date, courseStart, courseEnd)) {
                continue;
            }
            const key = `${date}#${course.id}`;
            if (seen.has(key)) {
                continue;
            }
            seen.add(key);
            out.push({ date, course_id: course.id });
        }

        for (const ex of makeups) {
            if (!exceptionAppliesToCourse(ex, course.id)) {
                continue;
            }
            for (const date of expandExceptionDates(ex, effectiveStart, effectiveEnd)) {
                if (holidaySet.has(date) || closed.has(date)) {
                    continue;
                }
                if (!acceptDate(date, courseStart, courseEnd)) {
                    continue;
                }
                const key = `${date}#${course.id}`;
                if (seen.has(key)) {
                    continue;
                }
                seen.add(key);
                out.push({ date, course_id: course.id });
            }
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
