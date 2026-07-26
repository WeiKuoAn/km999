<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import PageHeader from '@/components/layout/PageHeader.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import MultiMonthUnpaidList from '@/components/tuition-bag/MultiMonthUnpaidList.vue';

type PaymentStatus = 'paid' | 'partial' | 'unpaid' | 'none';

type BagRow = {
    classroom_id: number;
    student_name: string;
    unit_amount: number;
    date_cells: string[];
    session_count: number;
    expected_amount: number;
    is_paid: boolean;
    paid_amount: number | null;
    paid_date: string | null;
    payment_note: string | null;
};

type Section = {
    key: string;
    student_id?: number;
    title: string;
    subtitle: string | null;
    classroom_name?: string;
    schedule_hint?: string | null;
    rows: BagRow[];
    payment_status?: PaymentStatus;
    total_expected?: number;
    paid_date_display?: string | null;
};

type UnpaidStudent = {
    student_id: number;
    student_name: string;
    unpaid_months: Array<{
        year: number;
        month: number;
        label: string;
        total_expected: number;
        payment_status: 'unpaid' | 'partial';
        classroom_count: number;
        rows: BagRow[];
    }>;
    total_unpaid_amount: number;
};

const props = defineProps<{
    viewMode: 'single_month' | 'multi_unpaid';
    sections: Section[];
    students: UnpaidStudent[];
    rangeLabel: string | null;
    groupBy: 'student' | 'course';
    courseOptions: Array<{ id: number; name: string }>;
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    canManagePayment?: boolean;
    todayDate: string;
    filters: {
        months_back: string;
        year: string;
        month: string;
        student_name: string;
        course_id: string;
        teacher_id: string;
        payment_status: 'all' | 'paid' | 'unpaid';
    };
}>();

const monthScopeOptions = [
    { value: 'month', label: '單月' },
    { value: '3', label: '3 個月' },
    { value: '6', label: '6 個月' },
    { value: 'all', label: '全部' },
];

const isMultiUnpaidView = computed(() => props.viewMode === 'multi_unpaid');

const pageDescription = computed(() =>
    isMultiUnpaidView.value
        ? `掃描 ${props.rangeLabel ?? ''} 內未繳清的月份，依學生整理並顯示各班明細，可直接在此繳費確認。`
        : '依學生分組，列出各班的出勤日期（補課標示「（補）」、加課標示「（加）」）。勾選要繳費的班級後，於學生名稱旁進行繳費確認或取消繳費。',
);

const filterForm = useForm({
    months_back: props.filters.months_back ?? 'month',
    year: props.filters.year,
    month: props.filters.month,
    student_name: props.filters.student_name,
    course_id: props.filters.course_id,
    teacher_id: props.filters.teacher_id ?? '',
    payment_status: props.filters.payment_status ?? 'all',
});

const applyFilters = () => {
    filterForm.get('/tuition-bag-statistics', {
        preserveState: true,
        replace: true,
    });
};

const filterFieldClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs';

const filterGridClass = computed(() => {
    if (filterForm.months_back !== 'month') {
        return props.canFilterByTeacher
            ? 'lg:grid-cols-[7rem_minmax(0,8rem)_minmax(0,1fr)_8.5rem_auto_auto]'
            : 'lg:grid-cols-[7rem_minmax(0,8rem)_minmax(0,1fr)_auto_auto]';
    }

    return props.canFilterByTeacher
        ? 'lg:grid-cols-[7rem_4.5rem_5rem_minmax(0,8rem)_minmax(0,1fr)_8.5rem_7rem_auto_auto]'
        : 'lg:grid-cols-[7rem_4.5rem_5rem_minmax(0,8rem)_minmax(0,1fr)_7rem_auto_auto]';
});

const resetFilters = () => {
    filterForm.student_name = '';
    filterForm.course_id = '';
    filterForm.teacher_id = '';

    if (isMultiUnpaidView.value) {
        filterForm.months_back = '3';
        filterForm.payment_status = 'unpaid';
    } else {
        const now = new Date();
        const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        filterForm.months_back = 'month';
        filterForm.year = String(prev.getFullYear());
        filterForm.month = String(prev.getMonth() + 1);
        filterForm.payment_status = 'all';
    }

    applyFilters();
};

const formatMoney = (n: number | null) => (n === null ? '—' : n.toLocaleString('zh-TW'));

const isByStudent = () => props.groupBy === 'student';

