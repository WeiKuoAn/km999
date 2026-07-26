<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Row = {
    classroom_id: number;
    classroom_name: string;
    course_name: string;
    course_category_name: string;
    weekday_labels: string;
    expected_days: number;
    attended_days: number;
    actual_total_days: number;
    absent_days: number;
    makeup_days: number;
    extra_days: number;
    attendance_rate: number | null;
    date_boxes: Array<{
        date: string;
        attended: boolean;
        use_present_style: boolean;
        is_excused: boolean;
        display_text: string;
        makeup_note: string | null;
    }>;
    makeup_boxes: Array<{
        makeup_session_date: string;
        makeup_time_display: string;
        original_dates: string[];
    }>;
    extra_boxes: Array<{
        date: string;
        display_text: string;
    }>;
};

const props = defineProps<{
    student: { id: number; name: string };
    filters: { year: string; month: string };
    rows: Row[];
}>();

const filterForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
});

const applyFilters = () => {
    router.get(`/students/${props.student.id}/attendance-rate`, {
        year: filterForm.year,
        month: filterForm.month,
    }, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
};

const activeClassroomId = ref<number | null>(null);

watch(
    () => props.rows,
    (rows) => {
        if (rows.length === 0) {
            activeClassroomId.value = null;

            return;
        }
        if (activeClassroomId.value === null || !rows.some((r) => r.classroom_id === activeClassroomId.value)) {
            activeClassroomId.value = rows[0]!.classroom_id;
        }
    },
    { immediate: true, deep: true },
);

const activeRow = computed(() => props.rows.find((r) => r.classroom_id === activeClassroomId.value) ?? null);

/** 僅排課日：出席／遲到為綠、請假為藍、其餘（缺席）為紅 */
const scheduleCardClass = (box: Row['date_boxes'][number]) => {
    if (box.use_present_style) {
        return 'border-primary bg-primary text-primary-foreground';
    }
    if (box.is_excused) {
        return 'border-sky-300 bg-sky-100 text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200';
    }

    return 'border-red-300 bg-red-100 text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200';
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生管理', href: '/students' },
            { title: '課程出席率', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`課程出席率 - ${student.name}`" />
    <div class="space-y-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">課程出席率（{{ student.name }}）</h1>
            <Link href="/students" class="text-sm text-primary underline-offset-4 hover:underline">返回學生管理</Link>
        </div>

        <form class="rounded-xl border border-sidebar-border/70 p-4" @submit.prevent="applyFilters">
            <div class="form-filter-inline form-filter-inline--no-teacher">
                <div class="grid gap-1">
                    <label for="year" class="text-sm font-medium">年</label>
                    <input
                        id="year"
                        v-model="filterForm.year"
                        type="number"
                        min="2000"
                        max="2100"
                        class="h-9 rounded-md border px-3"
                        @change="applyFilters"
                    />
                </div>
                <div class="grid gap-1">
                    <label for="month" class="text-sm font-medium">月</label>
                    <select id="month" v-model="filterForm.month" class="h-9 rounded-md border px-3" @change="applyFilters">
                        <option v-for="m in 12" :key="m" :value="String(m)">{{ m }} 月</option>
                    </select>
                </div>
                <button type="submit" class="form-filter-inline__submit h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">查詢</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 p-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">班級</th>
                        <th class="py-2 text-left">課程</th>
                        <th class="py-2 text-left">上課週次</th>
                        <th class="py-2 text-right">應上課天數</th>
                        <th class="py-2 text-right">請假天數</th>
                        <th class="py-2 text-right">補課天數</th>
                        <th class="py-2 text-right">加課天數</th>
                        <th class="py-2 text-right">實際出席天數</th>
                        <th class="py-2 text-right">出席率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.classroom_id"
                        class="cursor-pointer border-b transition-colors hover:bg-muted/40"
                        :class="{ 'bg-muted/60': activeClassroomId === row.classroom_id }"
                        @click="activeClassroomId = row.classroom_id"
                    >
                        <td class="py-2">{{ row.classroom_name }}</td>
                        <td class="py-2">{{ row.course_category_name }} / {{ row.course_name }}</td>
                        <td class="py-2">{{ row.weekday_labels || '-' }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.expected_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.absent_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.makeup_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.extra_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.actual_total_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.attendance_rate === null ? '-' : `${row.attendance_rate}%` }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="9" class="py-8 text-center text-muted-foreground">此學生本月沒有班級資料。</td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-2 text-xs text-muted-foreground">點選表格列可切換下方該班級的出席明細。</p>
        </div>

        <div v-if="activeRow" class="rounded-xl border border-sidebar-border/70 p-4">
            <div class="mb-4 border-b border-sidebar-border/70 pb-3">
                <h2 class="text-sm font-semibold text-foreground">依班級檢視</h2>
                <p class="mt-1 text-xs text-muted-foreground">上方為排課日出席；若有補課，實際補課日另列於下方。</p>
            </div>
            <div
                class="flex flex-wrap gap-1 border-b border-sidebar-border/70 pb-2"
                role="tablist"
                aria-label="依班級切換"
            >
                <button
                    v-for="row in rows"
                    :key="`tab-${row.classroom_id}`"
                    type="button"
                    role="tab"
                    :aria-selected="activeClassroomId === row.classroom_id"
                    class="max-w-full rounded-md border px-3 py-1.5 text-left text-xs font-medium transition-colors md:text-sm"
                    :class="
                        activeClassroomId === row.classroom_id
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-transparent bg-muted/50 text-muted-foreground hover:bg-muted'
                    "
                    @click="activeClassroomId = row.classroom_id"
                >
                    <span class="block truncate">{{ row.classroom_name }}</span>
                    <span class="mt-0.5 block truncate text-[10px] font-normal opacity-90 md:text-xs">
                        {{ row.course_category_name }} / {{ row.course_name }}
                    </span>
                </button>
            </div>

            <div class="mt-4 space-y-6" role="tabpanel">
                <div class="mb-3 text-sm font-semibold">
                    {{ activeRow.classroom_name }}（{{ activeRow.course_category_name }} / {{ activeRow.course_name }}）
                </div>

                <section>
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">排課日</h3>
                    <div v-if="activeRow.date_boxes.length > 0" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div
                            v-for="box in activeRow.date_boxes"
                            :key="box.date"
                            class="rounded-md border px-3 py-2 text-center text-sm font-medium"
                            :class="scheduleCardClass(box)"
                        >
                            <div>{{ box.date }}</div>
                            <div class="mt-1 text-xs opacity-90">
                                {{ box.display_text }}
                            </div>
                            <div v-if="box.makeup_note" class="mt-0.5 text-[10px] opacity-90">
                                {{ box.makeup_note }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">本月沒有排課日期。</div>
                </section>

                <section v-if="activeRow.makeup_boxes?.length">
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">補課</h3>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div
                            v-for="mb in activeRow.makeup_boxes"
                            :key="mb.makeup_session_date"
                            class="rounded-md border border-amber-400 bg-amber-50 px-3 py-2 text-center text-sm font-medium text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-50"
                        >
                            <div class="tabular-nums">{{ mb.makeup_session_date }}</div>
                            <div class="mt-2 text-xs font-semibold">補課</div>
                        </div>
                    </div>
                </section>

                <section v-if="activeRow.extra_boxes?.length">
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">加課</h3>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div
                            v-for="eb in activeRow.extra_boxes"
                            :key="eb.date"
                            class="rounded-md border border-orange-400 bg-orange-50 px-3 py-2 text-center text-sm font-medium text-orange-950 dark:border-orange-700 dark:bg-orange-950/30 dark:text-orange-50"
                        >
                            <div class="tabular-nums">{{ eb.date }}</div>
                            <div class="mt-2 text-xs font-semibold">{{ eb.display_text }}</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
