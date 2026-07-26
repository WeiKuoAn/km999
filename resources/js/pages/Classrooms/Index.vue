<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChartColumnBig, Plus, Users } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import TableIconLink from '@/components/table/TableIconLink.vue';
import { classroomSwatchStyle } from '@/lib/classroomColor';
import { formatCourseCellParts } from '@/lib/courseLabel';

type CourseLike = {
    id: number;
    course_category: { name: string } | null;
    name: string;
    course_prices: Array<{ level: string | null; tuition: number }>;
};

type Classroom = {
    id: number;
    name: string;
    color: string | null;
    schedules: Array<{
        weekday: number;
        start_time: string;
        end_time: string;
        course?: CourseLike | null;
    }>;
    status: 'active' | 'paused';
    course: CourseLike | null;
    grade_level: { id: number; name: string } | null;
    teacher: { id: number; name: string } | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    classrooms: Paginated<Classroom>;
    courseFilterOptions: Array<{ id: number; name: string }>;
    gradeFilterOptions: Array<{ id: number; name: string; code: number }>;
    teacherFilterOptions: Array<{ id: number; name: string }>;
    filters: {
        name: string;
        course_id: string;
        grade_level_id: string;
        teacher_id: string;
        status: '' | 'active' | 'paused';
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: '班級管理', href: '/classrooms' }],
    },
});