const rowLabel = (row: BagRow) =>
    isByStudent() && row.unit_amount > 0 ? `${row.student_name} - $${formatMoney(row.unit_amount)}` : row.student_name;
const firstColumnLabel = () => (isByStudent() ? '課程／班級' : '姓名');

/** 各班日期依時間順序緊排，不跨班對齊留空 */
const rowDateLabels = (row: BagRow) => row.date_cells;

const paymentBadge = (status?: PaymentStatus): { label: string; class: string } | null => {
    switch (status) {
        case 'paid':
            return { label: '已繳費', class: 'bg-emerald-600 text-white' };
        case 'partial':
            return { label: '部分繳費', class: 'bg-amber-500 text-white' };
        case 'unpaid':
            return { label: '未繳費', class: 'bg-red-600 text-white' };
        default:
            return null;
    }
};

const openConfirmId = ref<number | null>(null);
const paidDateByStudent = reactive<Record<number, string>>({});
const selectedByStudent = reactive<Record<number, number[]>>({});
const submitting = ref(false);

const confirmSection = computed(() =>
    openConfirmId.value == null ? null : props.sections.find((s) => s.student_id === openConfirmId.value) ?? null,
);

const selectableRows = (section: Section) => section.rows.filter((r) => r.session_count > 0 && !r.is_paid);

const isRowSelected = (studentId: number, classroomId: number) =>
    (selectedByStudent[studentId] ?? []).includes(classroomId);

const toggleRow = (studentId: number, classroomId: number) => {
    const arr = selectedByStudent[studentId] ?? (selectedByStudent[studentId] = []);
    const idx = arr.indexOf(classroomId);
    if (idx >= 0) {
        arr.splice(idx, 1);
    } else {
        arr.push(classroomId);
    }
};

const selectedIds = (studentId: number) => selectedByStudent[studentId] ?? [];

const allSelected = (section: Section) => {
    if (section.student_id == null) {
        return false;
    }
    const rows = selectableRows(section);
    return rows.length > 0 && rows.every((r) => isRowSelected(section.student_id!, r.classroom_id));
};

const toggleAll = (section: Section) => {
    if (section.student_id == null) {
        return;
    }
    const rows = selectableRows(section);
    selectedByStudent[section.student_id] = allSelected(section) ? [] : rows.map((r) => r.classroom_id);
};

const selectedExpected = (section: Section) => {
    if (section.student_id == null) {
        return 0;
    }
    return section.rows
        .filter((r) => isRowSelected(section.student_id!, r.classroom_id))
        .reduce((sum, r) => sum + (r.expected_amount ?? 0), 0);
};

const openConfirmPanel = (section: Section) => {
    if (section.student_id == null || selectedIds(section.student_id).length === 0) {
        return;
    }
    paidDateByStudent[section.student_id] = props.todayDate;
    openConfirmId.value = section.student_id;
};

const closeConfirmPanel = () => {
    openConfirmId.value = null;
};

