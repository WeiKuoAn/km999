<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
        level?: string | null;
        course?: Course;
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
            description="依課程管理「上課時段」（年級＋星期＋時間）顯示本週課表（僅顯示啟用中的課程）。"
        />

        <WeeklyScheduleCalendar
            :schedule-classrooms="scheduleClassrooms"
            :show-teacher-in-block="false"
            :can-filter-by-teacher="false"
            empty-message="尚無啟用中的課程時段，請到「設定管理 → 課程管理」新增或啟用課程並填寫上課時段。"
        />
    </div>
</template>
