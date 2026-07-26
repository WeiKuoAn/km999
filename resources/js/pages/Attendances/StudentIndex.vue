<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import ClassroomFilterSelect from '@/components/ClassroomFilterSelect.vue';
import AttendanceStatusSelect from '@/components/AttendanceStatusSelect.vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { attendanceStatusBadgeClass } from '@/lib/attendanceStatus';
import { classroomSwatchStyle } from '@/lib/classroomColor';
import { formatCourseLabel } from '@/lib/courseLabel';

type Row = {
    id: number;
    classroom_id: number;
    class_date: string;
    status: 'present' | 'absent' | 'late' | 'excused' | 'makeup' | 'extra';
    classroom_name: string;
    classroom_color: string | null;
    course_name: string;
    course_category_name: string;
    duration_hours: number | null;
    student_name: string;
    student_phone: string | null;
    is_makeup: boolean;
    makeup_date: string | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    rows: Paginated<Row>;
    classroomFilterOptions: Array<{ id: number; name: string; color: string | null }>;
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    filters: {
        classroom_id: string;
        teacher_id: string;
        student_name: string;
        status: string;
        weekday: string;
        month: string;
        date_from: string;
        date_to: string;
    };
}>();

const page = usePage();

const filterForm = useForm<{
    classroom_id: string;
    teacher_id: string;
    student_name: string;
    status: string;
    weekday: string;
    month: string;
    date_from: string;
    date_to: string;
}>({
    classroom_id: props.filters.classroom_id,
    teacher_id: props.filters.teacher_id ?? '',
    student_name: props.filters.student_name,
    status: props.filters.status,
    weekday: props.filters.weekday,
    month: props.filters.month,
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
});

const weekdayOptions = [
    { value: '1', label: '週一' },
    { value: '2', label: '週二' },
    { value: '3', label: '週三' },
    { value: '4', label: '週四' },
    { value: '5', label: '週五' },
    { value: '6', label: '週六' },
    { value: '7', label: '週日' },
];

