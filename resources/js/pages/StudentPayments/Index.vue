<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    receipt_no?: string | null;
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
        billing_month: string;
        paid_month: string;
    };
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');
const billingMonth = ref(props.filters.billing_month ?? '');
const paidMonth = ref(props.filters.paid_month ?? '');

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        status.value = filters.status ?? 'all';
        billingMonth.value = filters.billing_month ?? '';
        paidMonth.value = filters.paid_month ?? '';
    },
);

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const detailHref = (row: Row) =>
    `/student-payments/${row.student_id}?from_year=${row.start_year}&from_month=${row.start_month}&to_year=${row.end_year}&to_month=${row.end_month}`;

const editHref = (row: Row) =>
    `/student-payments/create?student_id=${row.student_id}&edit_from=${row.start_year}-${row.start_month}&edit_to=${row.end_year}-${row.end_month}`;

const deleteError = computed(
    () => (page.props.errors as { delete?: string } | undefined)?.delete ?? '',
);

const deletingId = ref<number | null>(null);
const receiptOpen = ref(false);
const receiptRow = ref<Row | null>(null);

const receiptForm = useForm({
    from: '',
    to: '',
    receipt_no: '',
    paid_date: '',
});

const payCycleLabel = (cycle: string | null | undefined) =>
    cycle === 'monthly' ? '月繳' : cycle === 'annual' ? '年繳' : '季繳';

const openReceipt = (row: Row) => {
    receiptRow.value = row;
    receiptForm.clearErrors();
    receiptForm.from = `${row.start_year}-${row.start_month}`;
    receiptForm.to = `${row.end_year}-${row.end_month}`;
    receiptForm.receipt_no = row.receipt_no ?? '';
    receiptForm.paid_date = row.paid_date ?? '';
    receiptOpen.value = true;
};

const submitReceipt = () => {
    const row = receiptRow.value;
    if (!row) {
        return;
    }

    receiptForm.patch(`/student-payments/${row.student_id}/period-meta`, {
        preserveScroll: true,
        onSuccess: () => {
            receiptOpen.value = false;
            receiptRow.value = null;
        },
    });
};

