<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import RevenuePieChart from '@/components/reports/RevenuePieChart.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type BatchRow = {
    id: number;
    student_id: number;
    student_code: string | null;
    student_name: string;
    grade_name: string | null;
    start_year: number;
    start_month: number;
    end_year: number;
    end_month: number;
    period_label: string;
    expected_total: number;
    paid_total: number;
    course_count: number;
    paid_date: string | null;
    settled_by_name: string;
    pay_cycle?: string | null;
};

type PieSlice = { label: string; value: number };

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    year: number;
    monthRows: Array<{ month: number; revenue: number }>;
    yearTotal: number;
    pieByGrade: PieSlice[];
    pieBySubject: PieSlice[];
    batches: Paginated<BatchRow>;
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    filters: {
        year: string;
        q: string;
        teacher_id: string;
    };
}>();

const q = ref(props.filters.q ?? '');

const filterForm = useForm({
    year: props.filters.year,
    teacher_id: props.filters.teacher_id ?? '',
    q: props.filters.q ?? '',
});

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        filterForm.year = filters.year;
        filterForm.teacher_id = filters.teacher_id ?? '';
        filterForm.q = filters.q ?? '';
    },
);

const applyFilters = () => {
    filterForm.q = q.value.trim();
    filterForm.get('/reports', {
        preserveState: true,
        replace: true,
    });
};

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const monthLabel = (m: number) => `${m}月`;

const detailHref = (row: BatchRow) =>
    `/student-payments/${row.student_id}?from_year=${row.start_year}&from_month=${row.start_month}&to_year=${row.end_year}&to_month=${row.end_month}`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: '每月營收報表', href: '/reports' }],
    },
});
</script>

<template>
    <Head title="每月營收報表" />

    <div class="page-shell">
        <PageHeader
            title="每月營收報表"
            description="已完成繳費確認且已收款；上方為月份合計與圓餅圖（年級／科目），下方依帳期條列。"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/reports/attendance-rate">班級出席率</Link>
                </Button>
            </template>
        </PageHeader>

        <form
            class="mb-4 rounded-xl border border-sidebar-border/70 bg-card p-4"
            @submit.prevent="applyFilters"
        >
            <div
                class="form-filter-inline"
                :class="{ 'form-filter-inline--no-teacher': !canFilterByTeacher }"
            >
                <div class="grid gap-1">
                    <Label for="year">年份</Label>
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
                <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1">
                    <Label for="teacher_id">老師</Label>
                    <select
                        id="teacher_id"
                        v-model="filterForm.teacher_id"
                        class="h-9 w-full rounded-md border bg-background px-3"
                        @change="applyFilters"
                    >
                        <option value="">全部</option>
                        <option
                            v-for="t in teacherOptions"
                            :key="t.id"
                            :value="String(t.id)"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                </div>
                <div class="grid min-w-0 flex-1 gap-1">
                    <Label for="q">搜尋學生</Label>
                    <Input id="q" v-model="q" placeholder="學號或姓名" />
                </div>
                <Button
                    type="submit"
                    class="form-filter-inline__submit h-10 w-full sm:w-auto"
                    :disabled="filterForm.processing"
                >
                    查詢
                </Button>
            </div>
        </form>

        <div
            class="mb-4 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-medium">{{ year }} 年營收</p>
                <p class="text-xl font-semibold tabular-nums text-primary">
                    {{ formatMoney(yearTotal) }}
                </p>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[28rem] text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th
                                v-for="row in monthRows"
                                :key="row.month"
                                class="px-1 py-1.5 text-center font-normal"
                            >
                                {{ monthLabel(row.month) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td
                                v-for="row in monthRows"
                                :key="row.month"
                                class="px-1 py-1.5 text-center tabular-nums"
                                :class="
                                    row.revenue > 0
                                        ? 'font-medium'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ formatMoney(row.revenue) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4 grid gap-4 lg:grid-cols-2">
            <RevenuePieChart
                :title="`${year} 年營收｜依年級`"
                :items="pieByGrade"
            />
            <RevenuePieChart
                :title="`${year} 年營收｜依科目`"
                :items="pieBySubject"
            />
        </div>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="row in batches.data"
                :key="row.id"
                :title="`${row.student_code ?? '—'} ${row.student_name}`"
                :subtitle="`${row.period_label}｜${row.course_count} 門課程`"
            >
                <MobileRecordField label="年級">{{
                    row.grade_name ?? '—'
                }}</MobileRecordField>
                <MobileRecordField label="總金額">{{
                    formatMoney(row.paid_total)
                }}</MobileRecordField>
                <MobileRecordField label="收款日">{{
                    row.paid_date ?? '—'
                }}</MobileRecordField>
                <MobileRecordField label="收款人">{{
                    row.settled_by_name
                }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="detailHref(row)">明細</Link>
                        </Button>
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="batches.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                此年份尚無已收款紀錄。
            </p>
        </div>

        <div
            class="desktop-table-wrap rounded-xl border border-sidebar-border/70"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30">
                        <th class="px-3 py-2 text-left">學生</th>
                        <th class="px-3 py-2 text-left">帳期</th>
                        <th class="px-3 py-2 text-right">總金額</th>
                        <th class="px-3 py-2 text-left">收款日</th>
                        <th class="px-3 py-2 text-left">收款人</th>
                        <th class="px-3 py-2 text-right">課程數</th>
                        <th class="px-3 py-2 text-left">明細</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in batches.data"
                        :key="row.id"
                        class="border-b"
                    >
                        <td class="px-3 py-2.5">
                            <div class="font-medium">
                                {{ row.student_code ?? '—' }}
                                {{ row.student_name }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.grade_name ?? '—' }}
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5">
                            {{ row.period_label }}
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ formatMoney(row.paid_total) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5">
                            {{ row.paid_date ?? '—' }}
                        </td>
                        <td class="px-3 py-2.5">{{ row.settled_by_name }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ row.course_count }}
                        </td>
                        <td class="px-3 py-2.5">
                            <Link
                                :href="detailHref(row)"
                                class="text-primary underline-offset-4 hover:underline"
                            >
                                明細
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="batches.data.length === 0">
                        <td
                            colspan="7"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            此年份尚無已收款紀錄。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="batches.links" class="mt-4" />
    </div>
</template>