const currentMonthValue = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const applyFilters = () => {
    filterForm.get('/student-attendances', {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.classroom_id = '';
    filterForm.teacher_id = '';
    filterForm.student_name = '';
    filterForm.status = '';
    filterForm.weekday = '';
    filterForm.month = currentMonthValue();
    filterForm.date_from = '';
    filterForm.date_to = '';
    applyFilters();
};

const weekdayLabel = (dateStr: string | null): string => {
    if (!dateStr) {
        return '';
    }
    const [y, m, d] = dateStr.split('-').map(Number);
    if (!y || !m || !d) {
        return '';
    }
    const names = ['日', '一', '二', '三', '四', '五', '六'];
    const day = new Date(y, m - 1, d).getDay();

    return `（${names[day]}）`;
};

const dateWithWeekday = (dateStr: string | null): string =>
    dateStr ? `${dateStr} ${weekdayLabel(dateStr)}` : '—';

const statusText = (status: Row['status']) =>
    ({ present: '出席', absent: '缺席', late: '遲到', excused: '請假', makeup: '補課', extra: '加課' })[status] ?? status;

const editAttendanceHref = (row: Row) => {
    const returnUrl = encodeURIComponent(page.url);
    return `/student-attendances/${row.id}/edit?return=${returnUrl}`;
};

const removeAttendance = (row: Row) => {
    if (!window.confirm(`確定要移除「${row.student_name}」於 ${row.class_date} 的出勤紀錄嗎？`)) {
        return;
    }
    const returnUrl = encodeURIComponent(page.url);
    router.delete(`/student-attendances/${row.id}?return=${returnUrl}`, { preserveScroll: true });
};

const selectedIds = ref<number[]>([]);

watch(
    () => props.rows.data,
    () => {
        selectedIds.value = [];
    },
);

const isSelected = (id: number) => selectedIds.value.includes(id);

const toggleRow = (id: number) => {
    if (isSelected(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};

const allPageSelected = computed(
    () => props.rows.data.length > 0 && props.rows.data.every((row) => isSelected(row.id)),
);

const somePageSelected = computed(
    () => props.rows.data.some((row) => isSelected(row.id)) && !allPageSelected.value,
);

const toggleAllPage = () => {
    if (allPageSelected.value) {
        const pageIds = new Set(props.rows.data.map((row) => row.id));
        selectedIds.value = selectedIds.value.filter((id) => !pageIds.has(id));
    } else {
        const merged = new Set([...selectedIds.value, ...props.rows.data.map((row) => row.id)]);
        selectedIds.value = [...merged];
    }
};

const bulkRemoveAttendance = () => {
    const count = selectedIds.value.length;
    if (count === 0) {
        return;
    }
    if (!window.confirm(`確定要移除已勾選的 ${count} 筆出勤紀錄嗎？此操作無法復原。`)) {
        return;
    }
    const returnUrl = encodeURIComponent(page.url);
    router.delete(`/student-attendances/bulk?return=${returnUrl}`, {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
};

const courseLabel = (row: Row) =>
    formatCourseLabel(row.course_category_name, row.course_name, row.duration_hours);

defineOptions({
    layout: {
        breadcrumbs: [{ title: '學生出勤', href: '/student-attendances' }],
    },
});
</script>

<template>
    <Head title="學生出勤" />
    <div class="page-shell">
        <PageHeader title="學生出勤">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/attendances">返回點名首頁</Link>
                </Button>
            </template>
        </PageHeader>

        <form class="list-filter-panel rounded-xl border border-sidebar-border/70 p-4 md:p-5" @submit.prevent="applyFilters">
                <div class="filter-grid">
                    <div class="grid min-w-0 gap-1.5">
                        <label class="text-sm font-medium" for="classroom_id">班級</label>
                        <ClassroomFilterSelect
                            id="classroom_id"
                            v-model="filterForm.classroom_id"
                            :options="classroomFilterOptions"
                            :disabled="filterForm.processing"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div v-if="canFilterByTeacher" class="grid min-w-0 gap-1.5">
                        <label class="text-sm font-medium" for="teacher_id">老師</label>
                        <select
                            id="teacher_id"
                            v-model="filterForm.teacher_id"
                            class="h-10 w-full rounded-md border bg-background px-3"
                            :disabled="filterForm.processing"
                            @change="applyFilters"
                        >
                            <option value="">全部</option>
                            <option v-for="t in teacherOptions" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                        </select>
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label class="text-sm font-medium" for="student_name">學生姓名</label>
                        <input
                            id="student_name"
                            v-model="filterForm.student_name"
                            type="text"
                            class="h-10 w-full rounded-md border px-3"
                            placeholder="輸入學生"
                        />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label class="text-sm font-medium" for="weekday">星期</label>
                        <select
                            id="weekday"
                            v-model="filterForm.weekday"
                            class="h-10 w-full rounded-md border bg-background px-3"
                            :disabled="filterForm.processing"
                            @change="applyFilters"
                        >
                            <option value="">全部</option>
                            <option v-for="w in weekdayOptions" :key="w.value" :value="w.value">{{ w.label }}</option>
                        </select>
                    </div>
                    <div v-if="!canFilterByTeacher" class="min-w-0 max-sm:hidden" aria-hidden="true" />
                    <div class="filter-grid__date-row filter-grid__date-row--triple">
                        <div class="grid min-w-0 gap-1.5">
                            <label class="text-sm font-medium" for="month">月份</label>
                            <input
                                id="month"
                                v-model="filterForm.month"
                                type="month"
                                class="h-10 w-full rounded-md border bg-background px-3"
                                :disabled="filterForm.processing"
                                @change="applyFilters"
                            />
                        </div>
                        <div class="grid min-w-0 gap-1.5">
                            <label class="text-sm font-medium" for="date_from">日期（起）</label>
                            <input id="date_from" v-model="filterForm.date_from" type="date" class="h-10 w-full rounded-md border px-3" />
                        </div>
                        <div class="grid min-w-0 gap-1.5">
                            <label class="text-sm font-medium" for="date_to">日期（迄）</label>
                            <input id="date_to" v-model="filterForm.date_to" type="date" class="h-10 w-full rounded-md border px-3" />
                        </div>
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label class="text-sm font-medium" for="status">狀態</label>
                        <AttendanceStatusSelect
                            id="status"
                            v-model="filterForm.status"
                            include-all
                            :disabled="filterForm.processing"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div class="filter-grid__actions">
                        <Button type="submit" class="h-10 w-full sm:w-auto" :disabled="filterForm.processing">篩選</Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 w-full sm:w-auto"
                            :disabled="filterForm.processing"
                            @click="resetFilters"
                        >
                            重設
                        </Button>
                    </div>
                </div>
        </form>

        <div
            v-if="rows.data.length > 0"
            class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-muted/30 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    class="size-4 rounded border"
                    :checked="allPageSelected"
                    :indeterminate="somePageSelected"
                    @change="toggleAllPage"
                />
                <span>本頁全選（已選 {{ selectedIds.length }} 筆）</span>
            </label>
            <Button
                v-if="selectedIds.length > 0"
                type="button"
                variant="destructive"
                size="sm"
                class="w-full sm:w-auto"
                @click="bulkRemoveAttendance"
            >
                <Trash2 class="mr-1.5 size-4" />
                批量刪除（{{ selectedIds.length }}）
            </Button>
        </div>

        <!-- 手機：卡片列表 -->
        <div class="mobile-card-list mobile-card-list--until-lg">
            <MobileRecordCard
                v-for="row in rows.data"
                :key="row.id"
                :title="row.student_name"
                :subtitle="row.student_phone ?? undefined"
            >
                <template #badge>
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            class="size-4 rounded border"
                            :checked="isSelected(row.id)"
                            @change="toggleRow(row.id)"
                        />
                        <span
                            class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="attendanceStatusBadgeClass(row.status)"
                        >
                            {{ statusText(row.status) }}
                        </span>
                    </label>
                </template>
                <MobileRecordField label="日期">{{ dateWithWeekday(row.class_date) }}</MobileRecordField>
                <MobileRecordField label="班級">
                    <span class="inline-flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60"
                            :style="classroomSwatchStyle(row.classroom_color)"
                        />
                        {{ row.classroom_name }}
                    </span>
                </MobileRecordField>
                <MobileRecordField label="課程">{{ courseLabel(row) }}</MobileRecordField>
                <MobileRecordField label="補課時間">{{ row.makeup_date ?? '—' }}</MobileRecordField>
                <template #actions>
                    <TableEditIconLink :href="editAttendanceHref(row)" label="編輯出勤" />
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="removeAttendance(row)"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">移除出勤紀錄</span>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>移除出勤紀錄</TooltipContent>
                    </Tooltip>
                </template>
            </MobileRecordCard>
            <p
                v-if="rows.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                目前沒有符合條件的學生出勤資料。
            </p>
        </div>

        <!-- 平板以上：表格 -->
        <div class="desktop-table-wrap desktop-table-wrap--from-lg rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="w-10 text-left">
                            <input
                                type="checkbox"
                                class="size-4 rounded border"
                                :checked="allPageSelected"
                                :indeterminate="somePageSelected"
                                @change="toggleAllPage"
                            />
                        </th>
                        <th class="text-left">日期</th>
                        <th class="text-left">班級</th>
                        <th class="text-left">課程</th>
                        <th class="text-left">學生</th>
                        <th class="text-left">狀態</th>
                        <th class="text-left">補課時間</th>
                        <th class="text-left w-[120px]">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b">
                        <td>
                            <input
                                type="checkbox"
                                class="size-4 rounded border"
                                :checked="isSelected(row.id)"
                                @change="toggleRow(row.id)"
                            />
                        </td>
                        <td class="whitespace-nowrap">{{ dateWithWeekday(row.class_date) }}</td>
                        <td>
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60 shadow-sm"
                                    :style="classroomSwatchStyle(row.classroom_color)"
                                />
                                {{ row.classroom_name }}
                            </span>
                        </td>
                        <td>{{ courseLabel(row) }}</td>
                        <td>
                            {{ row.student_name }}
                            <span v-if="row.student_phone" class="text-muted-foreground"> ({{ row.student_phone }})</span>
                        </td>
                        <td>
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="attendanceStatusBadgeClass(row.status)"
                            >
                                {{ statusText(row.status) }}
                            </span>
                        </td>
                        <td class="tabular-nums">{{ row.makeup_date ?? '—' }}</td>
                        <td>
                            <div class="flex items-center gap-1">
                                <TableEditIconLink :href="editAttendanceHref(row)" label="編輯出勤" />
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="removeAttendance(row)"
                                        >
                                            <Trash2 class="size-4" />
                                            <span class="sr-only">移除出勤紀錄</span>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>移除出勤紀錄</TooltipContent>
                                </Tooltip>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td colspan="8" class="py-8 text-center text-muted-foreground">目前沒有符合條件的學生出勤資料。</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="rows.links" />
    </div>
</template>
