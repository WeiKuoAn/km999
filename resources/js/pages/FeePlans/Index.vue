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
    course_names: string[];
    bundles: Array<{ id: number; name: string; price: number }>;
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
        breadcrumbs: [{ title: '收費設定', href: '/fee-plans' }],
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
    if (!window.confirm('確定要刪除此收費設定嗎？')) {
        return;
    }

    router.delete(`/fee-plans/${id}`);
};
</script>

<template>
    <Head title="收費設定" />
    <div class="page-shell">
        <PageHeader
            title="收費設定"
            description="每門課的單科價，以及同時報多科時的雙科／多科優惠（季繳、年繳適用）。"
        >
            <template v-if="canManage" #actions>
                <Button as-child>
                    <Link href="/fee-plans/create">
                        <Plus class="size-4" />
                        新增收費設定
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
                <MobileRecordField label="適用課目">
                    {{
                        plan.course_names.length > 0
                            ? plan.course_names.join('、')
                            : '尚未設定'
                    }}
                </MobileRecordField>
                <MobileRecordField label="原價">{{
                    plan.list_label
                }}</MobileRecordField>
                <MobileRecordField label="優惠價">{{
                    plan.quarter_label
                }}</MobileRecordField>
                <MobileRecordField
                    v-if="plan.bundles.length"
                    label="雙科／多科優惠"
                >
                    <span
                        v-for="(bundle, i) in plan.bundles"
                        :key="bundle.id"
                        >{{ i > 0 ? '、' : '' }}{{ bundle.name }}
                        {{ bundle.price.toLocaleString() }}／月</span
                    >
                </MobileRecordField>
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
                尚無收費設定，請新增或執行 seed。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">年級</th>
                        <th class="py-2 text-left">科目組</th>
                        <th class="py-2 text-left">適用課目</th>
                        <th class="py-2 text-left">原價</th>
                        <th class="py-2 text-left">優惠價</th>
                        <th class="py-2 text-left">雙科／多科優惠</th>
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
                        <td class="py-2">{{ plan.list_label }}</td>
                        <td class="py-2">{{ plan.quarter_label }}</td>
                        <td class="py-2">
                            <div
                                v-for="bundle in plan.bundles"
                                :key="bundle.id"
                                class="text-emerald-700"
                            >
                                {{ bundle.name }}
                                {{ bundle.price.toLocaleString() }}／月
                            </div>
                            <span
                                v-if="plan.bundles.length === 0"
                                class="text-muted-foreground"
                                >—</span
                            >
                        </td>
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
