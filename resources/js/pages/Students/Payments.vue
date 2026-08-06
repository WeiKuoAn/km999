<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
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
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{
    student: { id: number; name: string };
    rows: Paginated<Row>;
}>();

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const statusLabel = (status: string) => {
    if (status === 'paid') return '已繳';
    if (status === 'cancelled') return '已取消';
    return '未繳';
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生管理', href: '/students' },
            { title: '繳費明細', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`繳費明細 - ${student.name}`" />
    <div class="page-shell">
        <PageHeader
            :title="`繳費明細（${student.name}）`"
            description="依帳期列出各科應收／已收。已繳紀錄會永久保留，即使之後停修該課程也不會消失。"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/courses-schedule`">
                        <CalendarDays class="size-4" />
                        課程與行事曆
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/student-payments/${student.id}`">學生收款明細</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/edit`">返回編輯</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 p-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">帳期</th>
                        <th class="py-2 text-left">課程</th>
                        <th class="py-2 text-right">應收</th>
                        <th class="py-2 text-right">已收</th>
                        <th class="py-2 text-left">狀態</th>
                        <th class="py-2 text-left">收款日</th>
                        <th class="py-2 text-left">收款人</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b">
                        <td class="py-2 whitespace-nowrap">
                            {{ row.billing_year }}/{{ row.billing_month }}
                        </td>
                        <td class="py-2">
                            {{ row.course_category_name }} / {{ row.course_name }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ formatMoney(row.expected_amount) }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ formatMoney(row.paid_amount) }}
                        </td>
                        <td class="py-2">{{ statusLabel(row.status) }}</td>
                        <td class="py-2 whitespace-nowrap">{{ row.paid_date ?? '—' }}</td>
                        <td class="py-2">{{ row.settled_by_name }}</td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td colspan="7" class="py-8 text-center text-muted-foreground">
                            目前沒有繳費紀錄。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" class="mt-4" />
    </div>
</template>
