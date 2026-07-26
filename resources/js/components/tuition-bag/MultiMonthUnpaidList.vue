<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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

type UnpaidMonth = {
    year: number;
    month: number;
    label: string;
    total_expected: number;
    payment_status: 'unpaid' | 'partial';
    classroom_count: number;
    rows: BagRow[];
};

type UnpaidStudent = {
    student_id: number;
    student_name: string;
    unpaid_months: UnpaidMonth[];
    total_unpaid_amount: number;
};

type MonthKey = `${number}-${number}-${number}`;

const props = defineProps<{
    students: UnpaidStudent[];
    canManagePayment?: boolean;
    todayDate: string;
}>();

const formatMoney = (n: number | null) => (n === null ? '—' : n.toLocaleString('zh-TW'));

const rowLabel = (row: BagRow) =>
    row.unit_amount > 0 ? `${row.student_name} - $${formatMoney(row.unit_amount)}` : row.student_name;

const monthStatusLabel = (status: UnpaidMonth['payment_status']) =>
    status === 'partial' ? '部分繳費' : '未繳費';

const monthStatusClass = (status: UnpaidMonth['payment_status']) =>
    status === 'partial' ? 'bg-amber-500 text-white' : 'bg-red-600 text-white';

const monthKey = (studentId: number, year: number, month: number): MonthKey =>
    `${studentId}-${year}-${month}`;

const selectedByMonth = reactive<Record<string, number[]>>({});
const paidDateByMonth = reactive<Record<string, string>>({});
const submitting = ref(false);

const openConfirm = ref<{
    student_id: number;
    student_name: string;
    year: number;
    month: number;
    label: string;
} | null>(null);

const selectableRows = (month: UnpaidMonth) => month.rows.filter((r) => r.session_count > 0 && !r.is_paid);

const selectedIds = (studentId: number, year: number, month: number) =>
    selectedByMonth[monthKey(studentId, year, month)] ?? [];

const isRowSelected = (studentId: number, year: number, month: number, classroomId: number) =>
    selectedIds(studentId, year, month).includes(classroomId);

const toggleRow = (studentId: number, year: number, month: number, classroomId: number) => {
    const key = monthKey(studentId, year, month);
    const arr = selectedByMonth[key] ?? (selectedByMonth[key] = []);
    const idx = arr.indexOf(classroomId);
    if (idx >= 0) {
        arr.splice(idx, 1);
    } else {
        arr.push(classroomId);
    }
};

const allSelected = (studentId: number, month: UnpaidMonth) => {
    const rows = selectableRows(month);
    return rows.length > 0 && rows.every((r) => isRowSelected(studentId, month.year, month.month, r.classroom_id));
};

const toggleAll = (studentId: number, month: UnpaidMonth) => {
    const key = monthKey(studentId, month.year, month.month);
    const rows = selectableRows(month);
    selectedByMonth[key] = allSelected(studentId, month) ? [] : rows.map((r) => r.classroom_id);
};

const selectedExpected = (studentId: number, year: number, month: number, monthData: UnpaidMonth) =>
    monthData.rows
        .filter((r) => isRowSelected(studentId, year, month, r.classroom_id))
        .reduce((sum, r) => sum + (r.expected_amount ?? 0), 0);

const openConfirmPanel = (student: UnpaidStudent, month: UnpaidMonth) => {
    const ids = selectedIds(student.student_id, month.year, month.month);
    if (ids.length === 0) {
        return;
    }
    const key = monthKey(student.student_id, month.year, month.month);
    paidDateByMonth[key] = props.todayDate;
    openConfirm.value = {
        student_id: student.student_id,
        student_name: student.student_name,
        year: month.year,
        month: month.month,
        label: month.label,
    };
};

const closeConfirmPanel = () => {
    openConfirm.value = null;
};

const confirmMonthData = computed(() => {
    if (openConfirm.value == null) {
        return null;
    }
    const student = props.students.find((s) => s.student_id === openConfirm.value!.student_id);
    return student?.unpaid_months.find(
        (m) => m.year === openConfirm.value!.year && m.month === openConfirm.value!.month,
    ) ?? null;
});

