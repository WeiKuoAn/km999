<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/layout/PageHeader.vue';
import WeeklyScheduleCalendar from '@/components/WeeklyScheduleCalendar.vue';

type CourseCategory = { name: string };
type Course = { name: string; course_category: CourseCategory | null } | null;
type Teacher = { id: number; name: string } | null;

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
    scheduleClassrooms: ScheduleClassroom[];
    teacherOptions?: Array<{ id: number; name: string }>;
    canFilterByTeacher?: boolean;
    filters: {
        teacher_id: string;
    };
}>();

const applyTeacherFilter = (teacherId: string) => {
    router.get(
        '/calendar',
        teacherId ? { teacher_id: teacherId } : {},
        { preserveState: true, replace: true },
    );
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '總覽', href: '/dashboard' },
            { title: '行事曆', href: '/calendar' },
        ],
    },
});
</script>

<template>
    <Head title="行事曆" />

    <div class="page-shell flex h-full flex-1 flex-col gap-4 overflow-x-hidden">
        <PageHeader
            title="行事曆"
            description="依班級「星期＋上課時段」顯示本週課表，並含「單次加課」指定日期（僅顯示狀態為上課的班級）。"
        />

        <WeeklyScheduleCalendar
            :schedule-classrooms="scheduleClassrooms"
            :show-teacher-in-block="!filters.teacher_id"
            :can-filter-by-teacher="canFilterByTeacher"
            :teacher-options="teacherOptions"
            :teacher-id="filters.teacher_id"
            @update:teacher-id="applyTeacherFilter"
        />
    </div>
</template>
