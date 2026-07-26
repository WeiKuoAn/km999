<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生管理', href: '/students' },
            { title: '繳費紀錄', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`繳費紀錄 - ${student.name}`" />
    <div class="page-shell">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">繳費紀錄（{{ student.name }}）</h1>
            <div class="flex flex-wrap gap-3 text-sm">
                <Link
                    :href="`/student-payments/${student.id}`"
                    class="text-primary underline-offset-4 hover:underline"
                >
                    學生收款明細
                </Link>
                <Link href="/students" class="text-primary underline-offset-4 hover:underline">返回學生管理</Link>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">帳期</th>
                        <th class="py-2 text-left">班級</th>
                        <th class="py-2 text-left">課程</th>
                        <th class="py-2 text-right">應收</th>
                        <th class="py-2 text-right">已收</th>
                        <th class="py-2 text-left">狀態</th>
                        <th class="py-2 text-left">收款日</th>
                        <th class="py-2 text-left">繳費確認人</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b">
                        <td class="py-2">{{ row.billing_year }}/{{ row.billing_month }}</td>
                        <td class="py-2">{{ row.classroom_name }}</td>
                        <td class="py-2">{{ row.course_category_name }} / {{ row.course_name }}</td>
                        <td class="py-2 text-right">{{ row.expected_amount }}</td>
                        <td class="py-2 text-right">{{ row.paid_amount }}</td>
                        <td class="py-2">{{ row.status === 'paid' ? '已繳' : '未繳' }}</td>
                        <td class="py-2">{{ row.paid_date ?? '-' }}</td>
                        <td class="py-2">{{ row.settled_by_name }}</td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td colspan="8" class="py-8 text-center text-muted-foreground">目前沒有繳費紀錄。</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link
                v-for="link in rows.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded border px-3 py-1 text-sm"
                :class="{ 'bg-primary text-primary-foreground': link.active, 'pointer-events-none opacity-50': !link.url }"
                v-html="link.label"
            />
        </div>
    </div>
</template>

