<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ClassroomFilterSelect from '@/components/ClassroomFilterSelect.vue';
import { classroomSwatchStyle, normalizeClassroomHex } from '@/lib/classroomColor';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { formatCourseCellParts, formatCourseSelectLabel } from '@/lib/courseLabel';
import { resolveRollCallDate, scheduleWeekdaysFromClassroom } from '@/lib/rollCallDate';

type Course = {
    id: number;
    course_category: { name: string };
    name: string;
    course_prices: Array<{ level: string | null; tuition: number }>;
};

type Classroom = {
    id: number;
    name: string;
    color: string | null;
    today_schedules: Array<{
        weekday: number;
        start_time: string;
        end_time: string;
    }>;
    course: Course;
    teacher: { id: number; name: string } | null;
    /** 與「點名」連結相同：以今日日期之出勤紀錄是否已涵蓋在籍學生為準 */
    roll_call_done_for_today: boolean;
    /** 日期區間模式才有：該列對應的實際上課日期 */
    date?: string;
    /** 日期區間模式才有：該日期的星期標籤，如「週一」 */
    weekday_label?: string;
};

const weekdayText = (day: number | null) =>
    ({ 1: '一', 2: '二', 3: '三', 4: '四', 5: '五', 6: '六', 7: '日' }[day ?? 0] ?? '-');

const formatHm = (t: string | null): string => {
    if (!t) {
        return '';
    }
    const [h, m] = t.split(':');

    return `${h}:${(m ?? '00').slice(0, 2)}`;
};

const scheduleLines = (c: Classroom): string[] =>
    c.today_schedules.length
        ? c.today_schedules.map((s) => `週${weekdayText(s.weekday)} ${formatHm(s.start_time)} — ${formatHm(s.end_time)}`)
        : ['-'];

const courseCell = (course: Course) => formatCourseCellParts(course);

const props = defineProps<{
    classrooms: Classroom[];
    courseOptions: Course[];
    classroomFilterOptions: Array<{ id: number; name: string; color: string | null }>;
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    filters: {
        weekday: string;
        course_id: string;
        classroom_id: string;
        teacher_id?: string;
        roll_status: string;
        from?: string;
        to?: string;
    };
    defaultWeekday: string;
    todayDate: string;
    todayWeekdayLabel: string;
    selectedWeekdayLabel: string;
    dateRangeMode?: boolean;
    rangeTruncated?: boolean;
}>();

