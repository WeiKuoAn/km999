<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FeeStandardTemplateFields from '@/components/fee-standards/FeeStandardTemplateFields.vue';
import type { FeeStandardFormData } from '@/components/fee-standards/FeeStandardTemplateFields.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    defaultAcademicYearId: number | null;
    defaultFooter: string;
}>();

const form = useForm<FeeStandardFormData>({
    academic_year_id: props.defaultAcademicYearId
        ? String(props.defaultAcademicYearId)
        : '',
    grade_level_id: '',
    title: '',
    deadline_label: '',
    footer_note: props.defaultFooter,
    is_active: true,
    sort_order: 0,
    items: [{ label: '', detail: '', sort_order: 0 }],
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '收費標準單', href: '/fee-standard-templates' },
            { title: '新增', href: '/fee-standard-templates/create' },
        ],
    },
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            academic_year_id:
                data.academic_year_id === ''
                    ? null
                    : Number(data.academic_year_id),
            grade_level_id: Number(data.grade_level_id),
            items: data.items.map((item, i) => ({
                label: item.label,
                detail: item.detail,
                sort_order: i,
            })),
        }))
        .post('/fee-standard-templates');
};
</script>

<template>
    <Head title="新增收費標準模板" />
    <div class="page-shell mx-auto w-full max-w-6xl">
        <h1 class="text-xl font-semibold tracking-tight">新增收費標準模板</h1>
        <form class="mt-4 space-y-5" @submit.prevent="submit">
            <FeeStandardTemplateFields
                :form="form"
                :academic-years="academicYears"
                :grade-levels="gradeLevels"
                :default-footer="defaultFooter"
            />
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child>
                    <Link href="/fee-standard-templates">返回</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
