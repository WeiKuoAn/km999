<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FeePlanFields from '@/components/fee-plans/FeePlanFields.vue';
import { Button } from '@/components/ui/button';
import { emptyPeriodCoursePrices, feePlanPayload } from '@/lib/feePlanForm';
import type { FeePlanCourseOption, FeePlanFormData } from '@/lib/feePlanForm';

const props = defineProps<{
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    courses: FeePlanCourseOption[];
    defaultAcademicYearId: number | null;
}>();

const form = useForm<FeePlanFormData>({
    academic_year_id: props.defaultAcademicYearId
        ? String(props.defaultAcademicYearId)
        : '',
    grade_level_id: '',
    course_ids: [],
    group_name: '',
    pricing_group: 'other',
    billing_kind: 'custom',
    session_block_size: 4,
    list_price: 0,
    quarter_price: '',
    double_monthly: '',
    triple_monthly: '',
    material_fee: 0,
    material_unit: 'term',
    sort_order: 0,
    is_active: true,
    plan_kind: 'period',
    per_course_periods: false,
    allow_annual: false,
    annual_start_month: 7,
    annual_end_month: 6,
    package_total: '',
    annual_prices: {},
    periods: [
        {
            course_id: null,
            start_month: 7,
            end_month: 8,
            course_prices: emptyPeriodCoursePrices([]),
        },
    ],
    bundles: [],
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '收費設定', href: '/fee-plans' },
            { title: '新增', href: '/fee-plans/create' },
        ],
    },
});

const submit = () =>
    form.transform(feePlanPayload).post('/fee-plans', {
        onError: () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    });
</script>

<template>
    <Head title="新增收費設定" />
    <div class="page-shell mx-auto w-full max-w-6xl">
        <h1 class="text-xl font-semibold tracking-tight">新增收費設定</h1>
        <form class="mt-4 space-y-5" novalidate @submit.prevent="submit">
            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
            >
                尚有欄位未填妥或格式不正確，請往下檢查紅字提示後再儲存。
                <ul class="mt-1 list-disc pl-5">
                    <li
                        v-for="(message, key) in form.errors"
                        :key="key"
                    >
                        {{ message }}
                    </li>
                </ul>
            </div>

            <FeePlanFields
                :form="form"
                :academic-years="academicYears"
                :grade-levels="gradeLevels"
                :courses="courses"
            />

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child
                    ><Link href="/fee-plans">返回</Link></Button
                >
            </div>
        </form>
    </div>
</template>
