<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import WeeklyScheduleCalendar from '@/components/WeeklyScheduleCalendar.vue';
import { scheduleLabelLines } from '@/lib/scheduleLabelLines';

type CourseRow = {
    course_category_name: string;
    course_name: string;
    classroom_count: number;
    classrooms_label: string;
    schedule_label: string;
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

defineProps<{
    teacher: { id: number; name: string };
    courseRows: CourseRow[];
    scheduleClassrooms: ScheduleClassroom[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '老師管理', href: '/teachers' },
            { title: '課程與課表', href: '#' },
        ],
    },
});

const emptyCalendarMessage =
    '此老師目前沒有「上課中」的班級，或班級尚未設定星期與上課時間；若有單次加課，請於加課選單建立。';
</script>

<template>
    <Head :title="`${teacher.name} · 課程與課表`" />

    <div class="page-shell flex h-full flex-1 flex-col gap-6 overflow-x-auto">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ teacher.name }} · 課程與課表</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                上方為依課程彙總的班級列表；下方週行事曆與首頁行事曆相同邏輯（含固定時段與單次加課）。
            </p>
        </div>

        <section class="space-y-2">
            <h2 class="text-sm font-medium text-foreground">課程一覽</h2>
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/30">
                            <th class="px-3 py-2 text-left font-medium">課程類別</th>
                            <th class="px-3 py-2 text-left font-medium">課程名稱</th>
                            <th class="px-3 py-2 text-left font-medium">上課時間</th>
                            <th class="px-3 py-2 text-right font-medium">班級數</th>
                            <th class="px-3 py-2 text-left font-medium">班級</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in courseRows" :key="idx" class="border-b last:border-0">
                            <td class="px-3 py-2 text-muted-foreground">{{ row.course_category_name }}</td>
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
                            <td class="px-3 py-2 text-right tabular-nums">{{ row.classroom_count }}</td>
                            <td class="max-w-md px-3 py-2 whitespace-normal break-words text-muted-foreground">
                                {{ row.classrooms_label }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="courseRows.length === 0" class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                此老師目前沒有「上課中」的班級，或班級尚未關聯課程。
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-sm font-medium text-foreground">週課表</h2>
            <WeeklyScheduleCalendar
                :schedule-classrooms="scheduleClassrooms"
                :show-teacher-in-block="false"
                :empty-message="emptyCalendarMessage"
            />
        </section>
    </div>
</template>
