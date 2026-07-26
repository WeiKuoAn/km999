<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import PageHeader from '@/components/layout/PageHeader.vue';
import { classroomSwatchStyle, normalizeClassroomHex } from '@/lib/classroomColor';
import { formatCourseOptionLabel } from '@/lib/courseLabel';

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
    roll_call_done_for_today: boolean;
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

const scheduleText = (c: Classroom): string =>
    c.today_schedules.length
        ? c.today_schedules.map((s) => `週${weekdayText(s.weekday)} ${formatHm(s.start_time)} — ${formatHm(s.end_time)}`).join('、')
        : '-';

const scheduleLines = (c: Classroom): string[] =>
    c.today_schedules.length
        ? c.today_schedules.map((s) => `週${weekdayText(s.weekday)} ${formatHm(s.start_time)} — ${formatHm(s.end_time)}`)
        : ['-'];

const props = defineProps<{
    classrooms: Classroom[];
    teacherOptions: Array<{ id: number; name: string }>;
    filters: { teacher_id: string };
    todayDate: string;
    nowDisplay: string;
    currentWeekdayLabel: string;
    canFilterByTeacher: boolean;
    serverTimezone: string;
}>();

const filterForm = useForm({
    teacher_id: props.filters.teacher_id ?? '',
});

const applyFilters = () => {
    filterForm.get('/attendances/live', {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.teacher_id = '';
    applyFilters();
};

const timezoneDisplay = computed(() =>
    props.serverTimezone === 'Asia/Taipei'
        ? `台灣時間（${props.serverTimezone}，UTC+8）`
        : props.serverTimezone,
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '線上點名', href: '/attendances' },
            { title: '即時點名', href: '/attendances/live' },
        ],
    },
});
</script>

<template>
    <Head title="即時點名" />
    <div class="page-shell">
        <PageHeader title="即時點名">
            <template #below-title>
            <p class="text-sm text-muted-foreground">
                依<strong class="text-foreground">伺服器此刻</strong>（{{ nowDisplay }}，{{ timezoneDisplay }}）篩選：今日為
                <span class="font-medium text-foreground">{{ currentWeekdayLabel }}</span>
                {{ todayDate }}，且節次時間<strong class="text-foreground">包含此刻</strong>的班級。
                <template v-if="!canFilterByTeacher">僅顯示您任課的班級。</template>
            </p>
                <p class="mt-1 text-sm">
                    <Link href="/attendances" class="text-primary underline-offset-4 hover:underline">返回線上點名（依星期篩選）</Link>
                </p>
            </template>
        </PageHeader>

        <form
            v-if="canFilterByTeacher"
            class="list-filter-panel rounded-xl border border-sidebar-border/70 p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
                <div class="grid gap-1">
                    <label for="teacher_id" class="text-sm font-medium">老師</label>
                    <select
                        id="teacher_id"
                        v-model="filterForm.teacher_id"
                        class="h-9 w-full max-w-md rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                        @change="applyFilters"
                    >
                        <option value="">全部老師</option>
                        <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">
                            {{ t.name }}
                        </option>
                    </select>
                </div>
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
        </form>

        <div class="mobile-card-list mobile-card-list--until-lg">
            <div
                v-for="c in classrooms"
                :key="c.id"
                class="rounded-xl border border-sidebar-border/70 border-l-4 p-3"
                :style="{ borderLeftColor: normalizeClassroomHex(c.color) }"
            >
                <div class="font-medium">{{ c.name }}</div>
                <div class="mt-1 text-sm text-muted-foreground">{{ formatCourseOptionLabel(c.course) }}</div>
                <div class="mt-1 text-sm">老師：{{ c.teacher?.name ?? '-' }}</div>
                <div class="mt-2 text-sm">
                    <div v-for="(line, idx) in scheduleLines(c)" :key="`${c.id}-line-${idx}`">{{ line }}</div>
                </div>
                <div class="mt-2">
                    <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            c.roll_call_done_for_today ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white'
                        "
                    >
                        {{ c.roll_call_done_for_today ? '已點名' : '未點名' }}
                    </span>
                </div>
                <div class="mt-3">
                    <Button size="sm" class="w-full" :variant="c.roll_call_done_for_today ? 'outline' : 'default'" as-child>
                        <Link :href="`/attendances/classrooms/${c.id}/roll-call?date=${encodeURIComponent(todayDate)}`">
                            {{ c.roll_call_done_for_today ? '查看' : '點名' }}
                        </Link>
                    </Button>
                </div>
            </div>
            <div v-if="classrooms.length === 0" class="rounded-xl border border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground">
                目前沒有在節次時間內的班級。可能尚未到上課時間、已下課，或請調整上方老師篩選。
            </div>
        </div>

        <div class="desktop-table-wrap desktop-table-wrap--from-lg">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">班級</th>
                        <th class="py-2 text-left">課程</th>
                        <th class="py-2 text-left">老師</th>
                        <th class="py-2 text-left">此刻節次</th>
                        <th class="py-2 text-left whitespace-nowrap">今日點名</th>
                        <th class="py-2 text-right w-28">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in classrooms" :key="c.id" class="border-b">
                        <td class="py-2 font-medium">
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60 shadow-sm"
                                    :style="classroomSwatchStyle(c.color)"
                                />
                                {{ c.name }}
                            </span>
                        </td>
                        <td class="py-2">{{ formatCourseOptionLabel(c.course) }}</td>
                        <td class="py-2">{{ c.teacher?.name ?? '-' }}</td>
                        <td class="py-2">
                            {{ scheduleText(c) }}
                        </td>
                        <td class="py-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    c.roll_call_done_for_today ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white'
                                "
                            >
                                {{ c.roll_call_done_for_today ? '已點名' : '未點名' }}
                            </span>
                        </td>
                        <td class="py-2 text-right">
                            <Button size="sm" :variant="c.roll_call_done_for_today ? 'outline' : 'default'" as-child>
                                <Link :href="`/attendances/classrooms/${c.id}/roll-call?date=${encodeURIComponent(todayDate)}`">
                                    {{ c.roll_call_done_for_today ? '查看' : '點名' }}
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="classrooms.length === 0">
                        <td colspan="6" class="py-8 text-center text-muted-foreground">
                            目前沒有在節次時間內的班級。可能尚未到上課時間、已下課，或請調整上方老師篩選。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
