<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FeePlanFields from '@/components/fee-plans/FeePlanFields.vue';
import { Button } from '@/components/ui/button';
import {
    emptyCoursePrices,
    emptyPeriodCoursePrice,
    emptyPeriodCoursePrices,
    feePlanPayload,
} from '@/lib/feePlanForm';
import type {
    FeePlanBundleRow,
    FeePlanCourseOption,
    FeePlanFormData,
    FeePlanPeriodCoursePrice,
} from '@/lib/feePlanForm';

const props = defineProps<{
    plan: {
        id: number;
        academic_year_id: number | null;
        grade_level_id: number | null;
        course_ids: number[];
        group_name: string;
        pricing_group: string;
        unit: 'month' | 'session_block' | 'custom_period';
        plan_kind?: 'period' | 'annual_package';
        per_course_periods?: boolean;
        allow_annual?: boolean;
        annual_start_month?: number | null;
        annual_end_month?: number | null;
        package_total?: number | null;
        annual_prices?: Record<number, number>;
        periods: Array<{
            course_id?: number | null;
            start_month: number;
            end_month: number;
            total_price?: number;
            double_price?: number | null;
            triple_price?: number | null;
            course_prices: Record<
                number,
                {
                    total_price: number;
                    double_price: number | null;
                    triple_price: number | null;
                }
            >;
        }>;
        session_block_size: number | null;
        list_price: number;
        quarter_price: number | null;
        quarter_single_price: number | null;
        quarter_double_price: number | null;
        material_fee: number;
        material_unit: 'term' | 'subject' | 'class_day';
        course_material_fees?: Record<number, number>;
        sort_order: number;
        is_active: boolean;
        bundles: Array<{
            id: number | null;
            name: string;
            course_ids: number[];
            course_prices: Record<number, number | null>;
            min_courses: number;
        }>;
    };
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    courses: FeePlanCourseOption[];
}>();

const form = useForm<FeePlanFormData>({
    academic_year_id: props.plan.academic_year_id
        ? String(props.plan.academic_year_id)
        : '',
    grade_level_id: props.plan.grade_level_id
        ? String(props.plan.grade_level_id)
        : props.plan.grade_level_id === null
          ? 'all'
          : '',
    course_ids: [...props.plan.course_ids],
    group_name: props.plan.group_name,
    pricing_group: props.plan.pricing_group,
    billing_kind: 'custom',
    session_block_size: '',
    list_price: props.plan.list_price,
    quarter_price: '',
    double_monthly: '',
    triple_monthly: '',
    material_fee: props.plan.material_fee,
    material_unit: props.plan.material_unit,
    course_material_fees: Object.fromEntries(
        props.plan.course_ids.map((id) => {
            const raw =
                props.plan.course_material_fees?.[id] ??
                (
                    props.plan.course_material_fees as
                        | Record<string, number>
                        | undefined
                )?.[String(id)];
            return [id, raw ?? props.plan.material_fee];
        }),
    ),
    sort_order: props.plan.sort_order,
    is_active: props.plan.is_active,
    plan_kind: props.plan.plan_kind === 'annual_package' ? 'annual_package' : 'period',
    per_course_periods: !!props.plan.per_course_periods,
    allow_annual: !!props.plan.allow_annual,
    annual_start_month: props.plan.annual_start_month ?? 7,
    annual_end_month: props.plan.annual_end_month ?? 6,
    package_total: props.plan.package_total ?? '',
    annual_prices: Object.fromEntries(
        props.plan.course_ids.map((id) => {
            const raw =
                props.plan.annual_prices?.[id] ??
                (
                    props.plan.annual_prices as
                        | Record<string, number>
                        | undefined
                )?.[String(id)];
            return [id, raw ?? ('' as const)];
        }),
    ),
    periods: (props.plan.periods ?? []).map((period) => {
        const courseIds = [...props.plan.course_ids];
        const perCourse = !!props.plan.per_course_periods && period.course_id != null;
        if (perCourse) {
            const courseId = Number(period.course_id);
            const raw =
                period.course_prices?.[courseId] ??
                (
                    period.course_prices as Record<
                        string,
                        {
                            total_price: number;
                            double_price: number | null;
                            triple_price: number | null;
                        }
                    >
                )?.[String(courseId)];
            const total =
                raw?.total_price ?? period.total_price ?? ('' as const);
            const double =
                raw?.double_price ?? period.double_price ?? ('' as const);
            const triple =
                raw?.triple_price ?? period.triple_price ?? ('' as const);

            return {
                course_id: courseId,
                start_month: period.start_month,
                end_month: period.end_month,
                total_price: total === null ? '' : total,
                double_price: double === null ? '' : double,
                triple_price: triple === null ? '' : triple,
                course_prices: {
                    [courseId]: {
                        total_price: total === null ? '' : total,
                        double_price: double === null ? '' : double,
                        triple_price: triple === null ? '' : triple,
                    } satisfies FeePlanPeriodCoursePrice,
                },
            };
        }

        const prices = emptyPeriodCoursePrices(courseIds);
        for (const id of courseIds) {
            const raw =
                period.course_prices?.[id] ??
                (
                    period.course_prices as Record<
                        string,
                        {
                            total_price: number;
                            double_price: number | null;
                            triple_price: number | null;
                        }
                    >
                )?.[String(id)];
            if (raw) {
                prices[id] = {
                    total_price: raw.total_price,
                    double_price: raw.double_price ?? '',
                    triple_price: raw.triple_price ?? '',
                } satisfies FeePlanPeriodCoursePrice;
            } else if (period.total_price) {
                // 舊資料尚未分科價：用期別統一價帶入
                prices[id] = {
                    ...emptyPeriodCoursePrice(),
                    total_price: period.total_price,
                };
            }
        }

        return {
            course_id: null,
            start_month: period.start_month,
            end_month: period.end_month,
            course_prices: prices,
        };
    }),
    bundles: props.plan.bundles.map((bundle): FeePlanBundleRow => {
        const courseIds = [...bundle.course_ids];
        const prices = emptyCoursePrices(courseIds);
        for (const id of courseIds) {
            const raw =
                bundle.course_prices?.[id] ??
                (
                    bundle.course_prices as Record<string, number | null>
                )?.[String(id)];
            prices[id] = raw === null || raw === undefined ? '' : raw;
        }

        return {
            id: bundle.id,
            name: bundle.name,
            course_ids: courseIds,
            course_prices: prices,
            min_courses: bundle.min_courses,
        };
    }),
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
    form.transform(feePlanPayload).put(`/fee-plans/${props.plan.id}`, {
        onError: () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    });
</script>

<template>
    <Head title="編輯收費設定" />
    <div class="page-shell mx-auto w-full max-w-6xl">
        <h1 class="text-xl font-semibold tracking-tight">編輯收費設定</h1>
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
                <Button type="submit" :disabled="form.processing">更新</Button>
                <Button variant="outline" as-child
                    ><Link href="/fee-plans">返回</Link></Button
                >
            </div>
        </form>
    </div>
</template>
