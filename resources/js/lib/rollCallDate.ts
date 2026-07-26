/** ISO 星期：1=週一 … 7=週日 */
const isoWeekday = (ymd: string): number => {
    const [y, m, d] = ymd.split('-').map(Number);
    const day = new Date(y, m - 1, d).getDay();

    return day === 0 ? 7 : day;
};

const formatYmd = (date: Date): string =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/**
 * 推算點名頁預設日期：若今天為班級上課日則用今天，否則帶入最近一個排課日（供補登）。
 */
export const resolveRollCallDate = (
    todayYmd: string,
    scheduleWeekdays: number[],
    explicitDate?: string,
): string => {
    if (explicitDate) {
        return explicitDate;
    }

    if (scheduleWeekdays.length === 0) {
        return todayYmd;
    }

    const todayWd = isoWeekday(todayYmd);
    if (scheduleWeekdays.includes(todayWd)) {
        return todayYmd;
    }

    const [y, m, d] = todayYmd.split('-').map(Number);
    for (let offset = 1; offset <= 7; offset++) {
        const candidate = new Date(y, m - 1, d - offset);
        const wd = candidate.getDay() === 0 ? 7 : candidate.getDay();
        if (scheduleWeekdays.includes(wd)) {
            return formatYmd(candidate);
        }
    }

    return todayYmd;
};

export const scheduleWeekdaysFromClassroom = (schedules: Array<{ weekday: number }>): number[] => {
    const set = new Set(schedules.map((s) => s.weekday));

    return [...set];
};
