<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import RevenuePieChart from '@/components/reports/RevenuePieChart.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type PieSlice = { label: string; value: number };

const props = defineProps<{
    year: number;
    monthRows: Array<{ month: number; revenue: number }>;
    yearTotal: number;
    pieByGrade: PieSlice[];
    pieBySubject: PieSlice[];
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

watch(
    () => props.filters,
    (filters) => {
        filterForm.year = filters.year;
        filterForm.teacher_id = filters.teacher_id ?? '';
    },
);

const applyFilters = () => {
    filterForm.get('/reports', {
        preserveState: true,
        replace: true,
    });
};

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

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
        <PageHeader
            title="每月營收報表"
            description="已完成繳費確認且已收款；顯示月份合計與圓餅圖（年級／科目）。"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/reports/attendance-rate">班級出席率</Link>
                </Button>
            </template>
        </PageHeader>

        <form
            class="mb-4 rounded-xl border border-sidebar-border/70 bg-card p-4"
            @submit.prevent="applyFilters"
        >
            <div
                class="form-filter-inline"
                :class="{ 'form-filter-inline--no-teacher': !canFilterByTeacher }"
            >
                <div class="grid gap-1">
                    <Label for="year">年份</Label>
                    <input
                        id="year"
                        v-model="filterForm.year"
                        type="number"
                        min="2000"
                        max="2100"
                        class="h-9 rounded-md border px-3"
                        @change="applyFilters"
                    />
                </div>
                <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1">
                    <Label for="teacher_id">老師</Label>
                    <select
                        id="teacher_id"
                        v-model="filterForm.teacher_id"
                        class="h-9 w-full rounded-md border bg-background px-3"
                        @change="applyFilters"
                    >
                        <option value="">全部</option>
                        <option
                            v-for="t in teacherOptions"
                            :key="t.id"
                            :value="String(t.id)"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                </div>
                <Button
                    type="submit"
                    class="form-filter-inline__submit h-10 w-full sm:w-auto"
                    :disabled="filterForm.processing"
                >
                    查詢
                </Button>
            </div>
        </form>

        <div
            class="mb-4 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-medium">{{ year }} 年營收</p>
                <p class="text-xl font-semibold tabular-nums text-primary">
                    {{ formatMoney(yearTotal) }}
                </p>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[28rem] text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th
                                v-for="row in monthRows"
                                :key="row.month"
                                class="px-1 py-1.5 text-center font-normal"
                            >
                                {{ monthLabel(row.month) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td
                                v-for="row in monthRows"
                                :key="row.month"
                                class="px-1 py-1.5 text-center tabular-nums"
                                :class="
                                    row.revenue > 0
                                        ? 'font-medium'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ formatMoney(row.revenue) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <RevenuePieChart
                :title="`${year} 年營收｜依年級`"
                :items="pieByGrade"
            />
            <RevenuePieChart
                :title="`${year} 年營收｜依科目`"
                :items="pieBySubject"
            />
        </div>
    </div>
</template>