const submitConfirm = () => {
    if (openConfirm.value == null) {
        return;
    }
    const { student_id, year, month } = openConfirm.value;
    const classroomIds = selectedIds(student_id, year, month);
    if (classroomIds.length === 0) {
        return;
    }
    const key = monthKey(student_id, year, month);
    submitting.value = true;
    router.post(
        '/tuition-bag-statistics/confirm-payment',
        {
            student_id,
            year,
            month,
            paid_date: paidDateByMonth[key] || props.todayDate,
            classroom_ids: classroomIds,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeConfirmPanel();
                selectedByMonth[key] = [];
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};
</script>

<template>
    <p v-if="students.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
        此範圍內沒有未繳費的學生。
    </p>

    <div v-else class="space-y-4">
        <section
            v-for="student in students"
            :key="student.student_id"
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-xs"
        >
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sidebar-border/70 bg-muted/25 px-4 py-3">
                <h2 class="text-lg font-semibold text-primary">{{ student.student_name }}</h2>
                <p class="text-sm text-muted-foreground">
                    合計應收
                    <span class="font-semibold tabular-nums text-foreground">${{ formatMoney(student.total_unpaid_amount) }}</span>
                </p>
            </div>

            <div
                v-for="month in student.unpaid_months"
                :key="`${student.student_id}-${month.year}-${month.month}`"
                class="border-b border-sidebar-border/70 last:border-b-0"
            >
                <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold">{{ month.label }}</h3>
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="monthStatusClass(month.payment_status)"
                        >
                            {{ monthStatusLabel(month.payment_status) }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ month.classroom_count }} 個班級 · 應收 ${{ formatMoney(month.total_expected) }}
                        </span>
                    </div>
                    <div v-if="canManagePayment" class="flex shrink-0 flex-wrap items-center gap-2">
                        <span class="text-xs text-muted-foreground">
                            已勾選 {{ selectedIds(student.student_id, month.year, month.month).length }} 班
                        </span>
                        <Button
                            size="sm"
                            :disabled="submitting || selectedIds(student.student_id, month.year, month.month).length === 0"
                            @click="openConfirmPanel(student, month)"
                        >
                            繳費確認
                        </Button>
                    </div>
                </div>

                <div class="overflow-x-auto px-4 pb-4">
                    <div class="overflow-x-auto rounded-md border border-sidebar-border/70">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b bg-muted/40">
                                    <th v-if="canManagePayment" class="w-10 px-2 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border"
                                            :checked="allSelected(student.student_id, month)"
                                            @change="toggleAll(student.student_id, month)"
                                        />
                                    </th>
                                    <th class="min-w-[12rem] px-2 py-2 text-left">課程／班級</th>
                                    <th class="min-w-[10rem] px-2 py-2 text-left">出勤日期</th>
                                    <th class="min-w-[3rem] px-2 py-2 text-center">堂數</th>
                                    <th class="min-w-[5rem] px-2 py-2 text-center">應繳金額</th>
                                    <th class="min-w-[5rem] px-2 py-2 text-center">狀態</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, ri) in month.rows"
                                    :key="`${student.student_id}-${month.year}-${month.month}-${ri}`"
                                    class="border-b last:border-b-0"
                                >
                                    <td v-if="canManagePayment" class="px-2 py-2 text-center">
                                        <input
                                            v-if="row.session_count > 0 && !row.is_paid"
                                            type="checkbox"
                                            class="size-4 rounded border"
                                            :checked="isRowSelected(student.student_id, month.year, month.month, row.classroom_id)"
                                            @change="toggleRow(student.student_id, month.year, month.month, row.classroom_id)"
                                        />
                                    </td>
                                    <td class="px-2 py-2 font-medium">{{ rowLabel(row) }}</td>
                                    <td class="px-2 py-2">
                                        <span v-if="row.date_cells.length" class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="(label, di) in row.date_cells"
                                                :key="di"
                                                class="rounded-md bg-muted px-2 py-0.5 tabular-nums"
                                            >
                                                {{ label }}
                                            </span>
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ row.session_count }}</td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ formatMoney(row.paid_amount) }}</td>
                                    <td class="px-2 py-2 text-center text-xs">
                                        <span v-if="row.payment_note" class="text-red-600">{{ row.payment_note }}</span>
                                        <span v-else-if="row.is_paid" class="text-emerald-700">已繳</span>
                                        <span v-else class="text-red-600">（未繳費）</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <Dialog
        :open="openConfirm !== null"
        @update:open="(v: boolean) => { if (!v) closeConfirmPanel(); }"
    >
        <DialogContent v-if="openConfirm && confirmMonthData" class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>繳費確認 — {{ openConfirm.student_name }}</DialogTitle>
                <DialogDescription>
                    {{ openConfirm.label }}，將為勾選的
                    {{ selectedIds(openConfirm.student_id, openConfirm.year, openConfirm.month).length }}
                    個班級確認繳費。
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="grid gap-1.5">
                    <label
                        :for="`dlg-paid-date-${openConfirm.student_id}-${openConfirm.year}-${openConfirm.month}`"
                        class="text-sm font-medium"
                    >
                        繳費日期
                    </label>
                    <input
                        :id="`dlg-paid-date-${openConfirm.student_id}-${openConfirm.year}-${openConfirm.month}`"
                        v-model="paidDateByMonth[monthKey(openConfirm.student_id, openConfirm.year, openConfirm.month)]"
                        type="date"
                        class="h-10 w-full rounded-md border px-3"
                    />
                </div>
                <div class="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-sm text-muted-foreground">金額總計</span>
                    <span class="text-lg font-semibold tabular-nums">
                        {{ formatMoney(selectedExpected(openConfirm.student_id, openConfirm.year, openConfirm.month, confirmMonthData)) }} 元
                    </span>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="submitting" @click="closeConfirmPanel">取消</Button>
                <Button :disabled="submitting" @click="submitConfirm">確認</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