const filterForm = useForm({
    name: props.filters.name ?? '',
    course_id: props.filters.course_id ?? '',
    grade_level_id: props.filters.grade_level_id ?? '',
    teacher_id: props.filters.teacher_id ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    filterForm.get('/classrooms', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.name = '';
    filterForm.course_id = '';
    filterForm.grade_level_id = '';
    filterForm.teacher_id = '';
    filterForm.status = '';
    applyFilters();
};

const weekdayText = (day: number | null) =>
    ({ 1: '一', 2: '二', 3: '三', 4: '四', 5: '五', 6: '六', 7: '日' }[day ?? 0] ?? '-');
const toHm = (value: string | null | undefined): string => (value ? value.slice(0, 5) : '');
const formatTimeRanges = (c: Classroom) =>
    c.schedules.length
        ? c.schedules.map((s) => {
              const subject = s.course?.name ? `${s.course.name} ` : '';
              return `${subject}週${weekdayText(s.weekday)} ${toHm(s.start_time)} - ${toHm(s.end_time)}`;
          })
        : [];
const coursesForClassroom = (c: Classroom): CourseLike[] => {
    const seen = new Set<number>();
    const list: CourseLike[] = [];
    for (const s of c.schedules) {
        const course = s.course;
        if (!course?.id || seen.has(course.id)) {
            continue;
        }
        seen.add(course.id);
        list.push(course);
    }
    if (list.length > 0) {
        return list;
    }
    return c.course ? [c.course] : [];
};
const courseCell = (course: CourseLike) =>
    formatCourseCellParts({
        course_category: course.course_category ?? { name: '—' },
        name: course.name,
        course_prices: course.course_prices ?? [],
    });
const statusLabel = (status: Classroom['status']) => (status === 'active' ? '上課' : '停課');

const destroyClassroom = (id: number) => {
    if (!window.confirm('確定要刪除此班級嗎？')) return;
    router.delete(`/classrooms/${id}`);
};
</script>

<template>
    <Head title="班級管理" />
    <div class="page-shell">
        <PageHeader
            title="班級管理"
            description="設定班級年級、上課時間與開課區間；學生名單可由此班維護，並會顯示在行事曆。"
        >
            <template #actions>
                <Button as-child>
                    <Link href="/classrooms/create">
                        <Plus class="size-4" />
                        新增班級
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form
            class="list-filter-panel rounded-xl border border-sidebar-border/70 bg-card p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_120px_160px_140px_auto_auto] lg:items-end">
                <div class="grid gap-1">
                    <label for="name" class="text-sm font-medium">班級名稱</label>
                    <input
                        id="name"
                        v-model="filterForm.name"
                        type="text"
                        class="h-10 rounded-md border px-3"
                        placeholder="輸入班級名稱"
                        @change="applyFilters"
                    />
                </div>
                <div class="grid gap-1">
                    <label for="course_id" class="text-sm font-medium">課程</label>
                    <select
                        id="course_id"
                        v-model="filterForm.course_id"
                        class="h-10 rounded-md border px-3"
                        @change="applyFilters"
                    >
                        <option value="">全部</option>
                        <option v-for="course in courseFilterOptions" :key="course.id" :value="String(course.id)">
                            {{ course.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <label for="grade_level_id" class="text-sm font-medium">年級</label>
                    <select
                        id="grade_level_id"
                        v-model="filterForm.grade_level_id"
                        class="h-10 rounded-md border px-3"
                        @change="applyFilters"
                    >
                        <option value="">全部</option>
                        <option v-for="g in gradeFilterOptions" :key="g.id" :value="String(g.id)">
                            {{ g.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <label for="teacher_id" class="text-sm font-medium">老師</label>
                    <select
                        id="teacher_id"
                        v-model="filterForm.teacher_id"
                        class="h-10 rounded-md border px-3"
                        @change="applyFilters"
                    >
                        <option value="">全部</option>
                        <option v-for="teacher in teacherFilterOptions" :key="teacher.id" :value="String(teacher.id)">
                            {{ teacher.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <label for="status" class="text-sm font-medium">狀態</label>
                    <select
                        id="status"
                        v-model="filterForm.status"
                        class="h-10 rounded-md border px-3"
                        @change="applyFilters"
                    >
                        <option value="">全部</option>
                        <option value="active">上課</option>
                        <option value="paused">停課</option>
                    </select>
                </div>
                <Button type="submit" class="h-10 w-full" :disabled="filterForm.processing">查詢</Button>
                <Button type="button" variant="outline" class="h-10 w-full" :disabled="filterForm.processing" @click="resetFilters">
                    清除
                </Button>
            </div>
        </form>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="classroom in classrooms.data"
                :key="classroom.id"
                :title="classroom.name"
            >
                <template #badge>
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60"
                        :style="classroomSwatchStyle(classroom.color)"
                    />
                </template>
                <MobileRecordField label="年級">{{ classroom.grade_level?.name ?? '—' }}</MobileRecordField>
                <MobileRecordField label="課程">
                    <span
                        v-for="(course, ci) in coursesForClassroom(classroom)"
                        :key="`${classroom.id}-course-${course.id}-${ci}`"
                        class="block font-normal"
                        :class="ci > 0 ? 'mt-1' : ''"
                    >
                        <span class="block">{{ courseCell(course).title }}</span>
                        <span
                            v-for="(line, li) in courseCell(course).tierLines"
                            :key="`${classroom.id}-tier-${course.id}-${li}`"
                            class="block text-xs font-normal text-muted-foreground"
                        >
                            {{ line }}
                        </span>
                    </span>
                    <span v-if="coursesForClassroom(classroom).length === 0" class="font-normal">—</span>
                </MobileRecordField>
                <MobileRecordField label="老師">{{ classroom.teacher?.name ?? '—' }}</MobileRecordField>
                <MobileRecordField label="時間">
                    <span v-if="formatTimeRanges(classroom).length" class="block space-y-0.5 font-normal">
                        <span
                            v-for="(line, idx) in formatTimeRanges(classroom)"
                            :key="`${classroom.id}-t-${idx}`"
                            class="block tabular-nums"
                        >
                            {{ line }}
                        </span>
                    </span>
                    <span v-else class="font-normal">—</span>
                </MobileRecordField>
                <MobileRecordField label="狀態">{{ statusLabel(classroom.status) }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <Button variant="outline" size="sm" class="w-full sm:w-auto" as-child>
                            <Link :href="`/classrooms/${classroom.id}/students`">學生名單</Link>
                        </Button>
                        <Button variant="outline" size="sm" class="w-full sm:w-auto" as-child>
                            <Link :href="`/classrooms/${classroom.id}/attendance-rate`">出席率</Link>
                        </Button>
                        <TableEditIconLink :href="`/classrooms/${classroom.id}/edit`" />
                        <TableDeleteIconButton @click="destroyClassroom(classroom.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="classrooms.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                找不到符合條件的班級資料。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="w-10 py-2 text-left">色</th>
                        <th class="py-2 text-left">班級名稱</th>
                        <th class="py-2 text-left">年級</th>
                        <th class="py-2 text-left">課程</th>
                        <th class="py-2 text-left">老師</th>
                        <th class="py-2 text-left">時間</th>
                        <th class="py-2 text-left">狀態</th>
                        <th class="w-[160px] py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="classroom in classrooms.data" :key="classroom.id" class="border-b">
                        <td class="py-2 align-middle">
                            <span
                                class="inline-block h-3 w-3 rounded-full border border-border shadow-sm"
                                :title="classroom.color ?? '預設'"
                                :style="classroomSwatchStyle(classroom.color)"
                            />
                        </td>
                        <td class="py-2">{{ classroom.name }}</td>
                        <td class="py-2">{{ classroom.grade_level?.name ?? '—' }}</td>
                        <td class="py-2 align-top">
                            <template v-if="coursesForClassroom(classroom).length">
                                <span
                                    v-for="(course, ci) in coursesForClassroom(classroom)"
                                    :key="`${classroom.id}-c-${course.id}-${ci}`"
                                    class="block leading-snug"
                                    :class="ci > 0 ? 'mt-1' : ''"
                                >
                                    {{ courseCell(course).title }}
                                    <span
                                        v-for="(line, li) in courseCell(course).tierLines"
                                        :key="`${classroom.id}-tier-${course.id}-${li}`"
                                        class="mt-0.5 block text-xs text-muted-foreground"
                                    >
                                        {{ line }}
                                    </span>
                                </span>
                            </template>
                            <span v-else>—</span>
                        </td>
                        <td class="py-2">{{ classroom.teacher?.name ?? '-' }}</td>
                        <td class="py-2 align-top">
                            <div v-if="formatTimeRanges(classroom).length" class="space-y-0.5">
                                <div
                                    v-for="(line, idx) in formatTimeRanges(classroom)"
                                    :key="`${classroom.id}-${idx}`"
                                    class="tabular-nums whitespace-nowrap"
                                >
                                    {{ line }}
                                </div>
                            </div>
                            <span v-else>-</span>
                        </td>
                        <td class="py-2">{{ statusLabel(classroom.status) }}</td>
                        <td class="py-2">
                            <div class="flex items-center gap-0.5">
                                <TableIconLink
                                    :href="`/classrooms/${classroom.id}/students`"
                                    label="學生名單"
                                    :icon="Users"
                                />
                                <TableIconLink
                                    :href="`/classrooms/${classroom.id}/attendance-rate`"
                                    label="出席率"
                                    :icon="ChartColumnBig"
                                />
                                <TableEditIconLink :href="`/classrooms/${classroom.id}/edit`" />
                                <TableDeleteIconButton @click="destroyClassroom(classroom.id)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="classrooms.links" />
    </div>
</template>
