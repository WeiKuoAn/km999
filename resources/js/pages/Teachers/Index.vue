<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ClassroomTagList from '@/components/ClassroomTagList.vue';
import CourseTagList from '@/components/CourseTagList.vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';

type Teacher = {
    id: number;
    name: string;
    phone: string | null;
    status: 'active' | 'paused';
    user: { id: number; name: string; email: string } | null;
    courses: string[];
    classrooms: Array<{ id: number; name: string; color: string | null }>;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{ teachers: Paginated<Teacher> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: '老師管理', href: '/teachers' }],
    },
});

const statusLabel = (status: Teacher['status']) => (status === 'active' ? '在職' : '停用');
const accountLabel = (teacher: Teacher) =>
    teacher.user ? `${teacher.user.name} (${teacher.user.email})` : '—';

const destroyTeacher = (id: number) => {
    if (!window.confirm('確定要刪除此老師嗎？')) return;
    router.delete(`/teachers/${id}`);
};
</script>

<template>
    <Head title="老師管理" />
    <div class="page-shell">
        <PageHeader title="老師管理">
            <template #actions>
                <Button as-child><Link href="/teachers/create">新增老師</Link></Button>
            </template>
        </PageHeader>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="teacher in teachers.data"
                :key="teacher.id"
                :title="teacher.name"
                :subtitle="teacher.phone ?? undefined"
            >
                <template #badge>
                    <span class="text-xs font-medium text-muted-foreground">{{ statusLabel(teacher.status) }}</span>
                </template>
                <MobileRecordField label="帳號">{{ accountLabel(teacher) }}</MobileRecordField>
                <MobileRecordField label="課程">
                    <CourseTagList :items="teacher.courses" />
                </MobileRecordField>
                <MobileRecordField label="班級">
                    <ClassroomTagList :items="teacher.classrooms" compact />
                </MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <Button variant="outline" size="sm" class="w-full sm:w-auto" as-child>
                            <Link :href="`/teachers/${teacher.id}/courses-schedule`">課程</Link>
                        </Button>
                        <TableEditIconLink :href="`/teachers/${teacher.id}/edit`" />
                        <TableDeleteIconButton @click="destroyTeacher(teacher.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="teachers.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無老師資料。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left whitespace-nowrap">姓名</th>
                        <th class="py-2 text-left whitespace-nowrap">電話</th>
                        <th class="py-2 text-left whitespace-nowrap">綁定帳號</th>
                        <th class="py-2 text-left">課程</th>
                        <th class="py-2 text-left min-w-[10rem]">班級</th>
                        <th class="py-2 text-left whitespace-nowrap">狀態</th>
                        <th class="py-2 text-left whitespace-nowrap">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="teacher in teachers.data" :key="teacher.id" class="border-b align-top">
                        <td class="py-2.5 whitespace-nowrap font-medium">{{ teacher.name }}</td>
                        <td class="py-2.5 whitespace-nowrap text-muted-foreground">{{ teacher.phone ?? '—' }}</td>
                        <td class="py-2.5 max-w-[12rem] break-words text-muted-foreground">{{ accountLabel(teacher) }}</td>
                        <td class="py-2.5">
                            <CourseTagList :items="teacher.courses" />
                        </td>
                        <td class="py-2.5">
                            <ClassroomTagList :items="teacher.classrooms" />
                        </td>
                        <td class="py-2.5 whitespace-nowrap">{{ statusLabel(teacher.status) }}</td>
                        <td class="py-2.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/teachers/${teacher.id}/courses-schedule`">課程</Link>
                                </Button>
                                <TableEditIconLink :href="`/teachers/${teacher.id}/edit`" />
                                <TableDeleteIconButton @click="destroyTeacher(teacher.id)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="teachers.links" />
    </div>
</template>
