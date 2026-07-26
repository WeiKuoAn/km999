export type CoursePriceLike = {
    level: string | null;
    duration_hours?: number;
    tuition: number;
};

export function formatDurationHours(hours: number | null | undefined): string {
    if (hours == null) {
        return '1hr';
    }

    const normalized = Math.round(hours * 10) / 10;
    const formatted = String(normalized).replace(/\.0$/, '');

    return `${formatted}hr`;
}

/** 類別 / 課程名；課程有多種時數且已記錄時，加上「 - 1hr」後綴 */
export function formatCourseLabel(
    categoryName: string,
    courseName: string,
    durationHours?: number | null,
): string {
    const base = `${categoryName} / ${courseName}`;
    if (durationHours == null) {
        return base;
    }

    return `${base} - ${formatDurationHours(durationHours)}`;
}

/**
 * 新增班級學生時建議帶入的學費：優先「不分學段」的最低時數，否則依 sort_order 第一筆。
 */
export function defaultTuitionFromCoursePrices(prices: CoursePriceLike[] | undefined): number {
    if (!prices?.length) {
        return 0;
    }
    const sorted = [...prices].sort(
        (a, b) => (a.duration_hours ?? 1) - (b.duration_hours ?? 1),
    );
    const none = sorted.find((p) => p.level == null || p.level === '');
    if (none !== undefined) {
        return none.tuition;
    }

    return sorted[0]!.tuition;
}

/** 固定學段（與學生資料、課程學費學段名稱一致時才能對到價格） */
export const SCHOOL_SEGMENTS = ['國小', '國中', '高中', '大學'] as const;
export type SchoolSegment = (typeof SCHOOL_SEGMENTS)[number];

/**
 * 依學段（國小／國中／高中）對應課程 `course_prices`；對不到則回傳預設價（最低時數）。
 */
export function tuitionFromSchoolSegment(
    prices: CoursePriceLike[] | undefined,
    segment: string | null | undefined,
): number {
    if (!prices?.length) {
        return 0;
    }

    const normalized = typeof segment === 'string' ? segment.trim() : '';
    if (normalized !== '') {
        const matches = prices.filter((p) => {
            const lv = p.level;

            return lv != null && lv !== '' && lv === normalized;
        });
        if (matches.length > 0) {
            const sorted = [...matches].sort(
                (a, b) => (a.duration_hours ?? 1) - (b.duration_hours ?? 1),
            );

            return sorted[0]!.tuition;
        }
    }

    return defaultTuitionFromCoursePrices(prices);
}

export type CourseOptionLike = {
    course_category: { name: string };
    name: string;
    course_prices: CoursePriceLike[];
};

function normalizeDurationHours(hours: number | null | undefined): number {
    return Math.round((hours ?? 1) * 10) / 10;
}

export function distinctDurationHours(prices: CoursePriceLike[] | undefined): number[] {
    if (!prices?.length) {
        return [];
    }

    return [...new Set(prices.map((p) => normalizeDurationHours(p.duration_hours)))].sort((a, b) => a - b);
}

export function hasMultipleCourseDurations(prices: CoursePriceLike[] | undefined): boolean {
    return distinctDurationHours(prices).length > 1;
}

/** 依學段與時數對應課程價目；對不到則回退同學段或預設價 */
export function tuitionFromSchoolSegmentAndDuration(
    prices: CoursePriceLike[] | undefined,
    segment: string | null | undefined,
    durationHours: number,
): number {
    if (!prices?.length) {
        return 0;
    }

    const duration = normalizeDurationHours(durationHours);
    const forDuration = prices.filter((p) => normalizeDurationHours(p.duration_hours) === duration);
    if (forDuration.length > 0) {
        return tuitionFromSchoolSegment(forDuration, segment);
    }

    return tuitionFromSchoolSegment(prices, segment);
}

export type TuitionByDuration = {
    durationHours: number;
    durationLabel: string;
    tuition: number;
};

/** 依學段列出各時數的參考學費（班級學生名單等） */
export function tuitionAmountsByDurationForSegment(
    prices: CoursePriceLike[] | undefined,
    segment: string | null | undefined,
): TuitionByDuration[] {
    return distinctDurationHours(prices).map((durationHours) => ({
        durationHours,
        durationLabel: formatDurationHours(durationHours),
        tuition: tuitionFromSchoolSegmentAndDuration(prices, segment, durationHours),
    }));
}

function formatTierPart(level: string | null, tuition: number, showDuration: boolean, durationHours?: number): string {
    const levelLabel = level == null || level === '' ? '不分' : level;
    if (showDuration) {
        return `${levelLabel} ${formatDurationHours(durationHours)} ${tuition}`;
    }

    return `${levelLabel} ${tuition}`;
}

/** 課程列表等：多種時數時依時數分行；單一時數不顯示 hr */
export function formatCoursePriceDisplayLines(prices: CoursePriceLike[] | undefined): string[] {
    if (!prices?.length) {
        return [];
    }

    const durations = distinctDurationHours(prices);
    const multi = durations.length > 1;

    if (!multi) {
        return [
            prices
                .map((p) => formatTierPart(p.level, p.tuition, false))
                .join('、'),
        ];
    }

    return durations.map((d) => {
        const tiers = prices
            .filter((p) => normalizeDurationHours(p.duration_hours) === d)
            .map((p) => formatTierPart(p.level, p.tuition, false));

        return `${formatDurationHours(d)}：${tiers.join('、')}`;
    });
}

/** 將課程的多筆學段／學費格式化成一行顯示文字（下拉選單等） */
export function formatCoursePriceTiers(prices: CoursePriceLike[] | undefined): string {
    const lines = formatCoursePriceDisplayLines(prices);
    if (lines.length === 0) {
        return '';
    }

    return lines.join('；');
}

/** 下拉選單：僅類別與課程名，避免 option 過長撐破版面 */
export function formatCourseSelectLabel(course: CourseOptionLike): string {
    return `${course.course_category.name} / ${course.name}`;
}

/** 詳細顯示：類別名 + 課程名 + 學段學費摘要 */
export function formatCourseOptionLabel(course: CourseOptionLike): string {
    const tiers = formatCoursePriceTiers(course.course_prices);
    const cat = course.course_category.name;

    return tiers ? `${cat} / ${course.name}（${tiers}）` : `${cat} / ${course.name}`;
}

/** 列表課程欄：主標題與學費摘要（多種時數時各時數一行） */
export function formatCourseCellParts(course: CourseOptionLike): { title: string; tierLines: string[] } {
    const title = `${course.course_category.name} / ${course.name}`;

    return { title, tierLines: formatCoursePriceDisplayLines(course.course_prices) };
}
