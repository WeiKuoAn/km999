<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FeeStandardTemplateFields from '@/components/fee-standards/FeeStandardTemplateFields.vue';
import type { FeeStandardFormData } from '@/components/fee-standards/FeeStandardTemplateFields.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    template: {
        id: number;
        academic_year_id: number | null;
        grade_level_id: number;
        title: string;
        deadline_label: string;
        footer_note: string;
        is_active: boolean;
        sort_order: number;
        items: Array<{ label: string; detail: string; sort_order: number }>;
    };
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    defaultFooter: string;
}>();

const form = useForm<FeeStandardFormData>({
    academic_year_id: props.template.academic_year_id
        ? String(props.template.academic_year_id)
        : '',
    grade_level_id: String(props.template.grade_level_id),
    title: props.template.title,
    deadline_label: props.template.deadline_label ?? '',
    footer_note: props.template.footer_note ?? '',
    is_active: props.template.is_active,
    sort_order: props.template.sort_order,
    items:
        props.template.items.length > 0
            ? props.template.items.map((item) => ({
                  label: item.label,
                  detail: item.detail ?? '',
                  sort_order: item.sort_order,
              }))
            : [{ label: '', detail: '', sort_order: 0 }],
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '收費標準單', href: '/fee-standard-templates' },
            { title: '編輯', href: '#' },
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
        .put(`/fee-standard-templates/${props.template.id}`);
};
</script>

<template>
    <Head title="編輯收費標準模板" />
    <div class="page-shell mx-auto w-full max-w-6xl">
        <h1 class="text-xl font-semibold tracking-tight">編輯收費標準模板</h1>
        <form class="mt-4 space-y-5" @submit.prevent="submit">
            <FeeStandardTemplateFields
                :form="form"
                :academic-years="academicYears"
                :grade-levels="gradeLevels"
                :default-footer="defaultFooter"
            />
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">更新</Button>
                <Button variant="outline" as-child>
                    <Link href="/fee-standard-templates">返回</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
