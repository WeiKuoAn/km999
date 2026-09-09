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

type Discount = {
    id: number;
    name: string;
    type: 'amount' | 'percent';
    value: number;
    is_active: boolean;
    sort_order: number;
    starts_on: string | null;
    ends_on: string | null;
    notes: string | null;
    label: string;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{ discounts: Paginated<Discount> }>();

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
const deleteError = computed(
    () => (page.props.errors as Record<string, string | undefined>)?.delete,
);

const typeLabel = (type: string) =>
    type === 'percent' ? '打折％' : '固定折讓';

const valueLabel = (row: Discount) =>
    row.type === 'percent'
        ? `${row.value}%`
        : row.value.toLocaleString();

defineOptions({
    layout: {
        breadcrumbs: [{ title: '優惠管理', href: '/fee-discounts' }],
    },
});

const destroyDiscount = (id: number) => {
    if (!window.confirm('確定要刪除此優惠嗎？若已有收款紀錄將改為停用。')) {
        return;
    }
    router.delete(`/fee-discounts/${id}`);
};
</script>

<template>
    <Head title="優惠管理" />
    <div class="page-shell">
        <PageHeader
            title="優惠管理"
            description="設定固定折讓金額或打折％數，新增收款時可選用。"
        >
            <template #actions>
                <Button as-child>
                    <Link href="/fee-discounts/create">新增優惠</Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>
        <InputError v-if="deleteError" :message="deleteError" />

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="row in discounts.data"
                :key="row.id"
                :title="row.name"
                :subtitle="row.label"
            >
                <MobileRecordField label="類型">{{
                    typeLabel(row.type)
                }}</MobileRecordField>
                <MobileRecordField label="數值">{{
                    valueLabel(row)
                }}</MobileRecordField>
                <MobileRecordField label="狀態">{{
                    row.is_active ? '啟用' : '停用'
                }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink
                            :href="`/fee-discounts/${row.id}/edit`"
                        />
                        <TableDeleteIconButton
                            @click="destroyDiscount(row.id)"
                        />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="discounts.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無優惠，請按右上角新增。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">名稱</th>
                        <th class="py-2 text-left">類型</th>
                        <th class="py-2 text-right">數值</th>
                        <th class="py-2 text-left">有效期間</th>
                        <th class="py-2 text-left">狀態</th>
                        <th class="py-2 text-left">排序</th>
                        <th class="py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in discounts.data"
                        :key="row.id"
                        class="border-b"
                    >
                        <td class="py-2.5">{{ row.name }}</td>
                        <td class="py-2.5">{{ typeLabel(row.type) }}</td>
                        <td class="py-2.5 text-right tabular-nums">
                            {{ valueLabel(row) }}
                        </td>
                        <td class="py-2.5 text-muted-foreground">
                            <template v-if="row.starts_on || row.ends_on">
                                {{ row.starts_on ?? '—' }} ～
                                {{ row.ends_on ?? '—' }}
                            </template>
                            <template v-else>不限</template>
                        </td>
                        <td class="py-2.5">
                            {{ row.is_active ? '啟用' : '停用' }}
                        </td>
                        <td class="py-2.5">{{ row.sort_order }}</td>
                        <td class="py-2.5">
                            <div class="flex items-center gap-1">
                                <TableEditIconLink
                                    :href="`/fee-discounts/${row.id}/edit`"
                                />
                                <TableDeleteIconButton
                                    @click="destroyDiscount(row.id)"
                                />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="discounts.data.length === 0">
                        <td
                            colspan="7"
                            class="py-10 text-center text-muted-foreground"
                        >
                            尚無優惠。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="discounts.links" />
    </div>
</template>
