<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';

type RecordRow = {
    id: number;
    student_id: number;
    student_code: string | null;
    student_name: string;
    grade_name: string | null;
    fee_plan_id: number | null;
    fee_plan_name: string | null;
    fee_label: string;
    amount: number;
    paid_on: string;
    note: string | null;
    recorded_by: string | null;
    created_at: string | null;
};

type FeePlanOption = {
    id: number;
    label: string;
    package_total: number;
};

type StudentOption = {
    id: number;
    student_code: string | null;
    name: string;
    paid?: boolean;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    filters: { month: string; q: string };
    month_total: number;
    feePlans: FeePlanOption[];
    records: Paginated<RecordRow>;
}>();

const month = ref(props.filters.month);
const q = ref(props.filters.q);
const createOpen = ref(false);

watch(
    () => props.filters,
    (next) => {
        month.value = next.month;
        q.value = next.q;
    },
);

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
const deleteError = computed(
    () => (page.props.errors as Record<string, string | undefined>)?.delete,
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '年繳訂金紀錄', href: '/annual-deposit-records' },
        ],
    },
});

const currentMonth = () => new Date().toISOString().slice(0, 7);

const applyFilters = () => {
    router.get(
        '/annual-deposit-records',
        {
            month: month.value || undefined,
            q: q.value.trim() || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const clearFilters = () => {
    month.value = currentMonth();
    q.value = '';
    router.get(
        '/annual-deposit-records',
        { month: month.value },
        { preserveState: true, replace: true },
    );
};

const destroyRecord = (id: number) => {
    if (!window.confirm('確定要刪除此訂金紀錄嗎？')) {
        return;
    }
    router.delete(`/annual-deposit-records/${id}`, {
        preserveScroll: true,
    });
};

const studentLabel = (row: RecordRow) =>
    `${row.student_code ? `${row.student_code} ` : ''}${row.student_name}`.trim();

const form = useForm({
    paid_on: new Date().toISOString().slice(0, 10),
    student_ids: [] as number[],
    fee_plan_id: '' as number | '',
    fee_label: '',
    amount: '' as number | '',
    note: '',
});

const students = ref<StudentOption[]>([]);
const studentsLoading = ref(false);
const studentQuery = ref('');
const selectedIds = ref<number[]>([]);
const includePaid = ref(false);
const paidCount = ref(0);
let studentsAbort: AbortController | null = null;

const filteredStudents = computed(() => {
    const keyword = studentQuery.value.trim().toLowerCase();
    if (keyword === '') {
        return students.value;
    }

    return students.value.filter((s) => {
        const code = (s.student_code ?? '').toLowerCase();
        const name = s.name.toLowerCase();

        return code.includes(keyword) || name.includes(keyword);
    });
});

const resetCreateForm = () => {
    form.clearErrors();
    form.paid_on = new Date().toISOString().slice(0, 10);
    form.student_ids = [];
    form.fee_plan_id = '';
    form.fee_label = '';
    form.amount = '';
    form.note = '';
    studentQuery.value = '';
    selectedIds.value = [];
    includePaid.value = false;
    paidCount.value = 0;
};

const loadGrade2Students = async () => {
    studentsAbort?.abort();
    studentsAbort = new AbortController();
    studentsLoading.value = true;

    try {
        const params = new URLSearchParams();
        if (includePaid.value) {
            params.set('include_paid', '1');
        }
        const qs = params.toString();
        const res = await fetch(
            `/annual-deposit-records/students${qs ? `?${qs}` : ''}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: studentsAbort.signal,
            },
        );
        if (!res.ok) {
            throw new Error('載入失敗');
        }
        const data = (await res.json()) as {
            students: StudentOption[];
            paid_count?: number;
        };
        students.value = data.students ?? [];
        paidCount.value = data.paid_count ?? 0;
        // 隱藏已繳後，拿掉不在清單上的勾選
        const visible = new Set(students.value.map((s) => s.id));
        selectedIds.value = selectedIds.value.filter((id) => visible.has(id));
    } catch (e) {
        if ((e as Error).name === 'AbortError') {
            return;
        }
        students.value = [];
        paidCount.value = 0;
    } finally {
        studentsLoading.value = false;
    }
};

const onIncludePaidChange = () => {
    void loadGrade2Students();
};

const openCreate = () => {
    resetCreateForm();
    createOpen.value = true;
    void loadGrade2Students();
};

const onFeePlanChange = () => {
    if (form.fee_plan_id === '') {
        return;
    }
    const plan = props.feePlans.find((p) => p.id === Number(form.fee_plan_id));
    if (plan && form.fee_label.trim() === '') {
        form.fee_label = `${plan.label}訂金`;
    }
};

const toggleStudent = (id: number) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};

const selectAllStudents = () => {
    const ids = filteredStudents.value.map((s) => s.id);
    selectedIds.value = [...new Set([...selectedIds.value, ...ids])];
};

const clearStudents = () => {
    selectedIds.value = [];
};

const submitCreate = () => {
    form.student_ids = [...selectedIds.value];
    form
        .transform((data) => ({
            paid_on: data.paid_on,
            student_ids: data.student_ids,
            fee_plan_id:
                data.fee_plan_id === '' ? null : Number(data.fee_plan_id),
            fee_label: data.fee_label.trim(),
            amount: Number(data.amount || 0),
            note: data.note.trim() === '' ? null : data.note.trim(),
        }))
        .post('/annual-deposit-records', {
            preserveScroll: true,
            onSuccess: () => {
                createOpen.value = false;
                resetCreateForm();
            },
        });
};
</script>

<template>
    <Head title="年繳訂金紀錄" />
    <div class="page-shell">
        <PageHeader
            title="年繳訂金紀錄"
            description="按月份查看同學繳了哪些年繳相關費用。此處金額僅供備查，不計入營收報表。"
        >
            <template #actions>
                <Button type="button" @click="openCreate">
                    <Plus class="size-4" />
                    新增紀錄
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>
        <InputError v-if="deleteError" :message="deleteError" />

        <form
            class="mb-4 grid gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-1">
                <Label for="filter_month">繳費月份</Label>
                <Input
                    id="filter_month"
                    v-model="month"
                    type="month"
                    @change="applyFilters"
                />
            </div>
            <div class="grid gap-1 lg:col-span-2">
                <Label for="filter_q">搜尋</Label>
                <Input
                    id="filter_q"
                    v-model="q"
                    placeholder="姓名、學號、費用名稱、備註"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="submit">篩選</Button>
                <Button type="button" variant="outline" @click="clearFilters">
                    回到本月
                </Button>
            </div>
        </form>

        <div
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-950"
        >
            {{ month }} 合計
            <span class="ml-2 text-base font-semibold tabular-nums">
                {{ month_total.toLocaleString() }}
            </span>
            元
            <span class="ml-2 text-xs text-emerald-800/80">（不計入營收）</span>
        </div>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="row in records.data"
                :key="row.id"
                :title="studentLabel(row)"
                :subtitle="row.fee_label"
            >
                <MobileRecordField label="繳費日">{{
                    row.paid_on || '—'
                }}</MobileRecordField>
                <MobileRecordField label="年級">{{
                    row.grade_name ?? '—'
                }}</MobileRecordField>
                <MobileRecordField label="金額">{{
                    row.amount.toLocaleString()
                }}</MobileRecordField>
                <MobileRecordField label="備註">{{
                    row.note ?? '—'
                }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink
                            :href="`/annual-deposit-records/${row.id}/edit`"
                        />
                        <TableDeleteIconButton
                            @click="destroyRecord(row.id)"
                        />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="records.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                這個月還沒有年繳訂金紀錄。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b bg-muted/40 text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">繳費日</th>
                        <th class="px-3 py-2 font-medium">學生</th>
                        <th class="px-3 py-2 font-medium">年級</th>
                        <th class="px-3 py-2 font-medium">費用</th>
                        <th class="px-3 py-2 font-medium text-right">金額</th>
                        <th class="px-3 py-2 font-medium">備註</th>
                        <th class="px-3 py-2 font-medium">登記</th>
                        <th class="px-3 py-2 font-medium text-right">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in records.data"
                        :key="row.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-3 py-2 tabular-nums whitespace-nowrap">
                            {{ row.paid_on || '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ row.student_name }}</div>
                            <div
                                v-if="row.student_code"
                                class="text-xs text-muted-foreground"
                            >
                                {{ row.student_code }}
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ row.grade_name ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <div>{{ row.fee_label }}</div>
                            <div
                                v-if="row.fee_plan_name"
                                class="text-xs text-muted-foreground"
                            >
                                方案：{{ row.fee_plan_name }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ row.amount.toLocaleString() }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ row.note ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            <div>{{ row.recorded_by ?? '—' }}</div>
                            <div class="text-xs">{{ row.created_at ?? '' }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex justify-end gap-1">
                                <TableEditIconLink
                                    :href="`/annual-deposit-records/${row.id}/edit`"
                                />
                                <TableDeleteIconButton
                                    @click="destroyRecord(row.id)"
                                />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="records.data.length === 0">
                        <td
                            colspan="8"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            這個月還沒有年繳訂金紀錄。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="records.links" />

        <Dialog v-model:open="createOpen">
            <DialogContent
                class="max-h-[90vh] overflow-y-auto sm:max-w-3xl"
            >
                <DialogHeader>
                    <DialogTitle>新增年繳訂金紀錄</DialogTitle>
                    <DialogDescription>
                        國二學生可一次多選登記。訂金僅供備查，不會計入每月營收報表。
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1">
                            <Label for="create_paid_on">繳費日期</Label>
                            <Input
                                id="create_paid_on"
                                v-model="form.paid_on"
                                type="date"
                            />
                            <InputError :message="form.errors.paid_on" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="create_fee_plan">年繳方案（選填）</Label>
                            <select
                                id="create_fee_plan"
                                v-model="form.fee_plan_id"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                                @change="onFeePlanChange"
                            >
                                <option value="">不指定方案</option>
                                <option
                                    v-for="plan in feePlans"
                                    :key="plan.id"
                                    :value="plan.id"
                                >
                                    {{ plan.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.fee_plan_id" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="create_fee_label">費用名稱</Label>
                            <Input
                                id="create_fee_label"
                                v-model="form.fee_label"
                                placeholder="例：科研全科年繳訂金"
                            />
                            <InputError :message="form.errors.fee_label" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="create_amount">金額（元）</Label>
                            <Input
                                id="create_amount"
                                v-model="form.amount"
                                type="number"
                                min="1"
                                step="1"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div class="grid gap-1 sm:col-span-2">
                            <Label for="create_note">備註</Label>
                            <Input
                                id="create_note"
                                v-model="form.note"
                                placeholder="選填，套用到每位勾選的學生"
                            />
                            <InputError :message="form.errors.note" />
                        </div>
                    </div>

                    <div class="space-y-2 rounded-md border p-3">
                        <p class="text-sm font-medium">指定國二學生</p>
                        <p
                            v-if="paidCount > 0"
                            class="text-xs text-muted-foreground"
                        >
                            已繳訂金 {{ paidCount }} 人（預設不列出）
                        </p>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="includePaid"
                                type="checkbox"
                                class="size-4"
                                @change="onIncludePaidChange"
                            />
                            顯示已繳訂金學生（可再登記）
                        </label>
                        <div class="grid gap-1">
                            <Label for="deposit-student-q">篩選姓名／學號</Label>
                            <Input
                                id="deposit-student-q"
                                v-model="studentQuery"
                                type="search"
                                placeholder="輸入姓名或學號"
                                class="h-9"
                            />
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="studentsLoading"
                                @click="selectAllStudents"
                            >
                                全選
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="clearStudents"
                            >
                                清除
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                已選 {{ selectedIds.length }} 人｜顯示
                                {{ filteredStudents.length }}／{{
                                    students.length
                                }}
                                人
                            </p>
                        </div>
                        <InputError :message="form.errors.student_ids" />
                        <p
                            v-if="studentsLoading"
                            class="text-sm text-muted-foreground"
                        >
                            載入中…
                        </p>
                        <ul
                            v-else
                            class="max-h-56 space-y-1 overflow-y-auto text-sm"
                        >
                            <li
                                v-for="s in filteredStudents"
                                :key="s.id"
                            >
                                <label
                                    class="flex cursor-pointer items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4"
                                        :checked="selectedIds.includes(s.id)"
                                        @change="toggleStudent(s.id)"
                                    />
                                    <span
                                        class="text-muted-foreground tabular-nums"
                                    >
                                        {{ s.student_code || '—' }}
                                    </span>
                                    <span>{{ s.name }}</span>
                                    <span
                                        v-if="s.paid"
                                        class="text-xs text-amber-700"
                                        >（已繳訂金）</span
                                    >
                                </label>
                            </li>
                            <li
                                v-if="filteredStudents.length === 0"
                                class="py-4 text-center text-muted-foreground"
                            >
                                {{
                                    students.length === 0
                                        ? paidCount > 0 && !includePaid
                                            ? '尚未繳訂金的學生已全部登記，可勾選上方「顯示已繳訂金」。'
                                            : '目前沒有國二在籍學生'
                                        : '沒有符合篩選的學生'
                                }}
                            </li>
                        </ul>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="createOpen = false"
                        >
                            取消
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            儲存紀錄
                            <span v-if="selectedIds.length > 0">
                                （{{ selectedIds.length }} 人）
                            </span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
