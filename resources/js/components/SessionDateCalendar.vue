<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { classroomCalendarSurface, normalizeClassroomHex } from '@/lib/classroomColor';
import {
    hasSession,
    isoWeekday,
    parseYmd,
    toggleSession,
    toYmd,
    type SessionEntry,
} from '@/lib/weekdayDates';

export type CalendarCourse = {
    id: number;
    name: string;
    weekdays: number[];
    color?: string | null;
};

type CourseLabel = {
    id: number;
    name: string;
    color: string;
};

const props = defineProps<{
    modelValue: SessionEntry[];
    startDate: string;
    /** 已選科目 */
    courses?: CalendarCourse[];
    /** 可瀏覽／點選的月數（含起算月） */
    monthSpan?: number;
    /** 國定假日／自訂連假（預選會略過；格子標示） */
    holidays?: Array<{ date: string; name: string }>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: SessionEntry[]];
}>();

const monthSpan = computed(() => props.monthSpan ?? 6);
const viewCursor = ref({ y: 0, m: 0 });
const pickerOpen = ref(false);
const pickerDate = ref<string | null>(null);

const holidayMap = computed(() => {
    const map = new Map<string, string>();
    for (const h of props.holidays ?? []) {
        map.set(h.date.slice(0, 10), h.name);
    }
    return map;
});

const syncViewToStart = () => {
    const d = parseYmd(props.startDate || toYmd(new Date()));
    if (Number.isNaN(d.getTime())) {
        const now = new Date();
        viewCursor.value = { y: now.getFullYear(), m: now.getMonth() + 1 };
        return;
    }
    viewCursor.value = { y: d.getFullYear(), m: d.getMonth() + 1 };
};

watch(() => props.startDate, syncViewToStart, { immediate: true });

const courseMap = computed(() => {
    const map = new Map<number, CalendarCourse>();
    for (const c of props.courses ?? []) {
        map.set(c.id, c);
    }
    return map;
});

const entriesByDate = computed(() => {
    const map = new Map<string, SessionEntry[]>();
    for (const entry of props.modelValue) {
        const list = map.get(entry.date) ?? [];
        list.push(entry);
        map.set(entry.date, list);
    }
    return map;
});

const labelsForDate = (ymd: string): CourseLabel[] => {
    const entries = entriesByDate.value.get(ymd) ?? [];
    const out: CourseLabel[] = [];
    for (const entry of entries) {
        const course = courseMap.value.get(entry.course_id);
        if (!course) {
            continue;
        }
        out.push({
            id: course.id,
            name: course.name,
            color: normalizeClassroomHex(course.color),
        });
    }
    return out;
};

const minDate = computed(() => parseYmd(props.startDate || toYmd(new Date())));
const maxDate = computed(() => {
    const s = minDate.value;
    return new Date(s.getFullYear(), s.getMonth() + monthSpan.value, 0);
});

const canPrev = computed(() => {
    const s = minDate.value;
    return (
        viewCursor.value.y > s.getFullYear() ||
        (viewCursor.value.y === s.getFullYear() && viewCursor.value.m > s.getMonth() + 1)
    );
});

const canNext = computed(() => {
    const e = maxDate.value;
    return (
        viewCursor.value.y < e.getFullYear() ||
        (viewCursor.value.y === e.getFullYear() && viewCursor.value.m < e.getMonth() + 1)
    );
});

const shiftMonth = (delta: number) => {
    const d = new Date(viewCursor.value.y, viewCursor.value.m - 1 + delta, 1);
    viewCursor.value = { y: d.getFullYear(), m: d.getMonth() + 1 };
};

const monthLabel = computed(() => `${viewCursor.value.y} 年 ${viewCursor.value.m} 月`);

type Cell = {
    key: string;
    date: string | null;
    inMonth: boolean;
    disabled: boolean;
    selected: boolean;
    isHoliday: boolean;
    holidayName: string | null;
    dayNum: number;
    courseLabels: CourseLabel[];
    selectedStyle: Record<string, string> | undefined;
};

const cells = computed<Cell[]>(() => {
    const y = viewCursor.value.y;
    const m = viewCursor.value.m;
    const first = new Date(y, m - 1, 1);
    const startPad = (first.getDay() + 6) % 7; // Monday-first
    const daysInMonth = new Date(y, m, 0).getDate();
    const out: Cell[] = [];

    for (let i = 0; i < startPad; i++) {
        out.push({
            key: `pad-${i}`,
            date: null,
            inMonth: false,
            disabled: true,
            selected: false,
            isHoliday: false,
            holidayName: null,
            dayNum: 0,
            courseLabels: [],
            selectedStyle: undefined,
        });
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(y, m - 1, day);
        const ymd = toYmd(date);
        const beforeStart =
            date < new Date(minDate.value.getFullYear(), minDate.value.getMonth(), minDate.value.getDate());
        const afterEnd = date > maxDate.value;
        const labels = labelsForDate(ymd);
        const selected = labels.length > 0;
        const holidayName = holidayMap.value.get(ymd) ?? null;
        out.push({
            key: ymd,
            date: ymd,
            inMonth: true,
            disabled: beforeStart || afterEnd,
            selected,
            isHoliday: holidayName !== null,
            holidayName,
            dayNum: day,
            courseLabels: labels,
            selectedStyle: selected ? classroomCalendarSurface(labels[0]?.color ?? null) : undefined,
        });
    }

    while (out.length % 7 !== 0) {
        out.push({
            key: `tail-${out.length}`,
            date: null,
            inMonth: false,
            disabled: true,
            selected: false,
            isHoliday: false,
            holidayName: null,
            dayNum: 0,
            courseLabels: [],
            selectedStyle: undefined,
        });
    }

    return out;
});

