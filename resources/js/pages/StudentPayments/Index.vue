<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Row = {
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
    status: string;
    settled_by_name: string;
    pay_cycle?: string | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    rows: Paginated<Row>;
    summary: {
        unpaid_total: number;
        paid_total: number;
    };
    filters: {
        q: string;
        status: string;
    };
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        status.value = filters.status ?? 'all';
    },
);

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const detailHref = (row: Row) =>
    `/student-payments/${row.student_id}?from_year=${row.start_year}&from_month=${row.start_month}&to_year=${row.end_year}&to_month=${row.end_month}`;

const applyFilters = () => {
    router.get(
        '/student-payments',
        {
            q: q.value.trim() || undefined,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: true, replace: true },
    );
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: '學生收款', href: '/student-payments' }],
    },
});
</script>

<template>
    <Head title="學生收款" />
    <div class="page-shell">
        <PageHeader
            title="學生收款"
            description="依整段帳期（例如季繳 8–10 月）彙總；點明細可看各月各科。"
        >
            <template #actions>
                <Button as-child>
                    <Link href="/student-payments/create">
                        <Plus class="size-4" />
                        新增收款
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3"
            >
                <p class="text-xs text-muted-foreground">未繳合計</p>
                <p
                    class="mt-1 text-xl font-semibold text-destructive tabular-nums"
                >
                    {{ formatMoney(summary.unpaid_total) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3"
            >
                <p class="text-xs text-muted-foreground">已收合計</p>
                <p class="mt-1 text-xl font-semibold text-primary tabular-nums">
                    {{ formatMoney(summary.paid_total) }}
                </p>
            </div>
        </div>

        <form
            class="mb-4 flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:flex-row sm:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid flex-1 gap-2">
                <Label for="q">搜尋學生</Label>
                <Input id="q" v-model="q" placeholder="學號或姓名" />
            </div>
            <div class="grid gap-2 sm:w-40">
                <Label for="status">狀態</Label>
                <select
                    id="status"
                    v-model="status"
                    class="h-9 rounded-md border px-3"
                >
                    <option value="all">全部</option>
                    <option value="unpaid">未繳</option>
                    <option value="paid">已繳</option>
                    <option value="cancelled">已取消</option>
                </select>
            </div>
            <Button type="submit">篩選</Button>
        </form>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="row in rows.data"
                :key="row.id"
                :title="`${row.student_code ?? '—'} ${row.student_name}`"
                :subtitle="`${row.period_label}｜${row.course_count} 門課程`"
            >
                <MobileRecordField label="年級">{{
                    row.grade_name ?? '—'
                }}</MobileRecordField>
                <MobileRecordField label="總金額">{{
                    formatMoney(row.expected_total)
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
                v-if="rows.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無收款明細。可按右上角「新增收款」產生帳期。
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
                    <tr v-for="row in rows.data" :key="row.id" class="border-b">
                        <td class="px-3 py-2.5">
                            <div class="font-medium">
                                {{ row.student_code ?? '—' }}
                                {{ row.student_name }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.grade_name ?? '—' }}
                            </div>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ row.period_label }}
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ formatMoney(row.expected_total) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
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
                    <tr v-if="rows.data.length === 0">
                        <td
                            colspan="7"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            尚無收款明細。可按右上角「新增收款」產生帳期。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" class="mt-4" />
    </div>
</template>
