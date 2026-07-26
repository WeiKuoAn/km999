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

type Category = {
    id: number;
    name: string;
    sort_order: number;
    courses_count: number;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{ categories: Paginated<Category> }>();

const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string | undefined>)?.delete);

defineOptions({
    layout: {
        breadcrumbs: [{ title: '課程類別管理', href: '/course-categories' }],
    },
});

const destroyCategory = (id: number) => {
    if (!window.confirm('確定要刪除此課程類別嗎？')) return;
    router.delete(`/course-categories/${id}`);
};
</script>

<template>
    <Head title="課程類別管理" />
    <div class="page-shell">
        <PageHeader title="課程類別管理">
            <template #actions>
                <Button as-child><Link href="/course-categories/create">新增類別</Link></Button>
            </template>
        </PageHeader>

        <InputError v-if="deleteError" :message="deleteError" />

        <div class="mobile-card-list">
            <MobileRecordCard v-for="row in categories.data" :key="row.id" :title="row.name">
                <MobileRecordField label="排序">{{ row.sort_order }}</MobileRecordField>
                <MobileRecordField label="課程數">{{ row.courses_count }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink :href="`/course-categories/${row.id}/edit`" />
                        <TableDeleteIconButton @click="destroyCategory(row.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="categories.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無課程類別。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">名稱</th>
                        <th class="py-2 text-left">排序</th>
                        <th class="py-2 text-left">課程數</th>
                        <th class="py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in categories.data" :key="row.id" class="border-b">
                        <td class="py-2">{{ row.name }}</td>
                        <td class="py-2">{{ row.sort_order }}</td>
                        <td class="py-2">{{ row.courses_count }}</td>
                        <td class="py-2">
                            <div class="flex items-center gap-0.5">
                                <TableEditIconLink :href="`/course-categories/${row.id}/edit`" />
                                <TableDeleteIconButton @click="destroyCategory(row.id)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="categories.links" />
    </div>
</template>
