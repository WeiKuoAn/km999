<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, CalendarDays, Plus, Receipt, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import WeeklyScheduleCalendar from '@/components/WeeklyScheduleCalendar.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { scheduleLabelLines } from '@/lib/scheduleLabelLines';

type CourseRow = {
    course_id: number;
    course_category_name: string;
    course_name: string;
    schedule_label: string;
    can_delete: boolean;
};

type CourseCategory = { name: string };
type Course = { name: string; course_category: CourseCategory | null } | null;
type Teacher = { name: string } | null;

type ScheduleClassroom = {
    id: number;
    name: string;
    color: string | null;
    start_date: string | null;
    end_date: string | null;
    date_range_unrestricted?: boolean;
    teaching_periods?: Array<{ start_date: string | null; end_date: string | null }>;
    schedules: Array<{
        weekday: number;
        start_time: string;
        end_time: string;
    }>;
    extra_sessions?: Array<{ date: string; start_time: string; end_time: string }> | null;
    course: Course;
    teacher: Teacher;
};

const props = defineProps<{
    student: { id: number; name: string; student_code?: string | null };
    courseRows: CourseRow[];
    scheduleClassrooms: ScheduleClassroom[];
    canManageCourses?: boolean;
}>();

const page = usePage();
const flashSuccess = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
const flashError = computed(
    () => (page.props.flash as { error?: string } | undefined)?.error,
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生管理', href: '/students' },
            { title: '課程與行事曆', href: '#' },
        ],
    },
});

const emptyCalendarMessage =
    '此學生目前沒有進行中的課程，或課程尚未設定上課時段。可先到「學生收款」為學生報名課程。';

const deleteCourse = (row: CourseRow) => {
    if (
        !window.confirm(
            `確定停修「${row.course_name}」？\n將取消未繳帳期；已繳紀錄會保留在繳費明細。`,
        )
    ) {
        return;
    }
    router.delete(`/students/${props.student.id}/courses/${row.course_id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${student.name} · 課程與行事曆`" />

    <div class="page-shell flex h-full flex-1 flex-col gap-6 overflow-x-auto">
        <PageHeader
            :title="`${student.name} · 課程與行事曆`"
            description="依收款帳期顯示進行中課程；停修只取消未繳，已繳紀錄仍留在繳費明細。下方為該生課程週行事曆。"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/payments`">
                        <Receipt class="size-4" />
                        繳費明細
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/student-payments/${student.id}`">
                        <BookOpen class="size-4" />
                        收款明細
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="`/student-payments/create?student_id=${student.id}`">
                        <Plus class="size-4" />
                        新增收款／報名
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/edit`">返回編輯</Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="flashSuccess"
            class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
            {{ flashError }}
        </div>

        <section class="space-y-2">
            <h2 class="text-sm font-medium text-foreground">進行中課程</h2>
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/30">
                            <th class="px-3 py-2 text-left font-medium">課程類別</th>
                            <th class="px-3 py-2 text-left font-medium">課程名稱</th>
                            <th class="px-3 py-2 text-left font-medium">上課時間</th>
                            <th v-if="canManageCourses" class="px-3 py-2 text-left font-medium">
                                操作
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in courseRows"
                            :key="row.course_id"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ row.course_category_name }}
                            </td>
                            <td class="px-3 py-2 font-medium">{{ row.course_name }}</td>
                            <td class="max-w-[min(28rem,45vw)] px-3 py-2 text-muted-foreground">
                                <div class="flex flex-col gap-1.5 break-words">
                                    <span
                                        v-for="(line, li) in scheduleLabelLines(row.schedule_label)"
                                        :key="li"
                                        class="block leading-snug"
                                    >
                                        {{ line }}
                                    </span>
                                </div>
                            </td>
                            <td v-if="canManageCourses" class="px-3 py-2">
                                <Button
                                    v-if="row.can_delete"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    @click="deleteCourse(row)"
                                >
                                    <Trash2 class="size-4" />
                                    停修
                                </Button>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="courseRows.length === 0"
                class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
            >
                此學生目前沒有進行中的課程。請按「新增收款／報名」報名科目。
            </p>
        </section>

        <section class="space-y-2">
            <div class="flex items-center gap-2">
                <CalendarDays class="size-4 text-muted-foreground" />
                <h2 class="text-sm font-medium text-foreground">週行事曆</h2>
            </div>
            <WeeklyScheduleCalendar
                :schedule-classrooms="scheduleClassrooms"
                :show-teacher-in-block="false"
                :empty-message="emptyCalendarMessage"
            />
        </section>
    </div>
</template>
