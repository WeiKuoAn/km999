<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FeePlanFields from '@/components/fee-plans/FeePlanFields.vue';
import { Button } from '@/components/ui/button';
import { feePlanPayload } from '@/lib/feePlanForm';
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
    billing_kind: 'month',
    session_block_size: 4,
    list_price: 0,
    quarter_price: '',
    material_fee: 0,
    material_unit: 'term',
    sort_order: 0,
    is_active: true,
    periods: [],
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

const submit = () => form.transform(feePlanPayload).post('/fee-plans');
</script>

<template>
    <Head title="新增收費設定" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增收費設定</h1>
        <form class="space-y-4 rounded-xl border p-4" @submit.prevent="submit">
            <FeePlanFields
                :form="form"
                :academic-years="academicYears"
                :grade-levels="gradeLevels"
                :courses="courses"
                require-grade
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
