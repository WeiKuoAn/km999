<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import { Button } from '@/components/ui/button';

type Plan = {
    id: number;
    grade_name: string | null;
    group_name: string;
    pricing_group_label: string;
    course_names: string[];
    list_label: string;
    quarter_label: string;
    material_label: string;
    unit_label: string;
    is_active: boolean;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    plans: Paginated<Plan>;
    filters: { grade: string };
    gradeOptions: string[];
}>();

const page = usePage();
const canManage =
    page.props.auth.user.role === 'super_admin' ||
    page.props.auth.user.role === 'admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: '收費標準', href: '/fee-plans' }],
    },
});

const activeGrade = computed(() => props.filters.grade || '全部');

const filterGrade = (grade: string) => {
    router.get('/fee-plans', grade === '全部' ? {} : { grade }, {
        preserveState: true,
        replace: true,
    });
};

const destroyPlan = (id: number) => {
    if (!window.confirm('確定要刪除此收費標準嗎？')) {
        return;
    }

    router.delete(`/fee-plans/${id}`);
};
</script>

<template>
    <Head title="收費標準" />
    <div class="page-shell">
        <PageHeader
            title="收費標準"
            description="依學年、年級與適用課目維護定價、季繳及教材費。"
        >
            <template v-if="canManage" #actions>
                <Button as-child>
                    <Link href="/fee-plans/create">
                        <Plus class="size-4" />
                        新增標準
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="g in gradeOptions"
                :key="g"
                type="button"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="
                    activeGrade === g
                        ? 'border-primary bg-primary text-primary-foreground'
                        : ''
                "
                @click="filterGrade(g)"
            >
                {{ g }}
            </button>
        </div>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="plan in plans.data"
                :key="plan.id"
                :title="plan.group_name"
                :subtitle="plan.grade_name ?? undefined"
            >
                <template #badge>
                    <span class="text-xs font-medium text-muted-foreground">
                        {{ plan.is_active ? '啟用' : '停用' }}
                    </span>
                </template>
                <MobileRecordField label="收費組別">{{
                    plan.pricing_group_label
                }}</MobileRecordField>
                <MobileRecordField label="適用課目">
                    {{
                        plan.course_names.length > 0
                            ? plan.course_names.join('、')
                            : '尚未設定'
                    }}
                </MobileRecordField>
                <MobileRecordField label="定價">{{
                    plan.list_label
                }}</MobileRecordField>
                <MobileRecordField label="季繳">{{
                    plan.quarter_label
                }}</MobileRecordField>
                <MobileRecordField label="教材費">{{
                    plan.material_label
                }}</MobileRecordField>
                <template v-if="canManage" #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink
                            :href="`/fee-plans/${plan.id}/edit`"
                        />
                        <TableDeleteIconButton @click="destroyPlan(plan.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="plans.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無收費標準，請新增或執行 seed。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">年級</th>
                        <th class="py-2 text-left">科目組</th>
                        <th class="py-2 text-left">適用課目</th>
                        <th class="py-2 text-left">收費組別</th>
                        <th class="py-2 text-left">定價</th>
                        <th class="py-2 text-left">季繳優惠</th>
                        <th class="py-2 text-left">教材費</th>
                        <th class="py-2 text-left">單位</th>
                        <th class="py-2 text-left">狀態</th>
                        <th class="w-[88px] py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="plan in plans.data"
                        :key="plan.id"
                        class="border-b"
                    >
                        <td class="py-2">{{ plan.grade_name ?? '—' }}</td>
                        <td class="py-2 font-medium">{{ plan.group_name }}</td>
                        <td class="max-w-56 py-2 text-muted-foreground">
                            {{
                                plan.course_names.length > 0
                                    ? plan.course_names.join('、')
                                    : '尚未設定'
                            }}
                        </td>
                        <td class="py-2 text-muted-foreground">
                            {{ plan.pricing_group_label }}
                        </td>
                        <td class="py-2">{{ plan.list_label }}</td>
                        <td class="py-2">{{ plan.quarter_label }}</td>
                        <td class="py-2">{{ plan.material_label }}</td>
                        <td class="py-2">{{ plan.unit_label }}</td>
                        <td class="py-2">
                            {{ plan.is_active ? '啟用' : '停用' }}
                        </td>
                        <td class="py-2">
                            <div
                                v-if="canManage"
                                class="flex items-center gap-0.5"
                            >
                                <TableEditIconLink
                                    :href="`/fee-plans/${plan.id}/edit`"
                                />
                                <TableDeleteIconButton
                                    @click="destroyPlan(plan.id)"
                                />
                            </div>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="plans.links" />
    </div>
</template>
