<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FeePlanFields from '@/components/fee-plans/FeePlanFields.vue';
import { Button } from '@/components/ui/button';
import {
    billingKindFromPlan,
    feePlanPayload,
    periodMonths,
    periodToMonthly,
} from '@/lib/feePlanForm';
import type {
    FeePlanBundleRow,
    FeePlanCourseOption,
    FeePlanFormData,
} from '@/lib/feePlanForm';

const props = defineProps<{
    plan: {
        id: number;
        academic_year_id: number | null;
        grade_level_id: number | null;
        course_ids: number[];
        group_name: string;
        pricing_group: string;
        unit: 'month' | 'session_block';
        session_block_size: number | null;
        list_price: number;
        quarter_price: number | null;
        quarter_single_price: number | null;
        quarter_double_price: number | null;
        material_fee: number;
        material_unit: 'term' | 'subject' | 'class_day';
        sort_order: number;
        is_active: boolean;
        bundles: FeePlanBundleRow[];
    };
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    courses: FeePlanCourseOption[];
}>();

const billingKind = billingKindFromPlan(
    props.plan.unit,
    props.plan.session_block_size,
);
const months = periodMonths(
    billingKind,
    props.plan.session_block_size ?? (billingKind === 'quarter' ? 3 : 4),
);
const storedQuarter =
    props.plan.quarter_single_price ?? props.plan.quarter_price;

const form = useForm<FeePlanFormData>({
    academic_year_id: props.plan.academic_year_id
        ? String(props.plan.academic_year_id)
        : '',
    grade_level_id: props.plan.grade_level_id
        ? String(props.plan.grade_level_id)
        : 'all',
    course_ids: [...props.plan.course_ids],
    group_name: props.plan.group_name,
    pricing_group: props.plan.pricing_group,
    billing_kind: billingKind,
    session_block_size: props.plan.session_block_size ?? months,
    list_price: periodToMonthly(props.plan.list_price, billingKind, months),
    quarter_price:
        storedQuarter === null || storedQuarter === undefined
            ? ''
            : periodToMonthly(storedQuarter, billingKind, months),
    material_fee: props.plan.material_fee,
    material_unit: props.plan.material_unit,
    sort_order: props.plan.sort_order,
    is_active: props.plan.is_active,
    bundles: props.plan.bundles.map((bundle) => ({
        ...bundle,
        course_ids: [...bundle.course_ids],
    })),
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '收費設定', href: '/fee-plans' },
            { title: '編輯', href: '#' },
        ],
    },
});

const submit = () =>
    form.transform(feePlanPayload).put(`/fee-plans/${props.plan.id}`);
</script>

<template>
    <Head title="編輯收費設定" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">編輯收費設定</h1>
        <form class="space-y-4 rounded-xl border p-4" @submit.prevent="submit">
            <FeePlanFields
                :form="form"
                :academic-years="academicYears"
                :grade-levels="gradeLevels"
                :courses="courses"
            />

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">更新</Button>
                <Button variant="outline" as-child
                    ><Link href="/fee-plans">返回</Link></Button
                >
            </div>
        </form>
    </div>
</template>
