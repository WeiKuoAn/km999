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

type Year = {
    id: number;
    year_code: string;
    name: string | null;
    is_current: boolean;
    students_count: number;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{ years: Paginated<Year> }>();

const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string | undefined>)?.delete);

defineOptions({
    layout: {
        breadcrumbs: [{ title: '學年設定', href: '/academic-years' }],
    },
});

const destroyYear = (id: number) => {
    if (!window.confirm('確定要刪除此學年嗎？')) return;
    router.delete(`/academic-years/${id}`);
};
</script>

<template>
    <Head title="學年設定" />
    <div class="page-shell">
        <PageHeader title="學年設定" description="學號前段＝學年度（例 115）。可自行新增年度，並指定目前學年。">
            <template #actions>
                <Button as-child><Link href="/academic-years/create">新增學年</Link></Button>
            </template>
        </PageHeader>

        <InputError v-if="deleteError" :message="deleteError" />

        <div class="mobile-card-list">
            <MobileRecordCard v-for="row in years.data" :key="row.id" :title="row.name || `${row.year_code}學年度`">
                <MobileRecordField label="年度碼">{{ row.year_code }}</MobileRecordField>
                <MobileRecordField label="目前學年">{{ row.is_current ? '是' : '否' }}</MobileRecordField>
                <MobileRecordField label="學生數">{{ row.students_count }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink :href="`/academic-years/${row.id}/edit`" />
                        <TableDeleteIconButton @click="destroyYear(row.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="years.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無學年，請先新增。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">年度碼</th>
                        <th class="py-2 text-left">名稱</th>
                        <th class="py-2 text-left">目前學年</th>
                        <th class="py-2 text-left">學生數</th>
                        <th class="py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in years.data" :key="row.id" class="border-b">
                        <td class="py-2 font-mono">{{ row.year_code }}</td>
                        <td class="py-2">{{ row.name || `${row.year_code}學年度` }}</td>
                        <td class="py-2">
                            <span
                                v-if="row.is_current"
                                class="rounded bg-primary/15 px-2 py-0.5 text-xs text-primary"
                            >目前</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="py-2">{{ row.students_count }}</td>
                        <td class="py-2">
                            <div class="flex items-center gap-0.5">
                                <TableEditIconLink :href="`/academic-years/${row.id}/edit`" />
                                <TableDeleteIconButton @click="destroyYear(row.id)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="years.links" />
    </div>
</template>
