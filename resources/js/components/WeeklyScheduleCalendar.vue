<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { classroomCalendarSurface } from '@/lib/classroomColor';

type CourseCategory = { name: string };
type Course = { name: string; course_category: CourseCategory | null } | null;
type Teacher = { name: string } | null;

export type WeeklyScheduleClassroom = {
    id: number;
    name: string;
    color: string | null;
    start_date: string | null;
    end_date: string | null;
    /** 後端保證：true = 未設定開課區間；false = 有設定，須依 teaching_periods（與舊欄位）過濾日期 */
    date_range_unrestricted?: boolean;
    teaching_periods?: Array<{ start_date: string | null; end_date: string | null }>;
    schedules: Array<{
        weekday: number;
        start_time: string;
        end_time: string;
        course?: Course;
    }>;
    extra_sessions?: Array<{ date: string; start_time: string; end_time: string }> | null;
    course: Course;
    teacher: Teacher;
};

type PeriodRow = { start_date: string | null; end_date: string | null };

type CalendarBlock = {
    key: string;
    classroom_id: number;
    name: string;
    color: string | null;
    weekday: number;
    start_time: string;
    end_time: string;
    start_date: string | null;
    end_date: string | null;
    date_range_unrestricted?: boolean;
    dateRangeUnrestricted?: boolean;
    teaching_periods?: Array<PeriodRow>;
    teachingPeriods?: Array<PeriodRow>;
    specific_date: string | null;
    course: Course;
    teacher: Teacher;
};

const props = withDefaults(
    defineProps<{
        scheduleClassrooms: WeeklyScheduleClassroom[];
        showTeacherInBlock?: boolean;
        emptyMessage?: string;
        canFilterByTeacher?: boolean;
        teacherOptions?: Array<{ id: number; name: string }>;
        teacherId?: string;
    }>(),
    {
        showTeacherInBlock: true,
        emptyMessage:
            '尚無「上課中」的班級，請先到班級管理新增或啟用班級並填寫星期與上課時間；臨時加課可於加課選單建立。',
        teacherId: '',
    },
);

const emit = defineEmits<{
    'update:teacherId': [value: string];
}>();

const scheduleBlocks = computed<CalendarBlock[]>(() =>
    props.scheduleClassrooms.flatMap((c, index) => {
        const fromSchedules = (c.schedules ?? []).map((s, sIndex) => ({
            key: `${c.id}-${index}-r${sIndex}`,
            classroom_id: c.id,
            name: c.name,
            color: c.color ?? null,
            weekday: s.weekday,
            start_time: s.start_time,
            end_time: s.end_time,
            start_date: c.start_date,
            end_date: c.end_date,
            date_range_unrestricted: c.date_range_unrestricted,
            dateRangeUnrestricted: (c as { dateRangeUnrestricted?: boolean }).dateRangeUnrestricted,
            teaching_periods: c.teaching_periods,
            teachingPeriods: (c as { teachingPeriods?: PeriodRow[] }).teachingPeriods,
            specific_date: null as string | null,
            course: s.course ?? c.course,
            teacher: c.teacher,
        }));
        const fromExtras = (c.extra_sessions ?? []).map((ex, exIndex) => {
            const dateStr = typeof ex.date === 'string' ? ex.date.slice(0, 10) : '';
            let wd = 1;
            if (dateStr) {
                const d = new Date(`${dateStr}T12:00:00`);
                if (!Number.isNaN(d.getTime())) {
                    wd = toDbWeekday(d);
                }
            }
            return {
                key: `${c.id}-${index}-x${exIndex}`,
                classroom_id: c.id,
                name: c.name,
                color: c.color ?? null,
                weekday: wd,
                start_time: ex.start_time,
                end_time: ex.end_time,
                start_date: c.start_date,
                end_date: c.end_date,
                date_range_unrestricted: c.date_range_unrestricted,
                dateRangeUnrestricted: (c as { dateRangeUnrestricted?: boolean }).dateRangeUnrestricted,
                teaching_periods: c.teaching_periods,
                teachingPeriods: (c as { teachingPeriods?: PeriodRow[] }).teachingPeriods,
                specific_date: dateStr || null,
                course: c.course,
                teacher: c.teacher,
            };
        });
        return [...fromSchedules, ...fromExtras];
    }),
);

