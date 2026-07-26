<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';

type Row = {
    id: number;
    billing_year: number;
    billing_month: number;
    classroom_name: string;
    course_name: string;
    course_category_name: string;
    expected_amount: number;
    paid_amount: number;
    paid_date: string | null;
    status: string;
    settled_by_name: string;
    note: string | null;
    pay_cycle: string | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    student: {
        id: number;
        student_code: string | null;
        name: string;
        grade_name: string | null;
        academic_year_name: string | null;
    };
    rows: Paginated<Row>;
    summary: {
        unpaid_total: number;
        paid_total: number;
    };
}>();

const page = usePage();
const successMessage = computed(() => (page.props.flash as { success?: string } | undefined)?.success);

const formatMoney = (n: number) => n.toLocaleString('zh-TW');
const statusLabel = (status: string) =>
    ({ paid: '已繳', unpaid: '未繳', cancelled: '已取消' })[status] ?? status;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生收款', href: '/student-payments' },
            { title: '收款明細', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`收款明細 - ${student.name}`" />
    <div class="page-shell">
        <div class="mb-2">
            <Link href="/student-payments" class="text-sm text-primary underline-offset-4 hover:underline">
                ← 重新選擇學生
            </Link>
        </div>

        <PageHeader
            :title="`收款明細｜${student.student_code ?? '—'} ${student.name}`"
            :description="`${student.grade_name ?? '未設定年級'}${student.academic_year_name ? `｜${student.academic_year_name}` : ''}`"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="`/student-payments?student_id=${student.id}`">報名計價</Link>
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
            <div class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3">
                <p class="text-xs text-muted-foreground">未繳合計</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-destructive">
                    {{ formatMoney(summary.unpaid_total) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3">
                <p class="text-xs text-muted-foreground">已收合計</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-primary">
                    {{ formatMoney(summary.paid_total) }}
                </p>
            </div>
        </div>

        <div class="desktop-table-wrap rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30">
                        <th class="px-3 py-2 text-left">帳期</th>
                        <th class="px-3 py-2 text-left">課程</th>
                        <th class="px-3 py-2 text-left">班級</th>
                        <th class="px-3 py-2 text-right">應收</th>
                        <th class="px-3 py-2 text-right">已收</th>
                        <th class="px-3 py-2 text-left">狀態</th>
                        <th class="px-3 py-2 text-left">收款日</th>
                        <th class="px-3 py-2 text-left">確認人</th>
                        <th class="px-3 py-2 text-left">備註</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b">
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ row.billing_year }}/{{ row.billing_month }}
                        </td>
                        <td class="px-3 py-2.5">
                            {{ row.course_category_name }} / {{ row.course_name }}
                        </td>
                        <td class="px-3 py-2.5">{{ row.classroom_name }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ formatMoney(row.expected_amount) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ formatMoney(row.paid_amount) }}</td>
                        <td class="px-3 py-2.5">{{ statusLabel(row.status) }}</td>
                        <td class="px-3 py-2.5 whitespace-nowrap">{{ row.paid_date ?? '—' }}</td>
                        <td class="px-3 py-2.5">{{ row.settled_by_name }}</td>
                        <td class="max-w-[14rem] truncate px-3 py-2.5 text-xs text-muted-foreground" :title="row.note ?? ''">
                            {{ row.note ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td colspan="9" class="px-3 py-10 text-center text-muted-foreground">
                            尚無帳期。可先按右上角「報名計價」產生應收。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" class="mt-4" />
    </div>
</template>
