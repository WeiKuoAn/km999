<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import { Button } from '@/components/ui/button';

import { classroomSwatchStyle } from '@/lib/classroomColor';
import { pricingGroupLabel } from '@/lib/pricingGroup';
import { scheduleLabelLines } from '@/lib/scheduleLabelLines';
import {
    formatCourseSchedules
    
} from '@/lib/weekdayDates';
import type {CourseScheduleRow} from '@/lib/weekdayDates';

type CoursePrice = {
    id: number;
    level: string | null;
    duration_hours?: number;
    tuition: number;
};

type Course = {
    id: number;
    course_category: { id: number; name: string };
    name: string;
    color: string | null;
    status: 'active' | 'paused';
    pricing_group: string | null;
    weekdays: number[] | null;
    schedules: CourseScheduleRow[] | null;
    course_prices: CoursePrice[];
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{ courses: Paginated<Course> }>();

const page = usePage();
const canManage =
    page.props.auth.user.role === 'super_admin' ||
    page.props.auth.user.role === 'admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: '課程管理', href: '/courses' }],
    },
});

const levelLabel = (prices: CoursePrice[]) => {
    const levels = prices.map((p) => p.level).filter((l): l is string => !!l);

    if (levels.length === 0) {
        return '不分年級';
    }

    return levels.join('、');
};

const scheduleLabel = (course: Course) => {
    if (course.schedules?.length) {
        return formatCourseSchedules(course.schedules);
    }

    if (course.weekdays?.length) {
        return formatCourseSchedules(
            course.weekdays.map((d) => ({ weekday: d })),
        );
    }

    return '—';
};

const statusLabel = (status: Course['status']) =>
    status === 'active' ? '啟用' : '停用';

const destroyCourse = (id: number) => {
    if (!window.confirm('確定要刪除此課程嗎？')) {
return;
}

    router.delete(`/courses/${id}`);
};
</script>

<template>
    <Head title="課程管理" />
    <div class="page-shell">
        <PageHeader title="課程管理">
            <template v-if="canManage" #actions>
                <Button as-child>
                    <Link href="/courses/create">
                        <Plus class="size-4" />
                        新增課程
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="course in courses.data"
                :key="course.id"
                :title="course.name"
                :subtitle="course.course_category.name"
            >
                <template #badge>
                    <span
                        class="inline-flex items-center gap-2 text-xs font-medium text-muted-foreground"
                    >
                        <span
                            class="inline-block size-3 rounded-sm border border-black/10"
                            :style="classroomSwatchStyle(course.color)"
                        />
                        {{ statusLabel(course.status) }}
                    </span>
                </template>
                <MobileRecordField label="年級">{{
                    levelLabel(course.course_prices)
                }}</MobileRecordField>
                <MobileRecordField label="上課時段">
                    <div class="flex flex-col gap-1">
                        <span
                            v-for="(line, li) in scheduleLabelLines(
                                scheduleLabel(course),
                            )"
                            :key="li"
                            class="block leading-snug"
                        >
                            {{ line }}
                        </span>
                    </div>
                </MobileRecordField>
                <MobileRecordField label="收費組別">{{
                    pricingGroupLabel(course.pricing_group)
                }}</MobileRecordField>
                <template v-if="canManage" #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink
                            :href="`/courses/${course.id}/edit`"
                        />
                        <TableDeleteIconButton
                            @click="destroyCourse(course.id)"
                        />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="courses.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無課程資料。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">顏色</th>
                        <th class="py-2 text-left">類別</th>
                        <th class="py-2 text-left">名稱</th>
                        <th class="py-2 text-left">年級</th>
                        <th class="py-2 text-left">上課時段</th>
                        <th class="py-2 text-left">收費組別</th>
                        <th class="py-2 text-left">狀態</th>
                        <th class="w-[88px] py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="course in courses.data"
                        :key="course.id"
                        class="border-b"
                    >
                        <td class="py-2">
                            <span
                                class="inline-block size-4 rounded-sm border border-black/10"
                                :title="course.color ?? '預設'"
                                :style="classroomSwatchStyle(course.color)"
                            />
                        </td>
                        <td class="py-2">{{ course.course_category.name }}</td>
                        <td class="py-2">{{ course.name }}</td>
                        <td class="py-2">
                            {{ levelLabel(course.course_prices) }}
                        </td>
                        <td class="max-w-md py-2 text-muted-foreground">
                            <div class="flex flex-col gap-1 break-words">
                                <span
                                    v-for="(line, li) in scheduleLabelLines(
                                        scheduleLabel(course),
                                    )"
                                    :key="li"
                                    class="block leading-snug"
                                >
                                    {{ line }}
                                </span>
                            </div>
                        </td>
                        <td class="py-2">
                            {{ pricingGroupLabel(course.pricing_group) }}
                        </td>
                        <td class="py-2">{{ statusLabel(course.status) }}</td>
                        <td class="py-2">
                            <div
                                v-if="canManage"
                                class="flex items-center gap-0.5"
                            >
                                <TableEditIconLink
                                    :href="`/courses/${course.id}/edit`"
                                />
                                <TableDeleteIconButton
                                    @click="destroyCourse(course.id)"
                                />
                            </div>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="courses.links" />
    </div>
</template>