const toDbWeekday = (date: Date): number => {
    const d = date.getDay();

    return d === 0 ? 7 : d;
};

const pad2 = (n: number) => String(n).padStart(2, '0');

const toYmd = (date: Date): string =>
    `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;

const startOfMonday = (date: Date): Date => {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    const day = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);

    return d;
};

const addDays = (date: Date, days: number): Date => {
    const d = new Date(date);
    d.setDate(d.getDate() + days);

    return d;
};

const weekOffset = ref(0);

const mondayOfViewWeek = computed(() => {
    const base = new Date();
    base.setDate(base.getDate() + weekOffset.value * 7);

    return startOfMonday(base);
});

const weekDays = computed(() => {
    const start = mondayOfViewWeek.value;

    return Array.from({ length: 7 }, (_, i) => addDays(start, i));
});

const weekdayLabels = ['週一', '週二', '週三', '週四', '週五', '週六', '週日'];

const weekRangeTitle = computed(() => {
    const first = weekDays.value[0]!;
    const last = weekDays.value[6]!;

    return `${first.getFullYear()}/${pad2(first.getMonth() + 1)}/${pad2(first.getDate())} — ${last.getFullYear()}/${pad2(last.getMonth() + 1)}/${pad2(last.getDate())}`;
});

const isSameLocalDay = (a: Date, b: Date): boolean =>
    a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

const isToday = (d: Date): boolean => isSameLocalDay(d, new Date());

const sliceYmd = (s: string | null | undefined): string => {
    if (!s) {
        return '';
    }

    return s.slice(0, 10);
};

const getExplicitUnrestricted = (c: CalendarBlock): boolean | undefined =>
    c.date_range_unrestricted ?? c.dateRangeUnrestricted;

const periodRowsForBlock = (c: CalendarBlock): PeriodRow[] =>
    (c.teaching_periods ?? c.teachingPeriods ?? []).filter((p) => p?.start_date || p?.end_date);

const ymdMatchesPeriodUnion = (ymd: string, effective: PeriodRow[]): boolean =>
    effective.some((p) => {
        const s = sliceYmd(p.start_date);
        const e = sliceYmd(p.end_date);
        if (s && ymd < s) {
            return false;
        }
        if (e && ymd > e) {
            return false;
        }

        return true;
    });

/** 依開課區間判斷該日是否應顯示（含加課單日）。有 date_range_unrestricted 時與後端一致，避免誤把缺欄位當成「全年」。 */
const ymdAllowedByClassDateRange = (ymd: string, c: CalendarBlock): boolean => {
    const explicit = getExplicitUnrestricted(c);
    const legacyStart = c.start_date ?? null;
    const legacyEnd = c.end_date ?? null;
    const fromApi = periodRowsForBlock(c);

    if (explicit === true) {
        return true;
    }

    if (explicit === false) {
        if (fromApi.length > 0) {
            return ymdMatchesPeriodUnion(ymd, fromApi);
        }
        const legacyOnly =
            legacyStart || legacyEnd ? [{ start_date: legacyStart, end_date: legacyEnd }] : [];
        if (legacyOnly.length > 0) {
            return ymdMatchesPeriodUnion(ymd, legacyOnly);
        }

        return false;
    }

    const effective =
        fromApi.length > 0
            ? fromApi
            : legacyStart || legacyEnd
              ? [{ start_date: legacyStart, end_date: legacyEnd }]
              : [];
    if (effective.length === 0) {
        return true;
    }

    return ymdMatchesPeriodUnion(ymd, effective);
};

const visibleOnDate = (c: CalendarBlock, date: Date): boolean => {
    const ymd = toYmd(date);
    if (c.specific_date) {
        if (ymd !== c.specific_date) {
            return false;
        }

        return ymdAllowedByClassDateRange(ymd, c);
    }
    if (toDbWeekday(date) !== c.weekday) {
        return false;
    }

    return ymdAllowedByClassDateRange(ymd, c);
};

const classroomsForDay = (date: Date) =>
    scheduleBlocks.value.filter((c) => visibleOnDate(c, date)).sort((a, b) => {
        const ta = a.start_time ?? '';
        const tb = b.start_time ?? '';

        return ta.localeCompare(tb);
    });

const unscheduledClassrooms = computed(() =>
    props.scheduleClassrooms.filter(
        (c) => (c.schedules ?? []).length === 0 && !(c.extra_sessions?.length),
    ),
);

const PX_PER_HOUR = 56;
const DEFAULT_DAY_START_HOUR = 8;
const DEFAULT_DAY_END_HOUR = 22;
const GRID_BOTTOM_PADDING_PX = 8;

const dayStartHour = computed(() => {
    let minHour = DEFAULT_DAY_START_HOUR;
    for (const block of scheduleBlocks.value) {
        if (!block.start_time) {
            continue;
        }
        minHour = Math.min(minHour, Math.floor(parseTimeToMinutes(block.start_time) / 60));
    }

    return Math.max(6, minHour);
});

const dayEndHour = computed(() => {
    let maxHour = DEFAULT_DAY_END_HOUR;
    for (const block of scheduleBlocks.value) {
        if (!block.end_time) {
            continue;
        }
        maxHour = Math.max(maxHour, Math.ceil(parseTimeToMinutes(block.end_time) / 60));
    }

    return Math.min(23, Math.max(maxHour, DEFAULT_DAY_END_HOUR));
});

const hoursDisplayed = computed(() =>
    Array.from({ length: dayEndHour.value - dayStartHour.value }, (_, i) => dayStartHour.value + i),
);

const gridHeightPx = computed(() => hoursDisplayed.value.length * PX_PER_HOUR + GRID_BOTTOM_PADDING_PX);

const isViewingCurrentWeek = computed(() => weekOffset.value === 0);

const currentTimeIndicator = computed(() => {
    if (!isViewingCurrentWeek.value) {
        return null;
    }
    const todayCol = weekDays.value.findIndex((d) => isToday(d));
    if (todayCol < 0) {
        return null;
    }
    const now = new Date();
    const minutes = now.getHours() * 60 + now.getMinutes();
    const dayStartM = dayStartHour.value * 60;
    const dayEndM = dayEndHour.value * 60;
    if (minutes < dayStartM || minutes > dayEndM) {
        return null;
    }

    return {
        col: todayCol,
        top: ((minutes - dayStartM) / 60) * PX_PER_HOUR,
    };
});

const isWeekendColumn = (col: number): boolean => col >= 5;

const parseTimeToMinutes = (t: string): number => {
    const [h, m] = t.split(':').map((x) => Number(x));
    const hh = Number.isFinite(h) ? h : 0;
    const mm = Number.isFinite(m) ? m : 0;

    return hh * 60 + mm;
};

const formatHm = (t: string | null): string => {
    if (!t) {
        return '';
    }
    const [h, m] = t.split(':');

    return `${h}:${(m ?? '00').slice(0, 2)}`;
};

const courseLabel = (c: { course: Course }): string => {
    const cat = c.course?.course_category?.name;
    const n = c.course?.name;
    if (cat && n) {
        return `${cat} / ${n}`;
    }

    return n ?? '課程';
};

type BlockLayout = {
    top: number;
    heightPx: number;
    clampedStart: number;
    visibleEnd: number;
};

type CalendarCluster = {
    key: string;
    blocks: CalendarBlock[];
    top: number;
    height: number;
    compact: boolean;
    isMulti: boolean;
    zIndex: number;
};

const COLLAPSED_CLUSTER_HEIGHT = 44;

const blockLayoutFor = (c: CalendarBlock, startHour: number, endHour: number): BlockLayout => {
    const dayStartM = startHour * 60;
    const dayEndM = endHour * 60;
    const startM = parseTimeToMinutes(c.start_time!);
    const endM = parseTimeToMinutes(c.end_time!);
    const clampedStart = Math.max(startM, dayStartM);
    const rawEnd = Math.min(endM, dayEndM);
    const clampedEnd = Math.max(rawEnd, clampedStart + 15);
    const visibleEnd = Math.min(clampedEnd, dayEndM);
    const top = ((clampedStart - dayStartM) / 60) * PX_PER_HOUR;
    const heightPx = Math.max(((visibleEnd - clampedStart) / 60) * PX_PER_HOUR, 32);

    return { top, heightPx, clampedStart, visibleEnd };
};

const layoutsOverlap = (a: BlockLayout, b: BlockLayout): boolean =>
    a.clampedStart < b.visibleEnd && a.visibleEnd > b.clampedStart;

const clusterOverlapping = (list: CalendarBlock[], startHour: number, endHour: number): CalendarBlock[][] => {
    if (list.length === 0) {
        return [];
    }

    const sorted = [...list].sort(
        (a, b) => parseTimeToMinutes(a.start_time!) - parseTimeToMinutes(b.start_time!),
    );
    const layouts = new Map(sorted.map((c) => [c.key, blockLayoutFor(c, startHour, endHour)]));
    const parent = sorted.map((_, i) => i);

    const find = (i: number): number => {
        if (parent[i] !== i) {
            parent[i] = find(parent[i]!);
        }

        return parent[i]!;
    };

    const unite = (i: number, j: number): void => {
        const ri = find(i);
        const rj = find(j);
        if (ri !== rj) {
            parent[ri] = rj;
        }
    };

    for (let i = 0; i < sorted.length; i++) {
        for (let j = i + 1; j < sorted.length; j++) {
            const li = layouts.get(sorted[i]!.key)!;
            const lj = layouts.get(sorted[j]!.key)!;
            if (layoutsOverlap(li, lj)) {
                unite(i, j);
            }
        }
    }

    const groups = new Map<number, CalendarBlock[]>();
    sorted.forEach((block, i) => {
        const root = find(i);
        if (!groups.has(root)) {
            groups.set(root, []);
        }
        groups.get(root)!.push(block);
    });

    return [...groups.values()];
};

const computeDayClusters = (list: CalendarBlock[], ymd: string, startHour: number, endHour: number): CalendarCluster[] => {
    const groups = clusterOverlapping(list, startHour, endHour);

    return groups.map((blocks, index) => {
        const layouts = blocks.map((b) => blockLayoutFor(b, startHour, endHour));
        const top = Math.min(...layouts.map((l) => l.top));
        const isMulti = blocks.length > 1;

        if (isMulti) {
            return {
                key: `${ymd}-cluster-${index}`,
                blocks,
                top,
                height: COLLAPSED_CLUSTER_HEIGHT,
                compact: true,
                isMulti: true,
                zIndex: 30 + index,
            };
        }

        const layout = layouts[0]!;

        return {
            key: blocks[0]!.key,
            blocks,
            top: layout.top,
            height: layout.heightPx,
            compact: layout.heightPx < 56,
            isMulti: false,
            zIndex: 20 + index,
        };
    });
};

const clustersByYmd = computed(() => {
    const result = new Map<string, CalendarCluster[]>();
    const startHour = dayStartHour.value;
    const endHour = dayEndHour.value;
    for (const day of weekDays.value) {
        const ymd = toYmd(day);
        result.set(ymd, computeDayClusters(classroomsForDay(day), ymd, startHour, endHour));
    }

    return result;
});

const expandedClusterKey = ref<string | null>(null);
const popoverPos = ref<{
    top: number;
    left: number;
    width: number;
    placement: 'below' | 'above';
} | null>(null);
let ignoreNextDocumentClose = false;

const expandedCluster = computed(() => {
    if (expandedClusterKey.value === null) {
        return null;
    }
    for (const clusters of clustersByYmd.value.values()) {
        const found = clusters.find((c) => c.key === expandedClusterKey.value);
        if (found) {
            return found;
        }
    }

    return null;
});

const openClusterPopover = (key: string, event: MouseEvent) => {
    event.stopPropagation();
    if (expandedClusterKey.value === key) {
        closeCluster();

        return;
    }

    const el = event.currentTarget as HTMLElement;
    const rect = el.getBoundingClientRect();
    const maxPopoverH = 224;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const placement = spaceBelow >= maxPopoverH || spaceBelow >= spaceAbove ? 'below' : 'above';

    popoverPos.value = {
        left: rect.left,
        width: Math.max(rect.width, 220),
        top: placement === 'below' ? rect.bottom + 4 : rect.top - 4,
        placement,
    };
    expandedClusterKey.value = key;
    ignoreNextDocumentClose = true;
    nextTick(() => {
        ignoreNextDocumentClose = false;
    });
};

const closeCluster = () => {
    if (ignoreNextDocumentClose) {
        return;
    }
    expandedClusterKey.value = null;
    popoverPos.value = null;
};

onMounted(() => {
    document.addEventListener('click', closeCluster);
});

onUnmounted(() => {
    document.removeEventListener('click', closeCluster);
});

watch(weekOffset, () => {
    closeCluster();
});

watch(
    () => props.teacherId,
    () => {
        closeCluster();
    },
);

const clusterTimeRange = (cluster: CalendarCluster): string => {
    const starts = cluster.blocks.map((b) => parseTimeToMinutes(b.start_time!));
    const ends = cluster.blocks.map((b) => parseTimeToMinutes(b.end_time!));
    const toHm = (minutes: number) => {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;

        return `${h}:${String(m).padStart(2, '0')}`;
    };

    return `${toHm(Math.min(...starts))} — ${toHm(Math.max(...ends))}`;
};

const blockTimeLabel = (c: CalendarBlock): string =>
    `${formatHm(c.start_time)}—${formatHm(c.end_time)}${teacherSuffix(c)}`;

const teacherSuffix = (c: CalendarBlock): string =>
    props.showTeacherInBlock && c.teacher?.name ? ` · ${c.teacher.name}` : '';
</script>

<template>
    <div class="space-y-4">
        <!-- 週次工具列 -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 shadow-sm lg:flex-row lg:items-end lg:justify-between lg:gap-4 lg:px-5"
        >
            <div class="min-w-0 shrink-0">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">本週區間</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums tracking-tight text-foreground lg:text-lg">
                    {{ weekRangeTitle }}
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-3 lg:shrink-0">
                <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1 sm:min-w-[10rem]">
                    <label class="text-sm font-medium" for="calendar_teacher_id">老師</label>
                    <select
                        id="calendar_teacher_id"
                        class="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-xs"
                        :value="teacherId"
                        @change="emit('update:teacherId', ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">全部老師</option>
                        <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                    </select>
                </div>
                <div class="inline-flex w-full shrink-0 rounded-lg border border-input bg-muted/40 p-1 sm:w-auto">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="flex-1 rounded-md sm:flex-none"
                        @click="weekOffset--"
                    >
                        上週
                    </Button>
                    <Button
                        type="button"
                        :variant="weekOffset === 0 ? 'secondary' : 'ghost'"
                        size="sm"
                        class="flex-1 rounded-md sm:flex-none"
                        @click="weekOffset = 0"
                    >
                        本週
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="flex-1 rounded-md sm:flex-none"
                        @click="weekOffset++"
                    >
                        下週
                    </Button>
                </div>
            </div>
        </div>

        <!-- 手機／平板：直向每日列表，不需左右滑動 -->
        <div class="space-y-3 lg:hidden">
            <section
                v-for="(day, col) in weekDays"
                :key="`mobile-day-${col}`"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            >
                <header
                    class="border-b border-sidebar-border/70 px-4 py-2.5 text-center text-sm font-medium"
                    :class="isToday(day) ? 'bg-primary/10 text-primary' : 'bg-muted/30 text-foreground'"
                >
                    <span>{{ weekdayLabels[col] }}</span>
                    <span class="ml-2 text-xs font-normal tabular-nums text-muted-foreground">
                        {{ day.getMonth() + 1 }}/{{ day.getDate() }}
                    </span>
                </header>
                <ul v-if="classroomsForDay(day).length" class="divide-y divide-sidebar-border/70">
                    <li
                        v-for="c in classroomsForDay(day)"
                        :key="`m-${c.key}`"
                        class="border-l-4 px-4 py-3"
                        :style="classroomCalendarSurface(c.color)"
                    >
                        <div class="font-semibold leading-snug text-foreground">
                            <span
                                v-if="c.specific_date"
                                class="mr-1.5 rounded bg-background/70 px-1 py-0.5 text-[10px] font-normal"
                            >
                                加課
                            </span>
                            {{ c.name }}
                        </div>
                        <div class="mt-1 text-sm tabular-nums text-muted-foreground">
                            {{ formatHm(c.start_time) }} — {{ formatHm(c.end_time) }}{{ teacherSuffix(c) }}
                        </div>
                        <div class="mt-0.5 text-sm break-words text-muted-foreground">{{ courseLabel(c) }}</div>
                    </li>
                </ul>
                <p v-else class="px-4 py-6 text-center text-sm text-muted-foreground">本日無排課</p>
            </section>
        </div>

        <!-- 電腦版：週曆格線 -->
        <div
            class="hidden w-full overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card shadow-sm ring-1 ring-black/[0.03] lg:block"
        >
            <!-- 表頭列 -->
            <div class="flex border-b border-sidebar-border/70 bg-muted/20">
                <div class="w-14 shrink-0 border-r border-sidebar-border/70" />
                <div class="grid flex-1 grid-cols-7 divide-x divide-sidebar-border/70">
                    <div
                        v-for="(day, col) in weekDays"
                        :key="`head-${col}`"
                        class="px-2 py-3 text-center"
                        :class="
                            isToday(day)
                                ? 'bg-primary/8'
                                : isWeekendColumn(col)
                                  ? 'bg-muted/30'
                                  : 'bg-card'
                        "
                    >
                        <div
                            class="text-xs font-medium"
                            :class="isToday(day) ? 'text-primary' : 'text-muted-foreground'"
                        >
                            {{ weekdayLabels[col] }}
                        </div>
                        <div
                            class="mt-1 inline-flex min-w-[2rem] items-center justify-center rounded-full text-sm font-semibold tabular-nums"
                            :class="
                                isToday(day)
                                    ? 'bg-primary px-2 py-0.5 text-primary-foreground shadow-sm'
                                    : 'text-foreground'
                            "
                        >
                            {{ day.getDate() }}
                        </div>
                        <div class="mt-0.5 text-[10px] tabular-nums text-muted-foreground">
                            {{ day.getMonth() + 1 }}月
                        </div>
                    </div>
                </div>
            </div>

            <!-- 格線主體 -->
            <div class="flex">
                <div
                    class="flex w-14 shrink-0 flex-col border-r border-sidebar-border/70 bg-gradient-to-b from-muted/40 to-muted/20 pt-0 text-right"
                >
                    <div
                        v-for="h in hoursDisplayed"
                        :key="h"
                        class="relative border-b border-transparent pr-2.5 text-[11px] font-medium tabular-nums text-muted-foreground"
                        :style="{ height: `${PX_PER_HOUR}px` }"
                    >
                        <span class="absolute -top-2.5 right-2.5">{{ pad2(h) }}:00</span>
                    </div>
                </div>

                <div class="grid flex-1 grid-cols-7 divide-x divide-sidebar-border/70">
                    <div
                        v-for="(day, col) in weekDays"
                        :key="col"
                        class="relative min-w-0 overflow-visible"
                        :class="
                            isToday(day)
                                ? 'bg-primary/[0.025]'
                                : isWeekendColumn(col)
                                  ? 'bg-muted/10'
                                  : 'bg-card'
                        "
                    >
                        <div class="relative" :style="{ height: `${gridHeightPx}px` }">
                            <!-- 整點虛線 -->
                            <div
                                v-for="h in hoursDisplayed"
                                :key="`${col}-${h}`"
                                class="pointer-events-none absolute left-0 right-0 border-b border-dashed border-border/40"
                                :style="{ top: `${(h - dayStartHour) * PX_PER_HOUR}px` }"
                            />
                            <!-- 半點細線 -->
                            <div
                                v-for="h in hoursDisplayed"
                                :key="`${col}-half-${h}`"
                                class="pointer-events-none absolute left-0 right-0 border-b border-border/20"
                                :style="{ top: `${(h - dayStartHour) * PX_PER_HOUR + PX_PER_HOUR / 2}px` }"
                            />

                            <!-- 現在時間指示線 -->
                            <div
                                v-if="currentTimeIndicator && currentTimeIndicator.col === col"
                                class="pointer-events-none absolute left-0 right-0 z-30"
                                :style="{ top: `${currentTimeIndicator.top}px` }"
                            >
                                <div class="relative h-0.5 bg-red-500/80">
                                    <div class="absolute -left-0.5 -top-1 h-2.5 w-2.5 rounded-full bg-red-500" />
                                </div>
                            </div>

                            <template v-for="cluster in clustersByYmd.get(toYmd(day)) ?? []" :key="cluster.key">
                                <!-- 同時段多堂：收合顯示，點擊展開 -->
                                <div
                                    v-if="cluster.isMulti"
                                    class="absolute left-0.5 right-0.5 box-border cursor-pointer rounded-lg border border-dashed px-2 py-1 text-xs leading-snug shadow-sm transition-shadow hover:shadow-md"
                                    :class="
                                        expandedClusterKey === cluster.key
                                            ? 'bg-primary/10 ring-2 ring-primary/40'
                                            : 'bg-muted/60 hover:bg-muted/80'
                                    "
                                    :style="{
                                        top: `${cluster.top}px`,
                                        height: `${cluster.height}px`,
                                        zIndex: expandedClusterKey === cluster.key ? 60 : cluster.zIndex,
                                    }"
                                    @click.stop="openClusterPopover(cluster.key, $event)"
                                >
                                    <div class="flex min-h-0 items-center gap-1.5">
                                        <span
                                            class="inline-flex shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground"
                                        >
                                            {{ cluster.blocks.length }} 堂
                                        </span>
                                        <span class="min-w-0 truncate font-medium text-foreground">
                                            {{ clusterTimeRange(cluster) }}
                                        </span>
                                    </div>
                                    <div class="mt-0.5 truncate text-[10px] text-muted-foreground">
                                        點擊展開 · {{ cluster.blocks.map((b) => b.name).join('、') }}
                                    </div>
                                </div>

                                <!-- 單堂：維持原本完整區塊 -->
                                <div
                                    v-else
                                    class="absolute left-0.5 right-0.5 box-border min-w-0 rounded-lg border px-2 py-1 text-xs leading-snug shadow-sm transition-shadow hover:shadow-md"
                                    :title="`${cluster.blocks[0]!.name} ${blockTimeLabel(cluster.blocks[0]!)}`"
                                    :style="{
                                        top: `${cluster.top}px`,
                                        height: `${cluster.height}px`,
                                        zIndex: cluster.zIndex,
                                        ...classroomCalendarSurface(cluster.blocks[0]!.color),
                                    }"
                                >
                                    <template v-if="cluster.compact">
                                        <div class="truncate font-semibold text-foreground">
                                            <span
                                                v-if="cluster.blocks[0]!.specific_date"
                                                class="mr-1 rounded bg-background/80 px-1 py-px text-[9px] font-medium text-foreground/80"
                                            >
                                                加課
                                            </span>
                                            {{ cluster.blocks[0]!.name }}
                                        </div>
                                        <div class="truncate text-[10px] tabular-nums text-muted-foreground">
                                            {{ blockTimeLabel(cluster.blocks[0]!) }}
                                        </div>
                                    </template>
                                    <div v-else class="flex min-h-0 flex-col gap-1">
                                        <div class="font-semibold leading-tight text-foreground">
                                            <span
                                                v-if="cluster.blocks[0]!.specific_date"
                                                class="mr-1.5 rounded-md bg-background/80 px-1.5 py-0.5 text-[10px] font-medium text-foreground/85"
                                            >
                                                加課
                                            </span>
                                            {{ cluster.blocks[0]!.name }}
                                        </div>
                                        <div class="tabular-nums text-[11px] text-muted-foreground">
                                            {{ formatHm(cluster.blocks[0]!.start_time) }} —
                                            {{ formatHm(cluster.blocks[0]!.end_time) }}{{ teacherSuffix(cluster.blocks[0]!) }}
                                        </div>
                                        <div class="line-clamp-2 text-[11px] leading-tight text-muted-foreground/90">
                                            {{ courseLabel(cluster.blocks[0]!) }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="unscheduledClassrooms.length"
            class="rounded-xl border border-sidebar-border/70 bg-muted/10 p-4 text-sm"
        >
            <h2 class="mb-2 font-medium text-foreground">未設定完整時段（無法顯示於格線）</h2>
            <ul class="list-inside list-disc space-y-1 text-muted-foreground">
                <li v-for="c in unscheduledClassrooms" :key="c.id">
                    {{ c.name }}
                    <span v-if="showTeacherInBlock && c.teacher">（{{ c.teacher.name }}）</span>
                </li>
            </ul>
        </div>

        <p v-if="!scheduleBlocks.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
            {{ emptyMessage }}
        </p>

        <Teleport to="body">
            <div
                v-if="expandedCluster && popoverPos"
                class="fixed z-[200] max-h-56 overflow-y-auto rounded-lg border border-sidebar-border/70 bg-card p-2 shadow-xl"
                :style="{
                    left: `${popoverPos.left}px`,
                    top: `${popoverPos.top}px`,
                    width: `${popoverPos.width}px`,
                    transform: popoverPos.placement === 'above' ? 'translateY(-100%)' : undefined,
                }"
                @click.stop
            >
                <p class="mb-2 px-1 text-xs font-medium text-muted-foreground">
                    同時段 {{ expandedCluster.blocks.length }} 堂課
                </p>
                <ul class="space-y-2">
                    <li
                        v-for="c in expandedCluster.blocks"
                        :key="c.key"
                        class="rounded-md border-l-4 px-2 py-1.5"
                        :style="classroomCalendarSurface(c.color)"
                    >
                        <div class="font-semibold text-foreground">
                            <span
                                v-if="c.specific_date"
                                class="mr-1 rounded bg-background/80 px-1 py-px text-[9px] font-medium"
                            >
                                加課
                            </span>
                            {{ c.name }}
                        </div>
                        <div class="mt-0.5 text-[11px] tabular-nums text-muted-foreground">
                            {{ formatHm(c.start_time) }} — {{ formatHm(c.end_time) }}{{ teacherSuffix(c) }}
                        </div>
                        <div class="text-[11px] text-muted-foreground">{{ courseLabel(c) }}</div>
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
