<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';

type Grade = {
    id: number;
    name: string;
    code: number;
    sort_order: number;
    is_active: boolean;
    students_count: number;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{ grades: Paginated<Grade> }>();

const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string | undefined>)?.delete);

defineOptions({
    layout: {
        breadcrumbs: [{ title: '年級編號', href: '/grade-levels' }],
    },
});

const destroyGrade = (id: number) => {
    if (!window.confirm('確定要刪除此年級嗎？')) return;
    router.delete(`/grade-levels/${id}`);
};

const pad = (n: number) => String(n).padStart(2, '0');
</script>

<template>
    <Head title="年級編號" />
    <div class="page-shell">
        <PageHeader
            title="年級編號"
            description="學號中段＝年級兩碼。例：國一＝7 → 07，學號如 11507001。"
        >
            <template #actions>
                <Button as-child><Link href="/grade-levels/create">新增年級</Link></Button>
            </template>
        </PageHeader>

        <InputError v-if="deleteError" :message="deleteError" />

        <div class="mobile-card-list">
            <MobileRecordCard v-for="row in grades.data" :key="row.id" :title="row.name">
                <MobileRecordField label="編號">{{ row.code }}（{{ pad(row.code) }}）</MobileRecordField>
                <MobileRecordField label="啟用">{{ row.is_active ? '是' : '否' }}</MobileRecordField>
                <MobileRecordField label="學生數">{{ row.students_count }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink :href="`/grade-levels/${row.id}/edit`" />
                        <TableDeleteIconButton @click="destroyGrade(row.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="grades.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無年級，請先新增（建議：國一=7、國二=8、國三=9）。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">年級</th>
                        <th class="py-2 text-left">編號</th>
                        <th class="py-2 text-left">學號兩碼</th>
                        <th class="py-2 text-left">啟用</th>
                        <th class="py-2 text-left">學生數</th>
                        <th class="py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in grades.data" :key="row.id" class="border-b">
                        <td class="py-2 font-medium">{{ row.name }}</td>
                        <td class="py-2">{{ row.code }}</td>
                        <td class="py-2 font-mono">{{ pad(row.code) }}</td>
                        <td class="py-2">{{ row.is_active ? '是' : '否' }}</td>
                        <td class="py-2">{{ row.students_count }}</td>
                        <td class="py-2">
                            <div class="flex items-center gap-0.5">
                                <TableEditIconLink :href="`/grade-levels/${row.id}/edit`" />
                                <TableDeleteIconButton @click="destroyGrade(row.id)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="grades.links" />
    </div>
</template>
