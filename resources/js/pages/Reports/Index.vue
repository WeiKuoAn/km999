<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    year: number;
    rows: Array<{ month: number; revenue: number }>;
    yearTotal: number;
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    filters: {
        year: string;
        teacher_id: string;
    };
}>();

const filterForm = useForm({
    year: props.filters.year,
    teacher_id: props.filters.teacher_id ?? '',
});

const applyFilters = () => {
    filterForm.get('/reports', {
        preserveState: true,
        replace: true,
    });
};

const monthLabel = (m: number) => `${m}月`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: '每月營收報表', href: '/reports' }],
    },
});
</script>

<template>
    <Head title="每月營收報表" />

    <div class="page-shell">
        <h1 class="text-xl font-semibold">每月營收報表</h1>
        <p class="text-sm text-muted-foreground">依月報表加總顯示每月營收（已完成繳費確認且已收款）。</p>

        <div class="flex flex-wrap gap-2">
            <Button variant="outline" as-child>
                <Link href="/reports/attendance-rate">班級出席率</Link>
            </Button>
        </div>

        <form class="rounded-xl border border-sidebar-border/70 p-4" @submit.prevent="applyFilters">
            <div class="form-filter-inline" :class="{ 'form-filter-inline--no-teacher': !canFilterByTeacher }">
                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="year">年份</label>
                    <input id="year" v-model="filterForm.year" type="number" min="2000" max="2100" class="h-9 rounded-md border px-3" @change="applyFilters" />
                </div>
                <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1">
                    <label class="text-sm font-medium" for="teacher_id">老師</label>
                    <select id="teacher_id" v-model="filterForm.teacher_id" class="h-9 w-full rounded-md border bg-background px-3" @change="applyFilters">
                        <option value="">全部</option>
                        <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                    </select>
                </div>
                <Button type="submit" class="form-filter-inline__submit h-10 w-full sm:w-auto" :disabled="filterForm.processing">查詢</Button>
            </div>
        </form>

        <div class="rounded-xl border border-sidebar-border/70 p-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">月份</th>
                        <th class="py-2 text-right">營收</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.month" class="border-b">
                        <td class="py-2">{{ monthLabel(row.month) }}</td>
                        <td class="py-2 text-right">{{ row.revenue }}</td>
                    </tr>
                    <tr class="font-semibold">
                        <td class="py-2">全年合計</td>
                        <td class="py-2 text-right">{{ yearTotal }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

