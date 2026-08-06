<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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

type Renewal = {
    available: boolean;
    button_label: string;
    start_date: string;
    pay_cycle: string;
    pay_cycle_label: string;
    course_count: number;
    span_months: number;
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
    period: {
        billing_year: number;
        billing_month: number;
        start_year?: number;
        start_month?: number;
        end_year?: number;
        end_month?: number;
        period_label?: string;
        expected_total: number;
        paid_total: number;
        course_count: number;
        status: string;
        paid_date: string | null;
        settled_by_name: string;
    } | null;
    renewal?: Renewal | null;
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
const renewalError = computed(
    () =>
        (page.props.errors as { renewal?: string } | undefined)?.renewal ?? '',
);

const renewSubmitting = ref(false);
const noteDialogOpen = ref(false);
const activeNoteRow = ref<Row | null>(null);

const formatMoney = (n: number) => n.toLocaleString('zh-TW');
const statusLabel = (status: string) =>
    ({ paid: '已繳', unpaid: '未繳', partial: '部分繳', cancelled: '已取消' })[
        status
    ] ?? status;

const pageTitle = computed(() => {
    if (!props.period) {
        return `收款明細｜${props.student.student_code ?? '—'} ${props.student.name}`;
    }
    const label =
        props.period.period_label ??
        `${props.period.billing_year}/${props.period.billing_month}`;

    return `收款明細｜${props.student.student_code ?? '—'} ${props.student.name}｜${label}`;
});

const pageDescription = computed(() => {
    const studentInfo = `${props.student.grade_name ?? '未設定年級'}${props.student.academic_year_name ? `｜${props.student.academic_year_name}` : ''}`;

    if (!props.period) {
        return studentInfo;
    }

    return `${studentInfo}｜${props.period.course_count} 門課程｜${statusLabel(props.period.status)}`;
});

const noteDialogTitle = computed(() => {
    const row = activeNoteRow.value;
    if (!row) {
        return '備註';
    }

    return `${row.billing_year}/${row.billing_month}｜${row.course_category_name}／${row.course_name}`;
});

const noteDialogItems = computed(() => {
    const note = activeNoteRow.value?.note?.trim();
    if (!note) {
        return [];
    }

    return note
        .split('｜')
        .map((part) => part.trim())
        .filter((part) => part !== '');
});

const openNote = (row: Row) => {
    if (!row.note) {
        return;
    }
    activeNoteRow.value = row;
    noteDialogOpen.value = true;
};

const confirmRenew = () => {
    const r = props.renewal;
    if (!r?.available || renewSubmitting.value) {
        return;
    }
    const ok = window.confirm(
        `${r.button_label}？\n\n將依上次設定：${r.pay_cycle_label}、${r.course_count} 門課，自 ${r.start_date} 起算產生 ${r.span_months} 個月帳期，並視為已收款。`,
    );
    if (!ok) {
        return;
    }
    renewSubmitting.value = true;
    router.post(
        `/student-payments/${props.student.id}/renew-next`,
        {},
        {
            onFinish: () => {
                renewSubmitting.value = false;
            },
        },
    );
};

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
            <Link
                href="/student-payments"
                class="text-sm text-primary underline-offset-4 hover:underline"
            >
                ← 返回明細紀錄
            </Link>
        </div>

        <PageHeader :title="pageTitle" :description="pageDescription">
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="renewal?.available"
                        variant="outline"
                        :disabled="renewSubmitting"
                        @click="confirmRenew"
                    >
                        {{ renewal.button_label }}
                    </Button>
                    <Button as-child>
                        <Link
                            :href="`/student-payments/create?student_id=${student.id}`"
                            >新增收款</Link
                        >
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>

        <div
            v-if="renewalError"
            class="mb-4 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm font-medium text-destructive"
        >
            {{ renewalError }}
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3"
            >
                <p class="text-xs text-muted-foreground">
                    {{ period ? '帳期總金額' : '未繳合計' }}
                </p>
                <p
                    class="mt-1 text-xl font-semibold text-destructive tabular-nums"
                >
                    {{
                        formatMoney(
                            period
                                ? period.expected_total
                                : summary.unpaid_total,
                        )
                    }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3"
            >
                <p class="text-xs text-muted-foreground">
                    {{ period ? '帳期已收' : '已收合計' }}
                </p>
                <p class="mt-1 text-xl font-semibold text-primary tabular-nums">
                    {{
                        formatMoney(
                            period ? period.paid_total : summary.paid_total,
                        )
                    }}
                </p>
            </div>
            <div
                v-if="period"
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3"
            >
                <p class="text-xs text-muted-foreground">收款資訊</p>
                <p class="mt-1 font-semibold">
                    {{ statusLabel(period.status) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ period.paid_date ?? '尚未收款' }}｜{{
                        period.settled_by_name
                    }}
                </p>
            </div>
        </div>

        <div
            class="desktop-table-wrap rounded-xl border border-sidebar-border/70"
        >
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
                            {{ row.course_category_name }} /
                            {{ row.course_name }}
                        </td>
                        <td class="px-3 py-2.5">{{ row.classroom_name }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ formatMoney(row.expected_amount) }}
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ formatMoney(row.paid_amount) }}
                        </td>
                        <td class="px-3 py-2.5">
                            {{ statusLabel(row.status) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ row.paid_date ?? '—' }}
                        </td>
                        <td class="px-3 py-2.5">{{ row.settled_by_name }}</td>
                        <td class="max-w-[14rem] px-3 py-2.5">
                            <button
                                v-if="row.note"
                                type="button"
                                class="block w-full truncate text-left text-xs text-primary underline-offset-2 hover:underline"
                                title="點擊查看完整備註"
                                @click="openNote(row)"
                            >
                                {{ row.note }}
                            </button>
                            <span
                                v-else
                                class="text-xs text-muted-foreground"
                                >—</span
                            >
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td
                            colspan="9"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            尚無帳期。可先按右上角「新增收款」產生應收。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" class="mt-4" />

        <Dialog v-model:open="noteDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ noteDialogTitle }}</DialogTitle>
                    <DialogDescription>完整備註內容</DialogDescription>
                </DialogHeader>
                <ul
                    v-if="noteDialogItems.length"
                    class="space-y-2 text-sm leading-relaxed text-foreground"
                >
                    <li
                        v-for="(item, index) in noteDialogItems"
                        :key="`${index}-${item}`"
                        class="flex gap-2 rounded-md border border-sidebar-border/60 bg-muted/20 px-3 py-2"
                    >
                        <span class="mt-0.5 text-muted-foreground">•</span>
                        <span class="min-w-0 flex-1 break-words">{{
                            item
                        }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">無備註</p>
            </DialogContent>
        </Dialog>
    </div>
</template>
