<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    classroom: {
        id: number;
        name: string;
        course_name: string;
        course_category_name: string;
        weekday_labels: string;
    };
    filters: {
        year: string;
        month: string;
    };
    rows: Array<{
        student_id: number;
        student_name: string;
        student_phone: string | null;
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
    }>;
}>();

const filterForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
});

const applyFilters = () => {
    filterForm.get(`/classrooms/${props.classroom.id}/attendance-rate`, {
        preserveState: true,
        replace: true,
    });
};

type DateBox = {
    date: string;
    attended: boolean;
    use_present_style: boolean;
    is_excused: boolean;
    display_text: string;
    makeup_note: string | null;
};

/** 僅排課日：出席／遲到／補課為綠、請假為藍、其餘（缺席）為紅 */
const scheduleCardClass = (box: DateBox) => {
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
            { title: '班級管理', href: '/classrooms' },
            { title: '班級出席率', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`班級出席率 - ${classroom.name}`" />
    <div class="page-shell">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-semibold">班級出席率（{{ classroom.name }}）</h1>
                <p class="text-sm text-muted-foreground">
                    {{ classroom.course_category_name }} / {{ classroom.course_name }} ・ {{ classroom.weekday_labels || '-' }}
                </p>
            </div>
            <Button variant="outline" as-child><Link href="/classrooms">返回班級管理</Link></Button>
        </div>

        <form class="rounded-xl border border-sidebar-border/70 p-4" @submit.prevent="applyFilters">
            <div class="form-filter-inline form-filter-inline--no-teacher">
                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="year">年</label>
                    <input id="year" v-model="filterForm.year" type="number" min="2000" max="2100" class="h-9 rounded-md border px-3" />
                </div>
                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="month">月</label>
                    <select id="month" v-model="filterForm.month" class="h-9 rounded-md border px-3" @change="applyFilters">
                        <option v-for="m in 12" :key="m" :value="String(m)">{{ m }} 月</option>
                    </select>
                </div>
                <Button type="submit" class="form-filter-inline__submit" :disabled="filterForm.processing">查詢</Button>
            </div>
        </form>

        <div class="rounded-xl border border-sidebar-border/70 p-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">學生</th>
                        <th class="py-2 text-right">應上課天數</th>
                        <th class="py-2 text-right">請假天數</th>
                        <th class="py-2 text-right">補課天數</th>
                        <th class="py-2 text-right">加課天數</th>
                        <th class="py-2 text-right">實際出席天數</th>
                        <th class="py-2 text-right">出席率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.student_id" class="border-b">
                        <td class="py-2">
                            {{ row.student_name }}
                            <span class="text-muted-foreground">{{ row.student_phone ? ` (${row.student_phone})` : '' }}</span>
                        </td>
                        <td class="py-2 text-right tabular-nums">{{ row.expected_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.absent_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.makeup_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.extra_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.actual_total_days }}</td>
                        <td class="py-2 text-right tabular-nums">{{ row.attendance_rate === null ? '-' : `${row.attendance_rate}%` }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="7" class="py-8 text-center text-muted-foreground">此班本月沒有可統計的學生資料。</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            <div
                v-for="row in rows"
                :key="`boxes-${row.student_id}`"
                class="rounded-xl border border-sidebar-border/70 p-4"
            >
                <div class="mb-3 text-sm font-semibold">
                    {{ row.student_name }}
                    <span class="text-muted-foreground">{{ row.student_phone ? ` (${row.student_phone})` : '' }}</span>
                </div>

                <section>
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">排課日</h3>
                    <div v-if="row.date_boxes.length > 0" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div
                            v-for="box in row.date_boxes"
                            :key="`${row.student_id}-${box.date}`"
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

                <section v-if="row.makeup_boxes?.length" class="mt-4">
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">補課</h3>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div
                            v-for="mb in row.makeup_boxes"
                            :key="`${row.student_id}-mk-${mb.makeup_session_date}`"
                            class="rounded-md border border-amber-400 bg-amber-50 px-3 py-2 text-center text-sm font-medium text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-50"
                        >
                            <div class="tabular-nums">{{ mb.makeup_session_date }}</div>
                            <div class="mt-2 text-xs font-semibold">補課</div>
                        </div>
                    </div>
                </section>

                <section v-if="row.extra_boxes?.length" class="mt-4">
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">加課</h3>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div
                            v-for="eb in row.extra_boxes"
                            :key="`${row.student_id}-ex-${eb.date}`"
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

