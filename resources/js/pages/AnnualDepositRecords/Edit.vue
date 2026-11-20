<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PageHeader from '@/components/layout/PageHeader.vue';

type FeePlanOption = {
    id: number;
    label: string;
    package_total: number;
};

type StudentOption = {
    id: number;
    student_code: string | null;
    name: string;
    grade_name: string | null;
};

type RecordRow = {
    id: number;
    student_id: number;
    student_code: string | null;
    student_name: string;
    grade_name: string | null;
    fee_plan_id: number | null;
    fee_label: string;
    amount: number;
    paid_on: string;
    note: string | null;
};

const props = defineProps<{
    record: RecordRow;
    feePlans: FeePlanOption[];
}>();

const studentQuery = ref(
    `${props.record.student_code ? `${props.record.student_code} ` : ''}${props.record.student_name}`.trim(),
);
const results = ref<StudentOption[]>([]);
const open = ref(false);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let abortController: AbortController | null = null;

const form = useForm({
    student_id: props.record.student_id,
    fee_plan_id: (props.record.fee_plan_id ?? '') as number | '',
    fee_label: props.record.fee_label,
    amount: props.record.amount,
    paid_on: props.record.paid_on,
    note: props.record.note ?? '',
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '年繳訂金紀錄', href: '/annual-deposit-records' },
            { title: '編輯', href: '/annual-deposit-records' },
        ],
    },
});

const onFeePlanChange = () => {
    if (form.fee_plan_id === '') {
        return;
    }
    const plan = props.feePlans.find((p) => p.id === Number(form.fee_plan_id));
    if (plan && form.fee_label.trim() === '') {
        form.fee_label = `${plan.label}訂金`;
    }
};

const searchStudent = async (q: string) => {
    if (q.trim() === '') {
        results.value = [];
        open.value = false;
        return;
    }

    abortController?.abort();
    abortController = new AbortController();
    loading.value = true;

    try {
        const res = await fetch(
            `/student-payments/search?q=${encodeURIComponent(q.trim())}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: abortController.signal,
            },
        );
        if (!res.ok) {
            throw new Error('搜尋失敗');
        }
        const data = (await res.json()) as { students: StudentOption[] };
        results.value = data.students ?? [];
        open.value = true;
    } catch (e) {
        if ((e as Error).name === 'AbortError') {
            return;
        }
        results.value = [];
        open.value = false;
    } finally {
        loading.value = false;
    }
};

const onStudentQuery = () => {
    form.student_id = 0;
    if (timer) {
        clearTimeout(timer);
    }
    timer = setTimeout(() => {
        void searchStudent(studentQuery.value);
    }, 250);
};

const selectStudent = (student: StudentOption) => {
    form.student_id = student.id;
    studentQuery.value =
        `${student.student_code ? `${student.student_code} ` : ''}${student.name}`.trim();
    results.value = [];
    open.value = false;
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            fee_plan_id: data.fee_plan_id === '' ? null : Number(data.fee_plan_id),
            note: data.note.trim() === '' ? null : data.note.trim(),
        }))
        .put(`/annual-deposit-records/${props.record.id}`, {
            preserveScroll: true,
        });
};
</script>

<template>
    <Head title="編輯年繳訂金紀錄" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <PageHeader title="編輯年繳訂金紀錄">
            <template #actions>
                <Button as-child variant="outline">
                    <Link
                        :href="`/annual-deposit-records?paid_on=${record.paid_on}`"
                    >
                        返回列表
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form
            class="mt-4 space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4"
            @submit.prevent="submit"
        >
            <div class="relative grid gap-1">
                <Label for="student">學生</Label>
                <Input
                    id="student"
                    v-model="studentQuery"
                    placeholder="搜尋姓名或學號"
                    autocomplete="off"
                    @input="onStudentQuery"
                    @focus="open = results.length > 0"
                />
                <div
                    v-if="open && results.length"
                    class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-auto rounded-md border bg-popover shadow-md"
                >
                    <button
                        v-for="student in results"
                        :key="student.id"
                        type="button"
                        class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-muted"
                        @click="selectStudent(student)"
                    >
                        <span class="font-medium">
                            {{ student.name }}
                            <span
                                v-if="student.student_code"
                                class="ml-1 text-xs font-normal text-muted-foreground"
                            >
                                {{ student.student_code }}
                            </span>
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ student.grade_name ?? '未設定年級' }}
                        </span>
                    </button>
                </div>
                <p v-if="loading" class="text-xs text-muted-foreground">
                    搜尋中…
                </p>
                <InputError :message="form.errors.student_id" />
            </div>

            <div class="grid gap-1">
                <Label for="paid_on">繳費日期</Label>
                <Input id="paid_on" v-model="form.paid_on" type="date" />
                <InputError :message="form.errors.paid_on" />
            </div>

            <div class="grid gap-1">
                <Label for="fee_plan_id">年繳方案（選填）</Label>
                <select
                    id="fee_plan_id"
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
                <Label for="fee_label">費用名稱</Label>
                <Input id="fee_label" v-model="form.fee_label" />
                <InputError :message="form.errors.fee_label" />
            </div>

            <div class="grid gap-1">
                <Label for="amount">金額（元）</Label>
                <Input
                    id="amount"
                    v-model="form.amount"
                    type="number"
                    min="1"
                    step="1"
                />
                <InputError :message="form.errors.amount" />
            </div>

            <div class="grid gap-1">
                <Label for="note">備註</Label>
                <Input id="note" v-model="form.note" />
                <InputError :message="form.errors.note" />
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    儲存修改
                </Button>
            </div>
        </form>
    </div>
</template>