const pickerCourses = computed(() => props.courses ?? []);

const pickerDateLabel = computed(() => {
    if (!pickerDate.value) {
        return '';
    }
    const d = parseYmd(pickerDate.value);
    const wd = ['一', '二', '三', '四', '五', '六', '日'][isoWeekday(d) - 1] ?? '';
    return `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()}（週${wd}）`;
});

const openPicker = (date: string) => {
    pickerDate.value = date;
    pickerOpen.value = true;
};

const applyToggle = (date: string, courseId: number) => {
    emit('update:modelValue', toggleSession(props.modelValue, date, courseId));
};

const onCellClick = (cell: Cell) => {
    if (!cell.date || cell.disabled) {
        return;
    }
    const courses = pickerCourses.value;
    if (courses.length === 0) {
        return;
    }
    if (courses.length === 1) {
        applyToggle(cell.date, courses[0]!.id);
        return;
    }
    // 已選且只有一科：直接取消該科
    if (cell.courseLabels.length === 1 && cell.selected) {
        applyToggle(cell.date, cell.courseLabels[0]!.id);
        return;
    }
    openPicker(cell.date);
};

const pickCourse = (courseId: number) => {
    if (!pickerDate.value) {
        return;
    }
    applyToggle(pickerDate.value, courseId);
};

const courseActiveOnPickerDate = (courseId: number) => {
    if (!pickerDate.value) {
        return false;
    }
    return hasSession(props.modelValue, pickerDate.value, courseId);
};

const sessionCount = computed(() => props.modelValue.length);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <Button type="button" variant="outline" size="sm" :disabled="!canPrev" @click="shiftMonth(-1)">
                上月
            </Button>
            <div class="text-sm font-medium">{{ monthLabel }}</div>
            <Button type="button" variant="outline" size="sm" :disabled="!canNext" @click="shiftMonth(1)">
                下月
            </Button>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
            <div>一</div>
            <div>二</div>
            <div>三</div>
            <div>四</div>
            <div>五</div>
            <div>六</div>
            <div>日</div>
        </div>

        <div class="grid grid-cols-7 gap-1">
            <button
                v-for="cell in cells"
                :key="cell.key"
                type="button"
                class="relative flex min-h-14 flex-col items-center justify-center gap-0.5 rounded-md border px-0.5 py-1 text-center text-sm transition sm:min-h-16"
                :class="[
                    !cell.inMonth ? 'invisible' : '',
                    cell.disabled
                        ? 'cursor-not-allowed border-transparent bg-transparent text-muted-foreground/40'
                        : cell.selected
                          ? 'hover:opacity-90'
                          : cell.isHoliday
                            ? 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-300 hover:bg-rose-100/80'
                            : 'border-transparent bg-white text-foreground hover:border-primary/40 hover:bg-accent/40',
                ]"
                :style="cell.selected && !cell.disabled ? cell.selectedStyle : undefined"
                :disabled="!cell.inMonth || cell.disabled"
                :title="
                    cell.courseLabels.length
                        ? cell.courseLabels.map((c) => c.name).join('、')
                        : cell.holidayName
                          ? `假日：${cell.holidayName}（仍可點選加課）`
                          : '點選加課'
                "
                @click="onCellClick(cell)"
            >
                <span v-if="cell.inMonth" class="leading-none tabular-nums">{{ cell.dayNum }}</span>
                <span
                    v-if="cell.inMonth && cell.courseLabels.length"
                    class="line-clamp-2 w-full text-center text-[9px] leading-tight sm:text-[10px]"
                >
                    <span
                        v-for="(label, i) in cell.courseLabels"
                        :key="label.id"
                        :style="{ color: label.color }"
                        class="font-medium"
                    >
                        <template v-if="i > 0">、</template>{{ label.name }}
                    </span>
                </span>
                <span
                    v-else-if="cell.inMonth && cell.holidayName"
                    class="line-clamp-2 w-full text-center text-[9px] leading-tight text-rose-700 sm:text-[10px]"
                >
                    {{ cell.holidayName }}
                </span>
            </button>
        </div>

        <p class="text-xs text-muted-foreground">
            已選 {{ sessionCount }} 堂。淡紅為假日（預選會略過，仍可手動加課）；點空白日可加課（多科時會問是哪一科）。
        </p>

        <Dialog v-model:open="pickerOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>選擇科目</DialogTitle>
                    <DialogDescription>
                        {{ pickerDateLabel }}｜點科目可加課或取消該科堂次。
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2 py-2">
                    <button
                        v-for="course in pickerCourses"
                        :key="course.id"
                        type="button"
                        class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition"
                        :class="
                            courseActiveOnPickerDate(course.id)
                                ? 'border-primary/40'
                                : 'hover:border-primary/30 hover:bg-accent/40'
                        "
                        :style="
                            courseActiveOnPickerDate(course.id)
                                ? classroomCalendarSurface(course.color)
                                : undefined
                        "
                        @click="pickCourse(course.id)"
                    >
                        <span
                            class="inline-block size-3 shrink-0 rounded-sm border border-black/10"
                            :style="{ backgroundColor: normalizeClassroomHex(course.color) }"
                        />
                        <span class="min-w-0 flex-1 font-medium">{{ course.name }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ courseActiveOnPickerDate(course.id) ? '已選 · 再點取消' : '點選加入' }}
                        </span>
                    </button>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="pickerOpen = false">完成</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