const confirmDelete = (row: Row) => {
    if (deletingId.value !== null) {
        return;
    }
    const ok = window.confirm(
        `確定要刪除 ${row.student_code ?? ''} ${row.student_name}「${row.period_label}」的收款？\n\n總金額 ${formatMoney(row.expected_total)}，這一期各月各科的紀錄與本期收的教材費都會一併刪除，無法復原。\n\n注意：刪除後若重新收款，金額會依「最新」收費設定計算。`,
    );
    if (!ok) {
        return;
    }
    deletingId.value = row.id;
    router.delete(`/student-payments/${row.student_id}/period`, {
        data: {
            from: `${row.start_year}-${row.start_month}`,
            to: `${row.end_year}-${row.end_month}`,
        },
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
};

const applyFilters = () => {
    router.get(
        '/student-payments',
        {
            q: q.value.trim() || undefined,
            status: status.value === 'all' ? undefined : status.value,
            billing_month: billingMonth.value || undefined,
            paid_month: paidMonth.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const clearFilters = () => {
    q.value = '';
    status.value = 'all';
    billingMonth.value = '';
    paidMonth.value = '';
    router.get('/student-payments', {}, { preserveState: true, replace: true });
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

        <div
            v-if="deleteError"
            class="mb-4 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm font-medium text-destructive"
        >
            {{ deleteError }}
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
            class="mb-4 grid gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-1 sm:col-span-2">
                <Label for="q">搜尋</Label>
                <Input id="q" v-model="q" placeholder="學號、姓名或單據編號" />
            </div>
            <div class="grid gap-1">
                <Label for="billing_month">帳期含此月</Label>
                <Input
                    id="billing_month"
                    v-model="billingMonth"
                    type="month"
                />
            </div>
            <div class="grid gap-1">
                <Label for="paid_month">收款月份</Label>
                <Input id="paid_month" v-model="paidMonth" type="month" />
            </div>
            <div class="grid gap-1">
                <Label for="status">狀態</Label>
                <select
                    id="status"
                    v-model="status"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="all">全部</option>
                    <option value="unpaid">未繳</option>
                    <option value="paid">已繳</option>
                    <option value="cancelled">已取消</option>
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="submit">篩選</Button>
                <Button type="button" variant="outline" @click="clearFilters">
                    清除
                </Button>
            </div>
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
                <MobileRecordField label="單據編號">{{
                    row.receipt_no ?? '—'
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
                        <Button
                            v-if="row.status !== 'cancelled'"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link :href="editHref(row)">編輯</Link>
                        </Button>
                        <Button
                            v-if="row.status !== 'cancelled'"
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="openReceipt(row)"
                        >
                            收據輸入
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="text-destructive"
                            :disabled="deletingId !== null"
                            @click="confirmDelete(row)"
                        >
                            刪除
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
                        <th class="px-3 py-2 text-left">單據編號</th>
                        <th class="px-3 py-2 text-left">帳期</th>
                        <th class="px-3 py-2 text-right">總金額</th>
                        <th class="px-3 py-2 text-left">收款日</th>
                        <th class="px-3 py-2 text-left">收款人</th>
                        <th class="px-3 py-2 text-right">課程數</th>
                        <th class="px-3 py-2 text-left">操作</th>
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
                        <td class="px-3 py-2.5 font-mono text-sm whitespace-nowrap">
                            {{ row.receipt_no ?? '—' }}
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
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <Link
                                    :href="detailHref(row)"
                                    class="text-primary underline-offset-4 hover:underline"
                                >
                                    明細
                                </Link>
                                <Link
                                    v-if="row.status !== 'cancelled'"
                                    :href="editHref(row)"
                                    class="text-primary underline-offset-4 hover:underline"
                                >
                                    編輯
                                </Link>
                                <button
                                    v-if="row.status !== 'cancelled'"
                                    type="button"
                                    class="text-primary underline-offset-4 hover:underline"
                                    @click="openReceipt(row)"
                                >
                                    收據輸入
                                </button>
                                <button
                                    type="button"
                                    class="text-destructive underline-offset-4 hover:underline disabled:opacity-50"
                                    :disabled="deletingId !== null"
                                    @click="confirmDelete(row)"
                                >
                                    {{ deletingId === row.id ? '刪除中…' : '刪除' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td
                            colspan="8"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            尚無收款明細。可按右上角「新增收款」產生帳期。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" class="mt-4" />

        <Dialog v-model:open="receiptOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        收據輸入｜{{ receiptRow?.student_name ?? '' }}
                    </DialogTitle>
                    <DialogDescription>
                        已收款金額已鎖定，此處只能改收據號碼與收款日。
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="receiptRow"
                    class="space-y-3 text-sm"
                >
                    <p>
                        <span class="text-muted-foreground">學生</span>
                        {{ receiptRow.student_code ?? '—' }}
                        {{ receiptRow.student_name }}
                        <span
                            v-if="receiptRow.grade_name"
                            class="text-muted-foreground"
                        >
                            （{{ receiptRow.grade_name }}）
                        </span>
                    </p>
                    <p>
                        <span class="text-muted-foreground">帳期</span>
                        {{ receiptRow.period_label }}｜{{
                            payCycleLabel(receiptRow.pay_cycle)
                        }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">課程數</span>
                        {{ receiptRow.course_count }} 門
                    </p>
                    <p class="text-base font-semibold tabular-nums">
                        <span class="text-sm font-normal text-muted-foreground"
                            >總金額（鎖定）</span
                        >
                        {{ formatMoney(receiptRow.expected_total) }}
                    </p>
                </div>

                <form class="mt-2 grid gap-4" @submit.prevent="submitReceipt">
                    <div class="grid gap-1.5">
                        <Label for="receipt_no">收據號碼</Label>
                        <Input
                            id="receipt_no"
                            v-model="receiptForm.receipt_no"
                            maxlength="20"
                            autocomplete="off"
                        />
                        <InputError :message="receiptForm.errors.receipt_no" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="paid_date">收款日</Label>
                        <Input
                            id="paid_date"
                            v-model="receiptForm.paid_date"
                            type="date"
                        />
                        <InputError :message="receiptForm.errors.paid_date" />
                    </div>
                    <InputError :message="receiptForm.errors.from" />
                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="receiptOpen = false"
                        >
                            取消
                        </Button>
                        <Button
                            type="submit"
                            :disabled="receiptForm.processing"
                        >
                            儲存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
