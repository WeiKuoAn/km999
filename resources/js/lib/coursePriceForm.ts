import { type CoursePriceLike, formatDurationHours } from '@/lib/courseLabel';

export type CoursePriceTierInput = {
    level: string;
    tuition: number;
};

export type DurationGroupInput = {
    duration_hours: number;
    tiers: CoursePriceTierInput[];
};

export type CoursePriceTierPayload = {
    level: string;
    duration_hours: number;
    tuition: number;
};

const defaultSingleTier = (): CoursePriceTierInput => ({ level: '', tuition: 600 });

export { defaultSingleTier };

export function normalizeDuration(hours: number): number {
    return Math.round(hours * 10) / 10;
}

function groupByDuration(prices: CoursePriceLike[]): DurationGroupInput[] {
    const map = new Map<number, CoursePriceTierInput[]>();

    for (const p of prices) {
        const d = normalizeDuration(p.duration_hours ?? 1);
        const list = map.get(d) ?? [];
        list.push({ level: p.level ?? '', tuition: p.tuition });
        map.set(d, list);
    }

    return [...map.entries()]
        .sort(([a], [b]) => a - b)
        .map(([duration_hours, tiers]) => ({ duration_hours, tiers }));
}

/** 從後端價目還原表單狀態 */
export function parseCoursePriceFormState(prices: CoursePriceLike[] | undefined): {
    hasMultipleDurations: boolean;
    singleDurationHours: number;
    singleTiers: CoursePriceTierInput[];
    durationGroups: DurationGroupInput[];
} {
    const groups = groupByDuration(prices ?? []);

    if (groups.length === 0) {
        return {
            hasMultipleDurations: false,
            singleDurationHours: 1,
            singleTiers: [defaultSingleTier()],
            durationGroups: [
                { duration_hours: 1, tiers: [defaultSingleTier()] },
                { duration_hours: 1.5, tiers: [{ level: '', tuition: 0 }] },
            ],
        };
    }

    if (groups.length === 1) {
        const only = groups[0]!;

        return {
            hasMultipleDurations: false,
            singleDurationHours: only.duration_hours,
            singleTiers: only.tiers,
            durationGroups: [
                { duration_hours: only.duration_hours, tiers: only.tiers.map((t) => ({ ...t })) },
                { duration_hours: suggestNextDuration(groups.map((g) => g.duration_hours)), tiers: [{ level: '', tuition: 0 }] },
            ],
        };
    }

    return {
        hasMultipleDurations: true,
        singleDurationHours: groups[0]!.duration_hours,
        singleTiers: groups[0]!.tiers.map((t) => ({ ...t })),
        durationGroups: groups.map((g) => ({
            duration_hours: g.duration_hours,
            tiers: g.tiers.map((t) => ({ ...t })),
        })),
    };
}

/** 表單狀態 flatten 成 API 的 tiers */
export function flattenCoursePriceTiers(
    hasMultipleDurations: boolean,
    singleDurationHours: number,
    singleTiers: CoursePriceTierInput[],
    durationGroups: DurationGroupInput[],
): CoursePriceTierPayload[] {
    if (!hasMultipleDurations) {
        const duration = normalizeDuration(singleDurationHours);

        return singleTiers.map((t) => ({
            level: t.level,
            duration_hours: duration,
            tuition: t.tuition,
        }));
    }

    return durationGroups.flatMap((g) =>
        g.tiers.map((t) => ({
            level: t.level,
            duration_hours: normalizeDuration(g.duration_hours),
            tuition: t.tuition,
        })),
    );
}

/** 建議下一個未使用的時數（小時，步進 0.5） */
export function suggestNextDuration(used: number[]): number {
    const set = new Set(used.map(normalizeDuration));
    for (let h = 0.5; h <= 8; h += 0.5) {
        const n = normalizeDuration(h);
        if (!set.has(n)) {
            return n;
        }
    }

    return normalizeDuration(Math.max(...used, 1) + 0.5);
}

export function durationLabel(hours: number): string {
    return formatDurationHours(hours);
}
