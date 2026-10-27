<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type PeriodLine = {
    label: string;
    monthly: string;
    months: number;
};

type TierLine = {
    tier: string;
    monthly: string;
};

type Plan = {
    id: number;
    grade_name: string | null;
    group_name: string;
    course_names: string[];
    period_lines: PeriodLine[];
    tier_lines: TierLine[];
    bundles: Array<{
        id: number;
        name: string;
        price: number;
        summary: string;
        summary_lines?: string[];
    }>;
    quarter_label: string;
    material_label: string;
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

const duplicatePlan = (id: number) => {
    if (!window.confirm('要複製這筆收費設定嗎？會帶入期別與金額，並導向編輯頁。')) {
        return;
    }

    router.post(`/fee-plans/${id}/duplicate`);
};

const hasTierInfo = (plan: Plan) =>
    plan.tier_lines.length > 0 || plan.bundles.length > 0;
</script>

<template>
    <Head title="收費設定" />
    <div class="page-shell">
        <PageHeader
            title="收費設定"
            description="各期填每月單價；一期應收＝月價×月數。雙／多科可另設優惠月價。"
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
                class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                :class="
                    activeGrade === g
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
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
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            plan.is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ plan.is_active ? '啟用' : '停用' }}
                    </span>
                </template>
                <MobileRecordField label="適用課目">
                    <ul
                        v-if="plan.course_names.length > 0"
                        class="space-y-0.5"
                    >
                        <li
                            v-for="name in plan.course_names"
                            :key="name"
                        >
                            {{ name }}
                        </li>
                    </ul>
                    <span v-else>尚未設定</span>
                </MobileRecordField>
                <MobileRecordField label="期別金額">
                    <ul
                        v-if="plan.period_lines?.length"
                        class="space-y-1 text-sm"
                    >
                        <li
                            v-for="(line, i) in plan.period_lines"
                            :key="i"
                            class="flex flex-wrap items-baseline gap-x-2"
                        >
                            <span class="text-muted-foreground">{{
                                line.label
                            }}</span>
                            <span class="tabular-nums font-medium"
                                >{{ line.monthly }}／月</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >×{{ line.months }}</span
                            >
                        </li>
                    </ul>
                    <span v-else>{{ plan.quarter_label }}</span>
                </MobileRecordField>
                <MobileRecordField v-if="hasTierInfo(plan)" label="雙科／多科">
                    <div class="space-y-2 text-sm">
                        <div
                            v-for="(line, i) in plan.tier_lines"
                            :key="`t-${i}`"
                            class="text-emerald-700"
                        >
                            {{ line.tier }} {{ line.monthly }}／月
                        </div>
                        <div
                            v-for="bundle in plan.bundles"
                            :key="bundle.id"
                            class="space-y-0.5 text-emerald-700"
                        >
                            <div class="font-medium">{{ bundle.name }}</div>
                            <div
                                v-for="(part, pi) in bundle.summary_lines ??
                                bundle.summary.split('、')"
                                :key="pi"
                            >
                                {{ part }}
                            </div>
                        </div>
                    </div>
                </MobileRecordField>
                <MobileRecordField label="教材費">{{
                    plan.material_label
                }}</MobileRecordField>
                <template v-if="canManage" #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink
                            :href="`/fee-plans/${plan.id}/edit`"
                        />
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground hover:bg-muted hover:text-foreground"
                                    @click="duplicatePlan(plan.id)"
                                >
                                    <Copy class="size-4" />
                                    <span class="sr-only">複製</span>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>複製</TooltipContent>
                        </Tooltip>
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
                        <th class="py-2.5 text-left">年級／科目組</th>
                        <th class="py-2.5 text-left">適用課目</th>
                        <th class="min-w-[11rem] py-2.5 text-left">期別金額</th>
                        <th class="py-2.5 text-left">雙科／多科</th>
                        <th class="py-2.5 text-left">教材費</th>
                        <th class="w-[4.5rem] py-2.5 text-left">狀態</th>
                        <th class="w-[7.5rem] py-2.5 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="plan in plans.data"
                        :key="plan.id"
                        class="border-b align-top"
                    >
                        <td class="py-3">
                            <div class="font-medium">{{ plan.group_name }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">
                                {{ plan.grade_name ?? '全年級' }}
                            </div>
                        </td>
                        <td class="max-w-[10rem] py-3 text-muted-foreground">
                            <ul
                                v-if="plan.course_names.length > 0"
                                class="space-y-0.5 leading-snug"
                            >
                                <li
                                    v-for="name in plan.course_names"
                                    :key="name"
                                >
                                    {{ name }}
                                </li>
                            </ul>
                            <span v-else>尚未設定</span>
                        </td>
                        <td class="py-3">
                            <ul
                                v-if="plan.period_lines?.length"
                                class="space-y-1"
                            >
                                <li
                                    v-for="(line, i) in plan.period_lines"
                                    :key="i"
                                    class="flex flex-wrap items-baseline gap-x-1.5 leading-snug"
                                >
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                        >{{ line.label }}</span
                                    >
                                    <span class="tabular-nums font-medium"
                                        >{{ line.monthly }}</span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >／月 ×{{ line.months }}</span
                                    >
                                </li>
                            </ul>
                            <span v-else class="text-muted-foreground">{{
                                plan.quarter_label
                            }}</span>
                        </td>
                        <td class="min-w-[8rem] py-3">
                            <template v-if="hasTierInfo(plan)">
                                <div class="space-y-2">
                                    <div
                                        v-for="(line, i) in plan.tier_lines"
                                        :key="`t-${i}`"
                                        class="leading-snug text-emerald-700"
                                    >
                                        <span class="text-xs">{{
                                            line.tier
                                        }}</span>
                                        <span
                                            class="ml-1 tabular-nums font-medium"
                                            >{{ line.monthly }}</span
                                        >
                                        <span class="text-xs">／月</span>
                                    </div>
                                    <div
                                        v-for="bundle in plan.bundles"
                                        :key="bundle.id"
                                        class="space-y-0.5 leading-snug text-emerald-700"
                                    >
                                        <div class="text-xs font-medium">
                                            {{ bundle.name }}
                                        </div>
                                        <div
                                            v-for="(part, pi) in bundle.summary_lines ??
                                            bundle.summary.split('、')"
                                            :key="pi"
                                            class="pl-0.5"
                                        >
                                            {{ part }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="py-3 whitespace-nowrap text-muted-foreground">
                            {{ plan.material_label }}
                        </td>
                        <td class="py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    plan.is_active
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ plan.is_active ? '啟用' : '停用' }}
                            </span>
                        </td>
                        <td class="py-3">
                            <div
                                v-if="canManage"
                                class="flex items-center gap-0.5"
                            >
                                <TableEditIconLink
                                    :href="`/fee-plans/${plan.id}/edit`"
                                />
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-muted-foreground hover:bg-muted hover:text-foreground"
                                            @click="duplicatePlan(plan.id)"
                                        >
                                            <Copy class="size-4" />
                                            <span class="sr-only">複製</span>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>複製</TooltipContent>
                                </Tooltip>
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
