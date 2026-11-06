<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FeeStandardTemplateFields from '@/components/fee-standards/FeeStandardTemplateFields.vue';
import { Button } from '@/components/ui/button';
import {
    emptyCheckItem,
    emptyTableItem,
    type FeeStandardFormData,
} from '@/lib/feeStandardForm';

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
        items: Array<{
            kind?: 'check' | 'table';
            label: string;
            heading_color?: string;
            detail: string;
            table_headers?: string[];
            table_rows?: string[][];
            sort_order: number;
        }>;
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
            ? props.template.items.map((item, i) => {
                  if (item.kind === 'table') {
                      const base = emptyTableItem(item.sort_order ?? i);
                      const headers =
                          item.table_headers && item.table_headers.length >= 2
                              ? [...item.table_headers]
                              : base.table_headers;
                      const rows =
                          item.table_rows && item.table_rows.length > 0
                              ? item.table_rows.map((row) => {
                                    const cells = [...row];
                                    while (cells.length < headers.length) {
                                        cells.push('');
                                    }
                                    return cells.slice(0, headers.length);
                                })
                              : [Array.from({ length: headers.length }, () => '')];
                      return {
                          ...base,
                          label: item.label,
                          heading_color:
                              item.heading_color || base.heading_color,
                          table_headers: headers,
                          table_rows: rows,
                          sort_order: item.sort_order ?? i,
                      };
                  }
                  return {
                      ...emptyCheckItem(item.sort_order ?? i),
                      label: item.label,
                      detail: item.detail ?? '',
                      sort_order: item.sort_order ?? i,
                  };
              })
            : [emptyCheckItem(0)],
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
                kind: item.kind === 'table' ? 'table' : 'check',
                label: item.label,
                heading_color:
                    item.kind === 'table' ? item.heading_color || null : null,
                detail: item.kind === 'table' ? null : item.detail,
                table_headers:
                    item.kind === 'table' ? item.table_headers : null,
                table_rows: item.kind === 'table' ? item.table_rows : null,
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
