<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ListPagination from '@/components/layout/ListPagination.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

type Row = {
    id: number;
    billing_year: number;
    billing_month: number;
    student_name: string;
    classroom_name: string;
    course_name: string;
    course_category_name: string;
    teacher_name: string | null;
    expected_amount: number;
    paid_amount: number;
    paid_date: string | null;
    status: string;
    action: string;
    performed_by_name: string;
    note: string | null;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    rows: Paginated<Row>;
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    filters: {
        year: string;
        month: string;
        student_name: string;
        teacher_id: string;
        action: string;
    };
}>();

const filterFieldClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs';

const filterForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    student_name: props.filters.student_name,
    teacher_id: props.filters.teacher_id ?? '',
    action: props.filters.action ?? '',
});

const applyFilters = () => {
    filterForm.get('/tuition-bag-statistics/reconciliation-records', {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    const now = new Date();
    const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    filterForm.year = String(prev.getFullYear());
    filterForm.month = String(prev.getMonth() + 1);
    filterForm.student_name = '';
    filterForm.teacher_id = '';
    filterForm.action = '';
    applyFilters();
};

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const actionLabel = (action: string) =>
    ({ confirm: '繳費確認', update: '調整', cancel: '取消' })[action] ?? action;

const statusLabel = (status: string) =>
    ({ paid: '已繳', cancelled: '已取消', unpaid: '未繳' })[status] ?? status;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學費袋統計', href: '/tuition-bag-statistics' },
            { title: '對帳紀錄', href: '/tuition-bag-statistics/reconciliation-records' },
        ],
    },
});
</script>

<template>
    <Head title="對帳紀錄" />

    <div class="page-shell">
        <PageHeader title="對帳紀錄" description="每一筆繳費確認、調整或取消操作都會留存在此。">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/tuition-bag-statistics">返回學費袋統計</Link>
                </Button>
            </template>
        </PageHeader>

        <form class="list-filter-panel rounded-xl border border-sidebar-border/70 bg-card p-4" @submit.prevent="applyFilters">
            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:items-end"
                :class="canFilterByTeacher ? 'lg:grid-cols-[5.5rem_5.5rem_minmax(0,12rem)_10rem_9rem_auto_auto]' : 'lg:grid-cols-[5.5rem_5.5rem_minmax(0,12rem)_9rem_auto_auto]'"
            >
                <div class="grid min-w-0 gap-1.5">
                    <label class="text-sm font-medium" for="year">年</label>
                    <input id="year" v-model="filterForm.year" type="number" min="2000" max="2100" :class="filterFieldClass" />
                </div>
                <div class="grid min-w-0 gap-1.5">
                    <label class="text-sm font-medium" for="month">月</label>
                    <select id="month" v-model="filterForm.month" :class="filterFieldClass" @change="applyFilters">
                        <option value="">全部</option>
                        <option v-for="m in 12" :key="m" :value="String(m)">{{ m }} 月</option>
                    </select>
                </div>
                <div class="grid min-w-0 gap-1.5 sm:col-span-2 lg:col-span-1">
                    <label class="text-sm font-medium" for="student_name">學生姓名</label>
                    <input
                        id="student_name"
                        v-model="filterForm.student_name"
                        type="text"
                        :class="filterFieldClass"
                        placeholder="輸入姓名篩選"
                    />
                </div>
                <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1.5">
                    <label class="text-sm font-medium" for="teacher_id">老師</label>
                    <select id="teacher_id" v-model="filterForm.teacher_id" :class="filterFieldClass" @change="applyFilters">
                        <option value="">全部</option>
                        <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                    </select>
                </div>
                <div class="grid min-w-0 gap-1.5">
                    <label class="text-sm font-medium" for="action">操作</label>
                    <select id="action" v-model="filterForm.action" :class="filterFieldClass" @change="applyFilters">
                        <option value="">全部</option>
                        <option value="confirm">繳費確認</option>
                        <option value="update">調整</option>
                        <option value="cancel">取消</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:contents">
                    <Button type="submit" class="h-10 w-full lg:w-auto lg:min-w-[5.5rem]" :disabled="filterForm.processing">
                        查詢
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 w-full lg:w-auto lg:min-w-[5.5rem]"
                        :disabled="filterForm.processing"
                        @click="resetFilters"
                    >
                        重設
                    </Button>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="px-3 py-2 text-left whitespace-nowrap">時間</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">帳期</th>
                        <th class="px-3 py-2 text-left">學生</th>
                        <th class="px-3 py-2 text-left">班級</th>
                        <th class="px-3 py-2 text-left">課程</th>
                        <th v-if="canFilterByTeacher" class="px-3 py-2 text-left">老師</th>
                        <th class="px-3 py-2 text-right whitespace-nowrap">應收</th>
                        <th class="px-3 py-2 text-right whitespace-nowrap">已收</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">收款日</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">操作</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">狀態</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">操作人</th>
                        <th class="px-3 py-2 text-left">備註</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b">
                        <td class="px-3 py-2 whitespace-nowrap tabular-nums text-muted-foreground">{{ row.created_at }}</td>
                        <td class="px-3 py-2 whitespace-nowrap tabular-nums">{{ row.billing_year }}/{{ row.billing_month }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ row.student_name }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ row.classroom_name }}</td>
                        <td class="px-3 py-2">{{ row.course_category_name }} / {{ row.course_name }}</td>
                        <td v-if="canFilterByTeacher" class="px-3 py-2 whitespace-nowrap">{{ row.teacher_name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.expected_amount) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.paid_amount) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap tabular-nums">{{ row.paid_date ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ actionLabel(row.action) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ statusLabel(row.status) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ row.performed_by_name }}</td>
                        <td class="px-3 py-2 text-muted-foreground">{{ row.note ?? '—' }}</td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td :colspan="canFilterByTeacher ? 13 : 12" class="px-3 py-8 text-center text-muted-foreground">
                            目前沒有對帳紀錄。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" />
    </div>
</template>