const filterForm = useForm({
    weekday: props.filters.weekday,
    course_id: props.filters.course_id,
    classroom_id: props.filters.classroom_id,
    teacher_id: props.filters.teacher_id ?? '',
    roll_status: props.filters.roll_status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const applyFilters = () => {
    filterForm
        .transform((data) => {
            const payload = { ...data };
            // 未同時填寫起訖日期時，不送出區間參數（維持星期模式）。
            if (!payload.from || !payload.to) {
                payload.from = '';
                payload.to = '';
            }
            return payload;
        })
        .get('/attendances', {
            preserveState: true,
            replace: true,
        });
};

const resetFilters = () => {
    filterForm.weekday = props.defaultWeekday;
    filterForm.course_id = '';
    filterForm.classroom_id = '';
    filterForm.teacher_id = '';
    filterForm.roll_status = '';
    filterForm.from = '';
    filterForm.to = '';
    applyFilters();
};

const page = usePage();

const rollCallHref = (c: Classroom): string => {
    const date =
        props.dateRangeMode && c.date
            ? c.date
            : resolveRollCallDate(props.todayDate, scheduleWeekdaysFromClassroom(c.today_schedules));

    return `/attendances/classrooms/${c.id}/roll-call?date=${encodeURIComponent(date)}&return=${encodeURIComponent(page.url)}`;
};

const rowKey = (c: Classroom): string => (props.dateRangeMode && c.date ? `${c.id}-${c.date}` : `${c.id}`);

defineOptions({
    layout: {
        breadcrumbs: [{ title: '線上點名', href: '/attendances' }],
    },
});
</script>

<template>
    <Head title="線上點名" />
    <div class="page-shell">
        <PageHeader title="線上點名">
            <template #below-title>
                <p v-if="dateRangeMode" class="text-sm text-muted-foreground">
                    日期區間模式：顯示
                    <span class="font-medium text-foreground">{{ filters.from }}</span> 至
                    <span class="font-medium text-foreground">{{ filters.to }}</span>
                    之間每個有排課（含加課）的日期，可逐日補登點名。「已點名／未點名」以該日期是否已儲存出勤且涵蓋在籍學生為準。
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    今日為 <span class="font-medium text-foreground">{{ todayWeekdayLabel }}</span>
                    {{ todayDate }}；目前篩選星期為
                    <span class="font-medium text-foreground">{{ selectedWeekdayLabel }}</span>。
                    「已點名／未點名」以各班列級預設點名日期（與「點名」按鈕相同；非今日上課日時為最近一個排課日）是否已儲存出勤且涵蓋在籍學生為準。
                </p>
                <p v-if="rangeTruncated" class="mt-1 text-sm font-medium text-amber-600">
                    區間過長，已自動限制為起始日起 92 天。
                </p>
            </template>
        </PageHeader>

        <form class="list-filter-panel rounded-xl border border-sidebar-border/70 p-4 md:p-5" @submit.prevent="applyFilters">
                <div class="filter-grid">
                    <div class="grid min-w-0 gap-1.5">
                        <label for="weekday" class="text-sm font-medium">星期</label>
                        <select id="weekday" v-model="filterForm.weekday" class="h-10 w-full rounded-md border px-3" @change="applyFilters">
                            <option value="">全部</option>
                            <option value="1">週一</option>
                            <option value="2">週二</option>
                            <option value="3">週三</option>
                            <option value="4">週四</option>
                            <option value="5">週五</option>
                            <option value="6">週六</option>
                            <option value="7">週日</option>
                        </select>
                    </div>
                    <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1.5">
                        <label for="teacher_id" class="text-sm font-medium">老師</label>
                        <select
                            id="teacher_id"
                            v-model="filterForm.teacher_id"
                            class="h-10 w-full min-w-0 rounded-md border bg-background px-3"
                            @change="applyFilters"
                        >
                            <option value="">全部老師</option>
                            <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                        </select>
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label for="classroom_id" class="text-sm font-medium">班級</label>
                        <ClassroomFilterSelect
                            id="classroom_id"
                            v-model="filterForm.classroom_id"
                            :options="classroomFilterOptions"
                            :disabled="filterForm.processing"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label for="course_id" class="text-sm font-medium">課程</label>
                        <select id="course_id" v-model="filterForm.course_id" class="h-10 w-full rounded-md border px-3" @change="applyFilters">
                            <option value="">全部課程</option>
                            <option v-for="course in courseOptions" :key="course.id" :value="String(course.id)">
                                {{ formatCourseSelectLabel(course) }}
                            </option>
                        </select>
                    </div>
                    <div class="filter-grid__date-row">
                        <div class="grid min-w-0 gap-1.5">
                            <label for="from" class="text-sm font-medium">起始日期</label>
                            <input id="from" v-model="filterForm.from" type="date" class="h-10 w-full rounded-md border px-3" />
                        </div>
                        <div class="grid min-w-0 gap-1.5">
                            <label for="to" class="text-sm font-medium">結束日期</label>
                            <input id="to" v-model="filterForm.to" type="date" class="h-10 w-full rounded-md border px-3" />
                        </div>
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label for="roll_status" class="text-sm font-medium">狀態</label>
                        <select
                            id="roll_status"
                            v-model="filterForm.roll_status"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                            @change="applyFilters"
                        >
                            <option value="">全部</option>
                            <option value="done">已點名</option>
                            <option value="pending">未點名</option>
                        </select>
                    </div>
                    <div class="filter-grid__actions">
                        <Button type="submit" class="h-10 w-full sm:w-auto" :disabled="filterForm.processing">篩選</Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 w-full sm:w-auto"
                            :disabled="filterForm.processing"
                            @click="resetFilters"
                        >
                            重設
                        </Button>
                    </div>
                </div>
            <p class="mt-2 text-xs text-muted-foreground">
                補登過往點名：同時填寫起訖日期後按「篩選」，會列出區間內每個上課日；若「星期」不是「全部」，只會列出該星期的日期。
            </p>
        </form>

        <div class="mobile-card-list mobile-card-list--until-lg">
            <MobileRecordCard
                v-for="c in classrooms"
                :key="rowKey(c)"
                :title="c.name"
                class="border-l-4"
                :style="{ borderLeftColor: normalizeClassroomHex(c.color) }"
            >
                <template #badge>
                    <span
                        class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            c.roll_call_done_for_today
                                ? 'bg-emerald-600 text-white'
                                : 'bg-amber-500 text-white'
                        "
                    >
                        {{ c.roll_call_done_for_today ? '已點名' : '未點名' }}
                    </span>
                </template>
                <MobileRecordField v-if="dateRangeMode" label="日期">
                    <span class="block font-normal tabular-nums">{{ c.date }} {{ c.weekday_label }}</span>
                </MobileRecordField>
                <MobileRecordField label="課程">
                    <span
                        v-for="cell in [courseCell(c.course)]"
                        :key="`${c.id}-m-course`"
                        class="block font-normal"
                    >
                        <span class="block">{{ cell.title }}</span>
                        <span
                            v-for="(line, li) in cell.tierLines"
                            :key="`${c.id}-tier-${li}`"
                            class="block text-xs font-normal text-muted-foreground"
                        >
                            {{ line }}
                        </span>
                    </span>
                </MobileRecordField>
                <MobileRecordField label="老師">{{ c.teacher?.name ?? '—' }}</MobileRecordField>
                <MobileRecordField label="時間">
                    <span class="block space-y-0.5 font-normal">
                        <span
                            v-for="(line, idx) in scheduleLines(c)"
                            :key="`${c.id}-line-${idx}`"
                            class="block tabular-nums"
                        >
                            {{ line }}
                        </span>
                    </span>
                </MobileRecordField>
                <template #actions>
                    <Button
                        size="sm"
                        class="w-full"
                        :variant="c.roll_call_done_for_today ? 'outline' : 'default'"
                        as-child
                    >
                        <Link :href="rollCallHref(c)">
                            {{ c.roll_call_done_for_today ? '查看' : '點名' }}
                        </Link>
                    </Button>
                </template>
            </MobileRecordCard>
            <p
                v-if="classrooms.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                {{ dateRangeMode ? '此日期區間內無排課班級。' : '今日此星期無排課班級，或尚未建立班級資料。' }}
            </p>
        </div>

        <div class="desktop-table-wrap desktop-table-wrap--from-lg">
            <table class="w-full min-w-[52rem] text-sm">
                <colgroup>
                    <col v-if="dateRangeMode" class="w-[8.5rem]" />
                    <col class="w-[7.5rem]" />
                    <col />
                    <col class="w-[5.5rem]" />
                    <col class="w-[10.5rem]" />
                    <col class="w-[6.5rem]" />
                    <col class="w-[5.5rem]" />
                </colgroup>
                <thead>
                    <tr class="border-b">
                        <th v-if="dateRangeMode">日期</th>
                        <th>班級</th>
                        <th>課程</th>
                        <th>老師</th>
                        <th>時間</th>
                        <th>點名狀態</th>
                        <th class="text-right">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in classrooms" :key="rowKey(c)" class="border-b">
                        <td v-if="dateRangeMode" class="align-top whitespace-nowrap tabular-nums">
                            <div>{{ c.date }}</div>
                            <div class="text-xs text-muted-foreground">{{ c.weekday_label }}</div>
                        </td>
                        <td class="align-top">
                            <div class="flex items-start gap-2">
                                <span
                                    class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full border border-border/60 shadow-sm"
                                    :style="classroomSwatchStyle(c.color)"
                                />
                                <span class="min-w-0 font-medium leading-snug break-words">{{ c.name }}</span>
                            </div>
                        </td>
                        <td class="align-top min-w-0">
                            <div
                                v-for="cell in [courseCell(c.course)]"
                                :key="`${c.id}-course`"
                                class="min-w-0 leading-snug"
                            >
                                <div>{{ cell.title }}</div>
                                <div
                                    v-for="(line, li) in cell.tierLines"
                                    :key="`${c.id}-tier-${li}`"
                                    class="mt-0.5 text-xs text-muted-foreground break-words"
                                >
                                    {{ line }}
                                </div>
                            </div>
                        </td>
                        <td class="align-top whitespace-nowrap">{{ c.teacher?.name ?? '-' }}</td>
                        <td class="align-top">
                            <ul class="space-y-1 leading-snug">
                                <li
                                    v-for="(line, idx) in scheduleLines(c)"
                                    :key="`${c.id}-sched-${idx}`"
                                    class="tabular-nums whitespace-nowrap"
                                >
                                    {{ line }}
                                </li>
                            </ul>
                        </td>
                        <td class="align-top whitespace-nowrap">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    c.roll_call_done_for_today
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-amber-500 text-white'
                                "
                            >
                                {{ c.roll_call_done_for_today ? '已點名' : '未點名' }}
                            </span>
                        </td>
                        <td class="align-top text-right whitespace-nowrap">
                            <Button size="sm" :variant="c.roll_call_done_for_today ? 'outline' : 'default'" as-child>
                                <Link :href="rollCallHref(c)">
                                    {{ c.roll_call_done_for_today ? '查看' : '點名' }}
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="classrooms.length === 0">
                        <td :colspan="dateRangeMode ? 7 : 6" class="py-8 text-center text-muted-foreground">
                            {{ dateRangeMode ? '此日期區間內無排課班級。' : '今日此星期無排課班級，或尚未建立班級資料。' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
