<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import StudentStatusFilterSelect from '@/components/StudentStatusFilterSelect.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import { studentStatusLabel, studentStatusPillClass } from '@/lib/studentStatus';

type Student = {
    id: number;
    student_code: string | null;
    name: string;
    grade_name: string | null;
    phone: string | null;
    parent_name: string | null;
    parent_phone: string | null;
    status: 'active' | 'paused';
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    students: Paginated<Student>;
    filters: {
        name: string;
        status: string;
    };
}>();

const page = usePage();
const canCreateEdit =
    page.props.auth.user.role === 'super_admin' ||
    page.props.auth.user.role === 'admin' ||
    page.props.auth.user.role === 'teacher';
const canDelete = page.props.auth.user.role === 'super_admin' || page.props.auth.user.role === 'admin';

const filterForm = useForm({
    name: props.filters.name ?? '',
    status: props.filters.status ?? '',
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: '學生管理', href: '/students' }],
    },
});

const destroyStudent = (id: number) => {
    if (!window.confirm('確定要刪除此學生嗎？')) return;
    router.delete(`/students/${id}`);
};

const applyFilters = () => {
    filterForm.get('/students', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.name = '';
    filterForm.status = '';
    applyFilters();
};
</script>

<template>
    <Head title="學生管理" />
    <div class="page-shell">
        <PageHeader title="學生管理">
            <template v-if="canCreateEdit" #actions>
                <Button as-child><Link href="/students/create">新增學生</Link></Button>
            </template>
        </PageHeader>

        <form
            class="list-filter-panel rounded-xl border border-sidebar-border/70 bg-card p-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_auto_auto] lg:items-end">
                <div class="grid gap-1">
                    <label for="name" class="text-sm font-medium">姓名</label>
                    <input
                        id="name"
                        v-model="filterForm.name"
                        type="text"
                        class="h-10 rounded-md border px-3"
                        placeholder="輸入學生姓名"
                        @change="applyFilters"
                    />
                </div>
                <div class="grid gap-1">
                    <label for="status" class="text-sm font-medium">狀態</label>
                    <StudentStatusFilterSelect
                        id="status"
                        v-model="filterForm.status"
                        :disabled="filterForm.processing"
                        @update:model-value="applyFilters"
                    />
                </div>
                <Button type="submit" class="h-10 w-full" :disabled="filterForm.processing">查詢</Button>
                <Button type="button" variant="outline" class="h-10 w-full" :disabled="filterForm.processing" @click="resetFilters">
                    清除
                </Button>
            </div>
        </form>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="student in students.data"
                :key="student.id"
                :title="student.name"
                :subtitle="student.phone ?? undefined"
            >
                <template #badge>
                    <span :class="studentStatusPillClass(student.status)">
                        {{ studentStatusLabel(student.status) }}
                    </span>
                </template>
                <MobileRecordField label="學號">{{ student.student_code ?? '—' }}</MobileRecordField>
                <MobileRecordField label="年級">{{ student.grade_name ?? '—' }}</MobileRecordField>
                <MobileRecordField label="家長">
                    {{ student.parent_name ?? '—' }}
                    <span v-if="student.parent_phone" class="block text-xs font-normal text-muted-foreground">
                        {{ student.parent_phone }}
                    </span>
                </MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink v-if="canCreateEdit" :href="`/students/${student.id}/edit`" />
                        <TableDeleteIconButton v-if="canDelete" @click="destroyStudent(student.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="students.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                找不到符合條件的學生資料。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left font-semibold">學號</th>
                        <th class="py-2 text-left font-semibold">姓名</th>
                        <th class="py-2 text-left font-semibold">年級</th>
                        <th class="py-2 text-left font-semibold">學生電話</th>
                        <th class="py-2 text-left font-semibold">家長</th>
                        <th class="py-2 text-left font-semibold">家長電話</th>
                        <th class="py-2 text-left font-semibold">狀態</th>
                        <th class="py-2 text-left font-semibold">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in students.data" :key="student.id" class="border-b">
                        <td class="py-2.5 font-mono text-xs">{{ student.student_code ?? '—' }}</td>
                        <td class="py-2.5">{{ student.name }}</td>
                        <td class="py-2.5">{{ student.grade_name ?? '—' }}</td>
                        <td class="py-2.5">{{ student.phone ?? '-' }}</td>
                        <td class="py-2.5">{{ student.parent_name ?? '-' }}</td>
                        <td class="py-2.5">{{ student.parent_phone ?? '-' }}</td>
                        <td class="py-2.5">
                            <span :class="studentStatusPillClass(student.status)">
                                {{ studentStatusLabel(student.status) }}
                            </span>
                        </td>
                        <td class="py-2.5">
                            <div class="flex flex-wrap gap-2">
                                <TableEditIconLink v-if="canCreateEdit" :href="`/students/${student.id}/edit`" />
                                <TableDeleteIconButton v-if="canDelete" @click="destroyStudent(student.id)" />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="students.data.length === 0">
                        <td colspan="8" class="py-8 text-center text-muted-foreground">找不到符合條件的學生資料。</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="students.links" />
    </div>
</template>
