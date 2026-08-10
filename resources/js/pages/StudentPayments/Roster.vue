<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileDown } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Row = {
    student_id: number;
    student_code: string | null;
    student_name: string;
    grade_name: string | null;
    subjects_months: string;
    subjects_label: string;
    period_label: string;
    start_year: number;
    start_month: number;
    end_year: number;
    end_month: number;
    fee: number;
    note: string;
    pay_cycle?: string | null;
    pay_cycle_label?: string | null;
    fee_source?: string;
    course_ids?: number[];
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    rows: Paginated<Row>;
    total_count?: number;
    filters: {
        q: string;
        year: string;
    };
}>();

const q = ref(props.filters.q ?? '');
const year = ref(props.filters.year ?? '');

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        year.value = filters.year ?? '';
    },
);

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const detailHref = (row: Row) => {
    const params = new URLSearchParams();
    params.set('student_id', String(row.student_id));
    for (const id of row.course_ids ?? []) {
        params.append('course_ids[]', String(id));
    }
    if (row.pay_cycle) {
        params.set('pay_cycle', row.pay_cycle);
    }
    return `/student-payments/create?${params.toString()}`;
};

const filterParams = () => ({
    q: q.value.trim() || undefined,
    year: year.value.trim() || undefined,
});

const applyFilters = () => {
    router.get('/payment-lists', filterParams(), {
        preserveState: true,
        replace: true,
    });
};

const pdfHref = () => {
    const params = new URLSearchParams();
    if (q.value.trim()) params.set('q', q.value.trim());
    if (year.value.trim()) params.set('year', year.value.trim());
    const qs = params.toString();
    return qs ? `/payment-lists/pdf?${qs}` : '/payment-lists/pdf';
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: '繳費名單', href: '/payment-lists' }],
    },
});
</script>

<template>
    <Head title="繳費名單" />
    <div class="page-shell">
        <PageHeader
            title="繳費名單"
            description="列出「下一期尚未繳費」的學生。例如已繳 7–9 月，會出現 10–12 月待繳；費用依繳別與科目自動試算（首期可不足三個月）。"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <a :href="pdfHref()" target="_blank" rel="noopener">
                        <FileDown class="size-4" />
                        匯出 PDF
                    </a>
                </Button>
                <Button as-child>
                    <Link href="/student-payments/create">新增收款</Link>
                </Button>
            </template>
        </PageHeader>

        <form
            class="mb-4 flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:flex-row sm:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid flex-1 gap-2">
                <Label for="q">搜尋學生</Label>
                <Input id="q" v-model="q" placeholder="學號或姓名" />
            </div>
            <div class="grid gap-2 sm:w-36">
                <Label for="year">待繳年份</Label>
                <Input
                    id="year"
                    v-model="year"
                    type="number"
                    min="2000"
                    max="2100"
                    placeholder="全部"
                />
            </div>
            <Button type="submit">查詢</Button>
        </form>

        <p v-if="typeof total_count === 'number'" class="mb-3 text-sm text-muted-foreground">
            共 {{ total_count }} 位待繳
        </p>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="row in rows.data"
                :key="`${row.student_id}-${row.start_year}-${row.start_month}`"
                :title="row.student_name"
                :subtitle="row.subjects_months"
            >
                <MobileRecordField label="學號">{{
                    row.student_code ?? '—'
                }}</MobileRecordField>
                <MobileRecordField label="費用">{{
                    formatMoney(row.fee)
                }}</MobileRecordField>
                <MobileRecordField label="繳別">{{
                    row.pay_cycle_label ?? '—'
                }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="detailHref(row)">去收款</Link>
                        </Button>
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="rows.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                目前沒有待繳名單（可能下一期已繳清，或尚無已繳紀錄可推算）。
            </p>
        </div>

        <div
            class="desktop-table-wrap rounded-xl border border-sidebar-border/70"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30">
                        <th class="px-3 py-2 text-left">學生姓名</th>
                        <th class="px-3 py-2 text-left">科目月份</th>
                        <th class="px-3 py-2 text-right">費用</th>
                        <th class="px-3 py-2 text-left">備註</th>
                        <th class="px-3 py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows.data"
                        :key="`${row.student_id}-${row.start_year}-${row.start_month}`"
                        class="border-b"
                    >
                        <td class="px-3 py-2.5">
                            <div class="font-medium">{{ row.student_name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.student_code ?? '—' }}
                                <span v-if="row.grade_name">
                                    · {{ row.grade_name }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5">
                            <div>{{ row.subjects_label }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.period_label }}
                                <span v-if="row.pay_cycle_label">
                                    · {{ row.pay_cycle_label }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-medium">
                            {{ formatMoney(row.fee) }}
                        </td>
                        <td class="px-3 py-2.5 text-muted-foreground">
                            {{ row.note || '—' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <Link
                                :href="detailHref(row)"
                                class="text-primary underline-offset-4 hover:underline"
                            >
                                去收款
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td
                            colspan="5"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            目前沒有待繳名單。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" class="mt-4" />
    </div>
</template>
