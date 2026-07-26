<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    filters: { year: string; month: string; teacher_id: string };
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    rows: Array<{
        classroom_id: number;
        classroom_name: string;
        course_id: number;
        course_name: string;
        category_name: string;
        teacher_name: string | null;
        student_count: number;
        total_count: number;
        attended_count: number;
        attendance_rate: number | null;
    }>;
}>();

const filterForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    teacher_id: props.filters.teacher_id ?? '',
});

const applyFilters = () => {
    filterForm.get('/reports/attendance-rate', {
        preserveState: true,
        replace: true,
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '每月營收報表', href: '/reports' },
            { title: '班級出席率', href: '/reports/attendance-rate' },
        ],
    },
});
</script>

<template>
    <Head title="班級出席率" />
    <div class="page-shell">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">班級出席率</h1>
                <p class="text-sm text-muted-foreground">以月份統計各班的出席率 =（出席+遲到+補課）/ 全部點名筆數。</p>
            </div>
            <Button variant="outline" as-child>
                <Link href="/reports">返回每月營收報表</Link>
            </Button>
        </div>

        <form class="rounded-xl border border-sidebar-border/70 p-4" @submit.prevent="applyFilters">
            <div class="form-filter-inline" :class="{ 'form-filter-inline--no-teacher': !canFilterByTeacher }">
                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="year">年</label>
                    <input id="year" v-model="filterForm.year" type="number" min="2000" max="2100" class="h-9 rounded-md border px-3" @change="applyFilters" />
                </div>
                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="month">月</label>
                    <select id="month" v-model="filterForm.month" class="h-9 rounded-md border px-3" @change="applyFilters">
                        <option v-for="m in 12" :key="m" :value="String(m)">{{ m }} 月</option>
                    </select>
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
                        <th class="py-2 text-left">班級</th>
                        <th class="py-2 text-left">課程</th>
                        <th class="py-2 text-left">老師</th>
                        <th class="py-2 text-right">學生數</th>
                        <th class="py-2 text-right">總點名筆數</th>
                        <th class="py-2 text-right">出席筆數</th>
                        <th class="py-2 text-right">出席率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.classroom_id" class="border-b">
                        <td class="py-2">{{ row.classroom_name }}</td>
                        <td class="py-2">{{ row.category_name }} / {{ row.course_name }}</td>
                        <td class="py-2">{{ row.teacher_name ?? '—' }}</td>
                        <td class="py-2 text-right">{{ row.student_count }}</td>
                        <td class="py-2 text-right">{{ row.total_count }}</td>
                        <td class="py-2 text-right">{{ row.attended_count }}</td>
                        <td class="py-2 text-right">{{ row.attendance_rate === null ? '-' : `${row.attendance_rate}%` }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="7" class="py-8 text-center text-muted-foreground">此月份目前沒有點名資料。</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
