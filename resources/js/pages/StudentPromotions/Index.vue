<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { isGraduationGrade, studentStatusLabel } from '@/lib/studentStatus';

type GradeOption = {
    id: number;
    name: string;
    code: number;
    code_padded: string;
    is_graduation_grade?: boolean;
};
type YearOption = { id: number; year_code: string; name: string; is_current: boolean };
type PreviewRow = {
    id: number;
    name: string;
    student_code: string | null;
    new_student_code: string | null;
    grade_name: string | null;
    academic_year_name: string | null;
    status: string;
    new_status?: string;
    action?: string;
    warning: string | null;
};

const GRADUATE_VALUE = '__graduated__';

const props = defineProps<{
    grades: GradeOption[];
    years: YearOption[];
    preview: PreviewRow[];
    filters: {
        from_grade_level_id: string;
        to_grade_level_id: string;
        graduate: boolean;
        from_academic_year_id: string;
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
const toTarget = ref(
    props.filters.graduate
        ? GRADUATE_VALUE
        : (props.filters.to_grade_level_id ?? ''),
);
const fromYear = ref(props.filters.from_academic_year_id ?? '');
const status = ref(props.filters.status ?? 'active');

const selectedIds = ref<number[]>(props.preview.map((r) => r.id));

const fromGradeOption = computed(
    () => props.grades.find((g) => String(g.id) === fromGrade.value) ?? null,
);

const isFromGraduationGrade = computed(() =>
    fromGradeOption.value
        ? Boolean(fromGradeOption.value.is_graduation_grade) ||
          isGraduationGrade(fromGradeOption.value)
        : false,
);

const targetOptions = computed(() => {
    if (isFromGraduationGrade.value) {
        return [] as GradeOption[];
    }
    return props.grades.filter((g) => String(g.id) !== fromGrade.value);
});

const suggestNextTarget = (fromId: string): string => {
    const from = props.grades.find((g) => String(g.id) === fromId);
    if (!from) {
        return '';
    }
    if (from.is_graduation_grade || isGraduationGrade(from)) {
        return GRADUATE_VALUE;
    }
    const next = props.grades
        .filter((g) => g.code > from.code)
        .sort((a, b) => a.code - b.code)[0];
    return next ? String(next.id) : '';
};

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
        toTarget.value = f.graduate ? GRADUATE_VALUE : (f.to_grade_level_id ?? '');
        fromYear.value = f.from_academic_year_id ?? '';
        status.value = f.status ?? 'active';
    },
);

const isGraduate = () => toTarget.value === GRADUATE_VALUE;

const loadPreview = () => {
    if (!fromGrade.value || !toTarget.value) {
        return;
    }
    router.get(
        '/student-promotions',
        {
            from_grade_level_id: fromGrade.value || undefined,
            to_grade_level_id: isGraduate() ? undefined : toTarget.value || undefined,
            graduate: isGraduate() ? 1 : undefined,
            from_academic_year_id: fromYear.value || undefined,
            status: status.value === 'all' ? 'all' : status.value,
        },
        { preserveState: true, replace: true },
    );
};

const onFromGradeChange = () => {
    toTarget.value = suggestNextTarget(fromGrade.value);
    loadPreview();
};

const onFilterChange = () => {
    loadPreview();
};

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

const form = useForm({
    from_grade_level_id: '',
    to_grade_level_id: '' as string | null,
    graduate: false,
    from_academic_year_id: '' as string | null,
    status: 'active',
    student_ids: [] as number[],
});

const submit = () => {
    if (!fromGrade.value || !toTarget.value) {
        window.alert(
            isFromGraduationGrade.value
                ? '請確認目前年級為國三，並選擇「已畢業」。'
                : '請選擇目前年級與新的年級。',
        );
        return;
    }
    if (!isGraduate() && fromGrade.value === toTarget.value) {
        window.alert('目前年級與新的年級不可相同。');
        return;
    }
    if (selectedIds.value.length === 0) {
        window.alert('請至少勾選一位學生。');
        return;
    }

    const fromName = fromGradeOption.value?.name ?? '';
    const toName = isGraduate()
        ? '已畢業'
        : (props.grades.find((g) => String(g.id) === toTarget.value)?.name ?? '');

    const confirmMsg = isGraduate()
        ? `確定將 ${selectedIds.value.length} 位「${fromName}」學生標記為「已畢業」？\n學號與年級維持不變，狀態改為已畢業。`
        : `確定將 ${selectedIds.value.length} 位學生由「${fromName}」轉至「${toName}」？\n學號會依年級兩碼變更（例：11507001 → 11508001）。`;

    if (!window.confirm(confirmMsg)) {
        return;
    }

    form.from_grade_level_id = fromGrade.value;
    form.to_grade_level_id = isGraduate() ? null : toTarget.value;
    form.graduate = isGraduate();
    form.from_academic_year_id = fromYear.value || null;
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
            description="國一／國二升級時變更年級與學號（例：11507001 → 11508001）。國三轉檔則改為「已畢業」，學號不變。"
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
                <Label for="from_grade">目前年級</Label>
                <select
                    id="from_grade"
                    v-model="fromGrade"
                    class="h-9 rounded-md border bg-background px-3"
                    @change="onFromGradeChange"
                >
                    <option value="">請選擇</option>
                    <option v-for="g in grades" :key="g.id" :value="String(g.id)">
                        {{ g.name }}（{{ g.code_padded }}）
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="to_grade">新的年級</Label>
                <select
                    id="to_grade"
                    v-model="toTarget"
                    class="h-9 rounded-md border bg-background px-3"
                    :disabled="!fromGrade"
                    @change="onFilterChange"
                >
                    <option value="">請選擇</option>
                    <option v-if="isFromGraduationGrade" :value="GRADUATE_VALUE">
                        已畢業
                    </option>
                    <option
                        v-for="g in targetOptions"
                        :key="g.id"
                        :value="String(g.id)"
                    >
                        {{ g.name }}（{{ g.code_padded }}）
                    </option>
                </select>
                <p
                    v-if="isFromGraduationGrade"
                    class="text-xs text-muted-foreground"
                >
                    國三僅能轉為已畢業（學號不變，狀態改為已畢業）。
                </p>
            </div>
            <div class="grid gap-1">
                <Label for="status">學生狀態</Label>
                <select
                    id="status"
                    v-model="status"
                    class="h-9 rounded-md border bg-background px-3"
                    @change="onFilterChange"
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
                    @change="onFilterChange"
                >
                    <option value="">全部學年</option>
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
                        <th class="px-3 py-2 text-left">結果</th>
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
                        <td class="px-3 py-2">
                            <template v-if="row.action === 'graduate'">
                                狀態 → {{ studentStatusLabel(row.new_status ?? 'graduated') }}
                            </template>
                            <template v-else>
                                {{ row.academic_year_name ?? '—' }}
                            </template>
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
                            請選擇目前年級與新的年級（國三請選已畢業）後按「預覽名單」。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