const submitConfirm = (section: Section) => {
    if (section.student_id == null) {
        return;
    }
    const classroomIds = selectedIds(section.student_id);
    if (classroomIds.length === 0) {
        return;
    }
    submitting.value = true;
    router.post(
        '/tuition-bag-statistics/confirm-payment',
        {
            student_id: section.student_id,
            year: Number(props.filters.year),
            month: Number(props.filters.month),
            paid_date: paidDateByStudent[section.student_id] || props.todayDate,
            classroom_ids: classroomIds,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeConfirmPanel();
                if (section.student_id != null) {
                    selectedByStudent[section.student_id] = [];
                }
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

const editing = ref<{ studentId: number; classroomId: number } | null>(null);
const editForm = reactive<{ paid_amount: string; paid_date: string }>({ paid_amount: '', paid_date: '' });

const editingSection = computed(() =>
    editing.value == null ? null : props.sections.find((s) => s.student_id === editing.value?.studentId) ?? null,
);

const editingRowLabel = computed(
    () => editingSection.value?.rows.find((r) => r.classroom_id === editing.value?.classroomId)?.student_name ?? '',
);

const openEdit = (section: Section, row: BagRow) => {
    if (section.student_id == null) {
        return;
    }
    openConfirmId.value = null;
    editing.value = { studentId: section.student_id, classroomId: row.classroom_id };
    editForm.paid_amount = String(row.paid_amount ?? 0);
    editForm.paid_date = row.paid_date ?? props.todayDate;
};

const closeEdit = () => {
    editing.value = null;
};

const submitEdit = () => {
    if (editing.value == null) {
        return;
    }
    submitting.value = true;
    router.post(
        '/tuition-bag-statistics/update-payment',
        {
            student_id: editing.value.studentId,
            classroom_id: editing.value.classroomId,
            year: Number(props.filters.year),
            month: Number(props.filters.month),
            paid_amount: Number(editForm.paid_amount),
            paid_date: editForm.paid_date,
        },
        {
            preserveScroll: true,
            onSuccess: () => closeEdit(),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

const cancelFromEdit = () => {
    if (editing.value == null) {
        return;
    }
    if (!window.confirm(`確定要取消「${editingRowLabel.value}」本月的繳費紀錄嗎？`)) {
        return;
    }
    const classroomId = editing.value.classroomId;
    submitting.value = true;
    router.post(
        '/tuition-bag-statistics/cancel-payment',
        {
            student_id: editing.value.studentId,
            year: Number(props.filters.year),
            month: Number(props.filters.month),
            classroom_ids: [classroomId],
        },
        {
            preserveScroll: true,
            onSuccess: () => closeEdit(),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: '學費袋統計', href: '/tuition-bag-statistics' }],
    },
});
</script>

<template>
    <Head title="學費袋統計" />

    <div class="page-shell space-y-4">
        <PageHeader
            title="學費袋統計"
            :description="pageDescription"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/tuition-bag-statistics/reconciliation-records">對帳紀錄</Link>
                </Button>
            </template>
        </PageHeader>

        <form class="list-filter-panel overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card p-4" @submit.prevent="applyFilters">
            <div
                class="grid min-w-[42rem] grid-cols-2 items-end gap-3 sm:grid-cols-3 lg:min-w-0"
                :class="filterGridClass"
            >
                <div class="grid min-w-0 gap-1.5">
                    <label class="text-sm font-medium" for="months_back">範圍</label>
                    <select
                        id="months_back"
                        v-model="filterForm.months_back"
                        :class="filterFieldClass"
                        @change="applyFilters"
                    >
                        <option
                            v-for="option in monthScopeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <template v-if="filterForm.months_back === 'month'">
                    <div class="grid min-w-0 gap-1.5">
                        <label class="text-sm font-medium" for="year">年</label>
                        <input
                            id="year"
                            v-model="filterForm.year"
                            type="number"
                            min="2000"
                            max="2100"
                            :class="filterFieldClass"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label class="text-sm font-medium" for="month">月</label>
                        <select
                            id="month"
                            v-model="filterForm.month"
                            :class="filterFieldClass"
                            @change="applyFilters"
                        >
                            <option v-for="m in 12" :key="m" :value="String(m)">{{ m }} 月</option>
                        </select>
                    </div>
                </template>
                <div class="col-span-2 grid min-w-0 gap-1.5 sm:col-span-1 lg:col-span-1">
                    <label class="text-sm font-medium" for="student_name">學生姓名</label>
                    <input
                        id="student_name"
                        v-model="filterForm.student_name"
                        type="text"
                        :class="filterFieldClass"
                        placeholder="輸入姓名篩選"
                    />
                </div>
                <div class="col-span-2 grid min-w-0 gap-1.5 sm:col-span-2 lg:col-span-1">
                    <label class="text-sm font-medium" for="course_id">課程</label>
                    <select
                        id="course_id"
                        v-model="filterForm.course_id"
                        :class="filterFieldClass"
                        @change="applyFilters"
                    >
                        <option value="">全部課程</option>
                        <option v-for="c in courseOptions" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                </div>
                <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1.5">
                    <label class="text-sm font-medium" for="teacher_id">老師</label>
                    <select
                        id="teacher_id"
                        v-model="filterForm.teacher_id"
                        :class="filterFieldClass"
                        @change="applyFilters"
                    >
                        <option value="">全部老師</option>
                        <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                    </select>
                </div>
                <div v-if="filterForm.months_back === 'month'" class="grid min-w-0 gap-1.5">
                    <label class="text-sm font-medium" for="payment_status">繳費狀態</label>
                    <select
                        id="payment_status"
                        v-model="filterForm.payment_status"
                        :class="filterFieldClass"
                        @change="applyFilters"
                    >
                        <option value="all">全部</option>
                        <option value="paid">繳費</option>
                        <option value="unpaid">未繳費</option>
                    </select>
                </div>
                <Button type="submit" class="col-span-1 h-10 w-full lg:w-auto lg:min-w-[4.5rem]" :disabled="filterForm.processing">
                    查詢
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="col-span-1 h-10 w-full lg:w-auto lg:min-w-[4.5rem]"
                    :disabled="filterForm.processing"
                    @click="resetFilters"
                >
                    重設
                </Button>
            </div>
        </form>

        <MultiMonthUnpaidList
            v-if="isMultiUnpaidView"
            :students="students"
            :can-manage-payment="canManagePayment"
            :today-date="todayDate"
        />

        <template v-else>
        <p v-if="sections.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
            此條件下沒有資料。請調整月份或篩選條件。
        </p>

        <section
            v-for="section in sections"
            :key="section.key"
            class="space-y-2 rounded-xl border border-sidebar-border/70 p-4"
        >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-primary">{{ section.title }}</h2>
                        <span
                            v-if="paymentBadge(section.payment_status)"
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="paymentBadge(section.payment_status)!.class"
                        >
                            {{ paymentBadge(section.payment_status)!.label }}
                        </span>
                    </div>
                    <p v-if="section.subtitle" class="text-sm text-muted-foreground">{{ section.subtitle }}</p>
                    <p v-if="section.schedule_hint" class="text-xs text-muted-foreground">{{ section.schedule_hint }}</p>
                    <p v-if="section.payment_status === 'paid' && section.paid_date_display" class="text-xs text-muted-foreground">
                        最後繳費日期：{{ section.paid_date_display }}
                    </p>
                </div>

                <div
                    v-if="canManagePayment && section.payment_status !== 'none' && section.student_id != null"
                    class="flex shrink-0 flex-wrap items-center gap-2"
                >
                    <span class="text-xs text-muted-foreground">
                        已勾選 {{ selectedIds(section.student_id).length }} 班
                    </span>
                    <Button
                        size="sm"
                        :disabled="submitting || selectedIds(section.student_id).length === 0"
                        @click="openConfirmPanel(section)"
                    >
                        繳費確認
                    </Button>
                </div>
            </div>

            <div class="space-y-3 lg:hidden">
                <MobileRecordCard
                    v-for="(row, ri) in section.rows"
                    :key="`${section.key}-mobile-${ri}`"
                    :title="rowLabel(row)"
                >
                    <template v-if="canManagePayment && section.student_id != null" #badge>
                        <button
                            v-if="row.is_paid"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium text-primary hover:bg-primary/10"
                            @click="openEdit(section, row)"
                        >
                            <Pencil class="size-3.5" />
                            編輯
                        </button>
                        <label v-else-if="row.session_count > 0" class="inline-flex items-center gap-1.5 text-xs font-medium">
                            <input
                                type="checkbox"
                                class="size-4 rounded border"
                                :checked="isRowSelected(section.student_id, row.classroom_id)"
                                @change="toggleRow(section.student_id, row.classroom_id)"
                            />
                            選取
                        </label>
                    </template>
                    <MobileRecordField label="出勤日期">
                        <span v-if="rowDateLabels(row).length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(label, di) in rowDateLabels(row)"
                                :key="`${section.key}-d-${ri}-${di}`"
                                class="rounded-md bg-muted px-2 py-0.5 font-normal tabular-nums"
                            >
                                {{ label }}
                            </span>
                        </span>
                        <span v-else class="font-normal text-muted-foreground">—</span>
                    </MobileRecordField>
                    <MobileRecordField label="堂數">{{ row.session_count }}</MobileRecordField>
                    <MobileRecordField label="繳費金額">{{ formatMoney(row.paid_amount) }}</MobileRecordField>
                    <MobileRecordField label="繳費日期">
                        {{ row.paid_date ?? (row.payment_note ? '' : '—') }}
                        <span v-if="row.payment_note" class="text-xs font-normal text-amber-700">
                            {{ row.payment_note }}
                        </span>
                    </MobileRecordField>
                </MobileRecordCard>
            </div>

            <div class="hidden overflow-x-auto rounded-md border lg:block">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40">
                            <th
                                v-if="canManagePayment && section.student_id != null"
                                class="w-10 px-2 py-2 text-center"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border"
                                    :checked="allSelected(section)"
                                    @change="toggleAll(section)"
                                />
                            </th>
                            <th class="sticky left-0 z-10 min-w-[8rem] border-r bg-muted/40 px-2 py-2 text-left">
                                {{ firstColumnLabel() }}
                            </th>
                            <th class="min-w-[12rem] px-2 py-2 text-left">出勤日期</th>
                            <th class="min-w-[3rem] px-2 py-2 text-center">堂數</th>
                            <th class="min-w-[5rem] px-2 py-2 text-center">最後繳費金額</th>
                            <th class="min-w-[6rem] px-2 py-2 text-center">最後繳費日期</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, ri) in section.rows" :key="`${section.key}-row-${ri}`" class="border-b">
                            <td
                                v-if="canManagePayment && section.student_id != null"
                                class="px-2 py-2 text-center"
                            >
                                <button
                                    v-if="row.is_paid"
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded-md text-primary hover:bg-primary/10"
                                    title="調整繳費"
                                    @click="openEdit(section, row)"
                                >
                                    <Pencil class="size-4" />
                                    <span class="sr-only">調整繳費</span>
                                </button>
                                <input
                                    v-else-if="row.session_count > 0"
                                    type="checkbox"
                                    class="size-4 rounded border"
                                    :checked="isRowSelected(section.student_id, row.classroom_id)"
                                    @change="toggleRow(section.student_id, row.classroom_id)"
                                />
                            </td>
                            <td class="sticky left-0 z-10 border-r bg-background px-2 py-2 font-medium">{{ rowLabel(row) }}</td>
                            <td class="px-2 py-2">
                                <span v-if="rowDateLabels(row).length" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="(label, di) in rowDateLabels(row)"
                                        :key="`${section.key}-d-${ri}-${di}`"
                                        class="rounded-md bg-muted px-2 py-0.5 tabular-nums"
                                    >
                                        {{ label }}
                                    </span>
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-2 py-2 text-center tabular-nums">{{ row.session_count }}</td>
                            <td class="px-2 py-2 text-center tabular-nums">{{ formatMoney(row.paid_amount) }}</td>
                            <td class="px-2 py-2 text-center text-muted-foreground">
                                {{ row.paid_date ?? (row.payment_note ? '' : '—') }}
                                <span v-if="row.payment_note" class="ml-1 text-xs text-amber-700">{{ row.payment_note }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Dialog
            :open="openConfirmId !== null"
            @update:open="(v: boolean) => { if (!v) closeConfirmPanel(); }"
        >
            <DialogContent v-if="confirmSection && confirmSection.student_id != null" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>繳費確認 — {{ confirmSection.title }}</DialogTitle>
                    <DialogDescription>
                        將為勾選的 {{ selectedIds(confirmSection.student_id).length }} 個班級確認繳費。
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid gap-1.5">
                        <label :for="`dlg-paid-date-${confirmSection.student_id}`" class="text-sm font-medium">繳費日期</label>
                        <input
                            :id="`dlg-paid-date-${confirmSection.student_id}`"
                            v-model="paidDateByStudent[confirmSection.student_id]"
                            type="date"
                            class="h-10 w-full rounded-md border px-3"
                        />
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2">
                        <span class="text-sm text-muted-foreground">金額總計</span>
                        <span class="text-lg font-semibold tabular-nums">{{ formatMoney(selectedExpected(confirmSection)) }} 元</span>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="submitting" @click="closeConfirmPanel">取消</Button>
                    <Button :disabled="submitting" @click="submitConfirm(confirmSection)">確認</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="editing !== null"
            @update:open="(v: boolean) => { if (!v) closeEdit(); }"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>調整繳費</DialogTitle>
                    <DialogDescription>{{ editingRowLabel }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid gap-1.5">
                        <label for="dlg-edit-date" class="text-sm font-medium">繳費日期</label>
                        <input
                            id="dlg-edit-date"
                            v-model="editForm.paid_date"
                            type="date"
                            class="h-10 w-full rounded-md border px-3"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <label for="dlg-edit-amount" class="text-sm font-medium">繳費金額</label>
                        <input
                            id="dlg-edit-amount"
                            v-model="editForm.paid_amount"
                            type="number"
                            min="0"
                            class="h-10 w-full rounded-md border px-3"
                        />
                    </div>
                </div>

                <DialogFooter class="sm:justify-between">
                    <Button
                        variant="outline"
                        class="text-destructive hover:text-destructive"
                        :disabled="submitting"
                        @click="cancelFromEdit"
                    >
                        取消繳費
                    </Button>
                    <div class="flex gap-2">
                        <Button variant="outline" :disabled="submitting" @click="closeEdit">關閉</Button>
                        <Button :disabled="submitting" @click="submitEdit">儲存</Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        </template>
    </div>
</template>
