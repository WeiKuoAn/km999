<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type GradeOption = { id: number; name: string; code: number; code_padded: string };
type YearOption = { id: number; year_code: string; name: string; is_current: boolean };
type PreviewRow = {
    id: number;
    name: string;
    student_code: string | null;
    new_student_code: string | null;
    grade_name: string | null;
    academic_year_name: string | null;
    status: string;
    warning: string | null;
};

const props = defineProps<{
    grades: GradeOption[];
    years: YearOption[];
    preview: PreviewRow[];
    filters: {
        from_grade_level_id: string;
        to_grade_level_id: string;
        from_academic_year_id: string;
        to_academic_year_id: string;
        status: string;
    };
}>();

const page = usePage();
const flashSuccess = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
const flashError = computed(
    () => (page.props.flash as { error?: string } | undefined)?.error,
);

const fromGrade = ref(props.filters.from_grade_level_id ?? '');
const toGrade = ref(props.filters.to_grade_level_id ?? '');
const fromYear = ref(props.filters.from_academic_year_id ?? '');
const toYear = ref(props.filters.to_academic_year_id ?? '');
const status = ref(props.filters.status ?? 'active');

const selectedIds = ref<number[]>(props.preview.map((r) => r.id));

watch(
    () => props.preview,
    (rows) => {
        selectedIds.value = rows.map((r) => r.id);
    },
);

watch(
    () => props.filters,
    (f) => {
        fromGrade.value = f.from_grade_level_id ?? '';
        toGrade.value = f.to_grade_level_id ?? '';
        fromYear.value = f.from_academic_year_id ?? '';
        toYear.value = f.to_academic_year_id ?? '';
        status.value = f.status ?? 'active';
    },
);

const allSelected = computed(
    () =>
        props.preview.length > 0 &&
        props.preview.every((r) => selectedIds.value.includes(r.id)),
);

const toggleAll = () => {
    selectedIds.value = allSelected.value ? [] : props.preview.map((r) => r.id);
};

const toggleOne = (id: number) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};

const loadPreview = () => {
    router.get(
        '/student-promotions',
        {
            from_grade_level_id: fromGrade.value || undefined,
            to_grade_level_id: toGrade.value || undefined,
            from_academic_year_id: fromYear.value || undefined,
            to_academic_year_id: toYear.value || undefined,
            status: status.value === 'all' ? 'all' : status.value,
        },
        { preserveState: true, replace: true },
    );
};

const form = useForm({
    from_grade_level_id: '',
    to_grade_level_id: '',
    from_academic_year_id: '' as string | null,
    to_academic_year_id: '' as string | null,
    status: 'active',
    student_ids: [] as number[],
});

const submit = () => {
    if (!fromGrade.value || !toGrade.value) {
        window.alert('請選擇來源年級與目標年級。');
        return;
    }
    if (fromGrade.value === toGrade.value) {
        window.alert('來源與目標年級不可相同。');
        return;
    }
    if (selectedIds.value.length === 0) {
        window.alert('請至少勾選一位學生。');
        return;
    }

    const fromName = props.grades.find((g) => String(g.id) === fromGrade.value)?.name ?? '';
    const toName = props.grades.find((g) => String(g.id) === toGrade.value)?.name ?? '';
    if (
        !window.confirm(
            `確定將 ${selectedIds.value.length} 位學生由「${fromName}」轉至「${toName}」？\n學號會依年級兩碼變更（例：11507001 → 11508001）。`,
        )
    ) {
        return;
    }

    form.from_grade_level_id = fromGrade.value;
    form.to_grade_level_id = toGrade.value;
    form.from_academic_year_id = fromYear.value || null;
    form.to_academic_year_id = toYear.value || null;
    form.status = status.value;
    form.student_ids = selectedIds.value;
    form.post('/student-promotions', { preserveScroll: true });
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: '學生轉檔', href: '/student-promotions' }],
    },
});
</script>

<template>
    <Head title="學生轉檔" />
    <div class="page-shell">
        <PageHeader
            title="學生轉檔"
            description="新學年升級時批次變更年級與學號。學號＝學年碼＋年級兩碼＋流水三碼，升級只改年級兩碼並保留流水（例：國一 11507001 → 國二 11508001）。"
        />

        <div
            v-if="flashSuccess"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
        >
            {{ flashError }}
        </div>

        <form
            class="mb-4 grid gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="loadPreview"
        >
            <div class="grid gap-1">
                <Label for="from_grade">來源年級</Label>
                <select
                    id="from_grade"
                    v-model="fromGrade"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">請選擇</option>
                    <option v-for="g in grades" :key="g.id" :value="String(g.id)">
                        {{ g.name }}（{{ g.code_padded }}）
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="to_grade">目標年級</Label>
                <select
                    id="to_grade"
                    v-model="toGrade"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">請選擇</option>
                    <option v-for="g in grades" :key="g.id" :value="String(g.id)">
                        {{ g.name }}（{{ g.code_padded }}）
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="status">學生狀態</Label>
                <select
                    id="status"
                    v-model="status"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="active">在學</option>
                    <option value="paused">暫停</option>
                    <option value="all">全部</option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="from_year">篩選學年（可選）</Label>
                <select
                    id="from_year"
                    v-model="fromYear"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">全部學年</option>
                    <option v-for="y in years" :key="y.id" :value="String(y.id)">
                        {{ y.name }}（{{ y.year_code }}）
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="to_year">轉入學年（可選）</Label>
                <select
                    id="to_year"
                    v-model="toYear"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">維持原學年</option>
                    <option v-for="y in years" :key="y.id" :value="String(y.id)">
                        {{ y.name }}（{{ y.year_code }}）
                    </option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <Button type="submit" class="w-full sm:w-auto">預覽名單</Button>
            </div>
        </form>

        <div
            v-if="preview.length > 0"
            class="mb-3 flex flex-wrap items-center justify-between gap-2"
        >
            <p class="text-sm text-muted-foreground">
                共 {{ preview.length }} 位，已勾選 {{ selectedIds.length }} 位
            </p>
            <Button
                type="button"
                :disabled="form.processing || selectedIds.length === 0"
                @click="submit"
            >
                確認轉檔
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full min-w-[40rem] text-sm">
                <thead>
                    <tr class="border-b bg-muted/30">
                        <th class="px-3 py-2 text-left">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                :disabled="preview.length === 0"
                                @change="toggleAll"
                            />
                        </th>
                        <th class="px-3 py-2 text-left">姓名</th>
                        <th class="px-3 py-2 text-left">原學號</th>
                        <th class="px-3 py-2 text-left">新學號</th>
                        <th class="px-3 py-2 text-left">目前學年</th>
                        <th class="px-3 py-2 text-left">備註</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in preview" :key="row.id" class="border-b">
                        <td class="px-3 py-2">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(row.id)"
                                @change="toggleOne(row.id)"
                            />
                        </td>
                        <td class="px-3 py-2 font-medium">{{ row.name }}</td>
                        <td class="px-3 py-2 font-mono text-xs">
                            {{ row.student_code ?? '—' }}
                        </td>
                        <td class="px-3 py-2 font-mono text-xs font-semibold text-primary">
                            {{ row.new_student_code ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ row.academic_year_name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-amber-700">
                            {{ row.warning ?? '' }}
                        </td>
                    </tr>
                    <tr v-if="preview.length === 0">
                        <td
                            colspan="6"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            請選擇來源／目標年級後按「預覽名單」。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
