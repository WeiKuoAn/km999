<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { isGraduationGrade, studentStatusLabel } from '@/lib/studentStatus';

type GradeOption = {
    id: number;
    name: string;
    code: number;
    code_padded: string;
    is_graduation_grade?: boolean;
};
type YearOption = {
    id: number;
    year_code: string;
    name: string;
    is_current: boolean;
};
type TargetSubject = { id: number; name: string; category: string | null };
type PromotionLog = {
    id: number;
    created_at: string | null;
    action: string;
    action_label: string;
    student_name: string;
    from_grade_name: string | null;
    to_grade_name: string | null;
    old_student_code: string | null;
    new_student_code: string | null;
    course_mode: string | null;
    course_mode_label: string;
    note: string | null;
    performed_by: string;
};
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
    course_mode?: 'auto' | 'manual' | null;
    current_labels?: string[];
    transferable_labels?: string[];
    blocked_labels?: string[];
    transferable_ids?: number[];
    course_note?: string | null;
    warning: string | null;
};

const GRADUATE_VALUE = '__graduated__';

const props = defineProps<{
    grades: GradeOption[];
    years: YearOption[];
    preview: PreviewRow[];
    target_subjects?: TargetSubject[];
    logs?: PromotionLog[];
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
/** 需手動選課的學生 → 已選 course_ids */
const manualCourses = reactive<Record<number, number[]>>({});

const fromGradeOption = computed(
    () => props.grades.find((g) => String(g.id) === fromGrade.value) ?? null,
);

const toGradeOption = computed(() =>
    isGraduate()
        ? null
        : (props.grades.find((g) => String(g.id) === toTarget.value) ?? null),
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

const syncManualDefaults = (rows: PreviewRow[]) => {
    for (const key of Object.keys(manualCourses)) {
        delete manualCourses[Number(key)];
    }
    for (const row of rows) {
        if (row.course_mode === 'manual') {
            manualCourses[row.id] = [...(row.transferable_ids ?? [])];
        }
    }
};

watch(
    () => props.preview,
    (rows) => {
        selectedIds.value = rows.map((r) => r.id);
        syncManualDefaults(rows);
    },
);

watch(
    () => props.filters,
    (f) => {
        fromGrade.value = f.from_grade_level_id ?? '';
        toTarget.value = f.graduate
            ? GRADUATE_VALUE
            : (f.to_grade_level_id ?? '');
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
            to_grade_level_id: isGraduate()
                ? undefined
                : toTarget.value || undefined,
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
    selectedIds.value = allSelected.value
        ? []
        : props.preview.map((r) => r.id);
};

const toggleOne = (id: number) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};

const toggleManualCourse = (studentId: number, courseId: number) => {
    const current = manualCourses[studentId] ?? [];
    if (current.includes(courseId)) {
        manualCourses[studentId] = current.filter((id) => id !== courseId);
    } else {
        manualCourses[studentId] = [...current, courseId];
    }
};

const subjectLabel = (s: TargetSubject) =>
    s.category ? `${s.name}（${s.category}）` : s.name;

const selectedManualRows = computed(() =>
    props.preview.filter(
        (r) =>
            selectedIds.value.includes(r.id) && r.course_mode === 'manual',
    ),
);

const autoSelectedCount = computed(
    () =>
        props.preview.filter(
            (r) =>
                selectedIds.value.includes(r.id) && r.course_mode === 'auto',
        ).length,
);

const form = useForm({
    from_grade_level_id: '',
    to_grade_level_id: '' as string | null,
    graduate: false,
    from_academic_year_id: '' as string | null,
    status: 'active',
    student_ids: [] as number[],
    course_selections: [] as Array<{
        student_id: number;
        course_ids: number[];
    }>,
});

const individualOpen = ref(false);
const individualRow = ref<PreviewRow | null>(null);
const individualCourseIds = ref<number[]>([]);
const individualError = ref('');

const openIndividual = (row: PreviewRow) => {
    if (!fromGrade.value || !toTarget.value) {
        window.alert('請先選擇目前年級與新的年級（或已畢業）。');
        return;
    }
    individualRow.value = row;
    individualError.value = '';
    if (isGraduate() || row.action === 'graduate') {
        individualCourseIds.value = [];
    } else {
        // 預選可延續科目；可再加勾新年級科目（例：國二加國文）
        individualCourseIds.value = [...(row.transferable_ids ?? [])];
    }
    individualOpen.value = true;
};

const toggleIndividualCourse = (courseId: number) => {
    if (individualCourseIds.value.includes(courseId)) {
        individualCourseIds.value = individualCourseIds.value.filter(
            (id) => id !== courseId,
        );
    } else {
        individualCourseIds.value = [...individualCourseIds.value, courseId];
    }
};

const submitIndividual = () => {
    const row = individualRow.value;
    if (!row || !fromGrade.value || !toTarget.value) {
        return;
    }

    const graduate = isGraduate() || row.action === 'graduate';
    if (!graduate && individualCourseIds.value.length === 0) {
        individualError.value = `請選擇${toGradeOption.value?.name ?? '新年級'}要修的課程（可保留原有科目並加選新科目）。`;
        return;
    }

    const fromName = fromGradeOption.value?.name ?? '';
    const toName = graduate
        ? '已畢業'
        : (toGradeOption.value?.name ?? '');
    const confirmMsg = graduate
        ? `確定將「${row.name}」標記為「已畢業」？`
        : `確定將「${row.name}」由「${fromName}」個別轉至「${toName}」？\n將依你勾選的課程設定${toName}要修科目。`;

    if (!window.confirm(confirmMsg)) {
        return;
    }

    form.from_grade_level_id = fromGrade.value;
    form.to_grade_level_id = graduate ? null : toTarget.value;
    form.graduate = graduate;
    form.from_academic_year_id = fromYear.value || null;
    form.status = status.value;
    form.student_ids = [row.id];
    form.course_selections = graduate
        ? []
        : [
              {
                  student_id: row.id,
                  course_ids: [...individualCourseIds.value],
              },
          ];
    form.post('/student-promotions', {
        preserveScroll: true,
        onSuccess: () => {
            individualOpen.value = false;
            individualRow.value = null;
        },
        onError: (errors) => {
            const key = `course_selections.${row.id}`;
            individualError.value =
                (errors as Record<string, string>)[key] ??
                (errors as Record<string, string>).student_ids ??
                '轉檔失敗，請再試一次。';
        },
    });
};

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

    for (const row of selectedManualRows.value) {
        const ids = manualCourses[row.id] ?? [];
        if (ids.length === 0) {
            window.alert(
                `${row.name}：科目有變，請選擇${toGradeOption.value?.name ?? '新年級'}要修的課程。`,
            );
            return;
        }
    }

    const fromName = fromGradeOption.value?.name ?? '';
    const toName = isGraduate()
        ? '已畢業'
        : (toGradeOption.value?.name ?? '');

    const confirmMsg = isGraduate()
        ? `確定將 ${selectedIds.value.length} 位「${fromName}」學生標記為「已畢業」？\n學號與年級維持不變，狀態改為已畢業。`
        : `確定將 ${selectedIds.value.length} 位學生由「${fromName}」轉至「${toName}」？\n學號會依年級變更；科目不變者自動帶課，有變者依你勾選的${toName}課程。`;

    if (!window.confirm(confirmMsg)) {
        return;
    }

    form.from_grade_level_id = fromGrade.value;
    form.to_grade_level_id = isGraduate() ? null : toTarget.value;
    form.graduate = isGraduate();
    form.from_academic_year_id = fromYear.value || null;
    form.status = status.value;
    form.student_ids = selectedIds.value;
    form.course_selections = selectedManualRows.value.map((row) => ({
        student_id: row.id,
        course_ids: manualCourses[row.id] ?? [],
    }));
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
            description="批次轉檔：科目不變可自動帶課；科目有變請勾選後於上方選課。右側「個別轉檔」可為單人調整課程（例：國一英文＋數學，升國二加選國文）。國三轉已畢業。"
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
                    <option
                        v-for="g in grades"
                        :key="g.id"
                        :value="String(g.id)"
                    >
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
                    <option
                        v-if="isFromGraduationGrade"
                        :value="GRADUATE_VALUE"
                    >
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
                    <option
                        v-for="y in years"
                        :key="y.id"
                        :value="String(y.id)"
                    >
                        {{ y.name }}（{{ y.year_code }}）
                    </option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <Button type="submit" class="w-full sm:w-auto"
                    >預覽名單</Button
                >
            </div>
        </form>

        <div
            v-if="preview.length > 0"
            class="mb-3 flex flex-wrap items-center justify-between gap-2"
        >
            <p class="text-sm text-muted-foreground">
                共 {{ preview.length }} 位，已勾選 {{ selectedIds.length }} 位
                <template v-if="!isGraduate()">
                    （可自動帶課 {{ autoSelectedCount }}／需手動選課
                    {{ selectedManualRows.length }}）
                </template>
            </p>
            <Button
                type="button"
                :disabled="form.processing || selectedIds.length === 0"
                @click="submit"
            >
                確認轉檔
            </Button>
        </div>

        <div
            v-if="selectedManualRows.length > 0"
            class="mb-4 space-y-3 rounded-xl border border-amber-200/80 bg-amber-50/40 p-4"
        >
            <div>
                <h2 class="text-sm font-semibold text-amber-900">
                    需手動選課（科目有變）
                </h2>
                <p class="mt-0.5 text-xs text-amber-800/80">
                    下列學生有科目無法自動對應{{
                        toGradeOption?.name ?? '新年級'
                    }}，請勾選轉檔後要修的課（可含可延續科目與新科目）。
                </p>
            </div>
            <div
                v-for="row in selectedManualRows"
                :key="`manual-${row.id}`"
                class="rounded-lg border border-amber-200/70 bg-card p-3"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-medium">
                        {{ row.name }}
                        <span class="font-mono text-xs text-muted-foreground">
                            {{ row.student_code }}
                        </span>
                    </p>
                    <p
                        v-if="row.blocked_labels?.length"
                        class="text-xs text-rose-700"
                    >
                        無法對應：{{ row.blocked_labels.join('、') }}
                    </p>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="s in target_subjects ?? []"
                        :key="`${row.id}-${s.id}`"
                        type="button"
                        class="rounded-md border px-2.5 py-1 text-sm"
                        :class="
                            (manualCourses[row.id] ?? []).includes(s.id)
                                ? 'border-primary bg-primary/10 text-foreground'
                                : 'border-border text-muted-foreground hover:border-primary/40'
                        "
                        @click="toggleManualCourse(row.id, s.id)"
                    >
                        {{ subjectLabel(s) }}
                    </button>
                </div>
                <p
                    v-if="form.errors[`course_selections.${row.id}`]"
                    class="mt-2 text-xs text-destructive"
                >
                    {{ form.errors[`course_selections.${row.id}`] }}
                </p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full min-w-[48rem] text-sm">
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
                        <th class="px-3 py-2 text-left">課程</th>
                        <th class="px-3 py-2 text-left">備註</th>
                        <th class="px-3 py-2 text-right">操作</th>
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
                        <td
                            class="px-3 py-2 font-mono text-xs font-semibold text-primary"
                        >
                            {{ row.new_student_code ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <template v-if="row.action === 'graduate'">
                                狀態 →
                                {{
                                    studentStatusLabel(
                                        row.new_status ?? 'graduated',
                                    )
                                }}
                            </template>
                            <template v-else-if="row.course_mode === 'auto'">
                                <span
                                    class="text-xs font-medium text-emerald-700"
                                    >可自動帶課</span
                                >
                                <p
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{
                                        row.transferable_labels?.length
                                            ? row.transferable_labels.join('、')
                                            : '無進行中科目'
                                    }}
                                </p>
                            </template>
                            <template v-else-if="row.course_mode === 'manual'">
                                <span class="text-xs font-medium text-amber-800"
                                    >需手動選課</span
                                >
                                <p
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    目前：{{
                                        row.current_labels?.length
                                            ? row.current_labels.join('、')
                                            : '—'
                                    }}
                                </p>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            <span
                                v-if="row.warning"
                                class="block text-amber-700"
                                >{{ row.warning }}</span
                            >
                            <span v-if="row.course_note">{{
                                row.course_note
                            }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="form.processing"
                                @click="openIndividual(row)"
                            >
                                個別轉檔
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="preview.length === 0">
                        <td
                            colspan="7"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            請選擇目前年級與新的年級（國三請選已畢業）後按「預覽名單」。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog
            :open="individualOpen"
            @update:open="
                (v) => {
                    individualOpen = v;
                    if (!v) individualRow = null;
                }
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>個別轉檔</DialogTitle>
                    <DialogDescription>
                        <template v-if="individualRow">
                            {{ individualRow.name }}
                            <span class="font-mono">
                                （{{ individualRow.student_code ?? '—' }} →
                                {{ individualRow.new_student_code ?? '—' }}）
                            </span>
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="individualRow && (isGraduate() || individualRow.action === 'graduate')"
                    class="text-sm text-muted-foreground"
                >
                    將標記為已畢業；學號與年級維持不變。
                </div>

                <div v-else-if="individualRow" class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        請勾選升上「{{ toGradeOption?.name ?? '新年級' }}」後要修的課。可保留原有英文／數學，並加選國文等新科目。
                    </p>
                    <p
                        v-if="individualRow.transferable_labels?.length"
                        class="text-xs text-emerald-700"
                    >
                        可延續（已預選）：{{
                            individualRow.transferable_labels.join('、')
                        }}
                    </p>
                    <p
                        v-if="individualRow.current_labels?.length"
                        class="text-xs text-muted-foreground"
                    >
                        原年級科目：{{
                            individualRow.current_labels.join('、')
                        }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="s in target_subjects ?? []"
                            :key="`ind-${s.id}`"
                            type="button"
                            class="rounded-md border px-2.5 py-1.5 text-sm"
                            :class="
                                individualCourseIds.includes(s.id)
                                    ? 'border-primary bg-primary/10 text-foreground'
                                    : 'border-border text-muted-foreground hover:border-primary/40'
                            "
                            @click="toggleIndividualCourse(s.id)"
                        >
                            {{ subjectLabel(s) }}
                        </button>
                    </div>
                    <p
                        v-if="!(target_subjects ?? []).length"
                        class="text-xs text-amber-700"
                    >
                        新年級尚無可選課程，請先至收費標準／課程確認適用年級。
                    </p>
                    <p v-if="individualError" class="text-xs text-destructive">
                        {{ individualError }}
                    </p>
                    <p
                        v-if="form.errors[`course_selections.${individualRow.id}`]"
                        class="text-xs text-destructive"
                    >
                        {{
                            form.errors[
                                `course_selections.${individualRow.id}`
                            ]
                        }}
                    </p>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="individualOpen = false"
                    >
                        取消
                    </Button>
                    <Button
                        type="button"
                        :disabled="form.processing"
                        @click="submitIndividual"
                    >
                        確認個別轉檔
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div class="mt-6 rounded-xl border border-sidebar-border/70 bg-card">
            <div class="border-b border-sidebar-border/60 px-4 py-3">
                <h2 class="text-base font-semibold">轉檔紀錄</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    最近 50 筆；升級與畢業都會留下紀錄。
                </p>
            </div>
            <p
                v-if="!(logs ?? []).length"
                class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
                尚無轉檔紀錄。
            </p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/30 text-muted-foreground">
                            <th class="px-3 py-2 text-left font-normal">時間</th>
                            <th class="px-3 py-2 text-left font-normal">學生</th>
                            <th class="px-3 py-2 text-left font-normal">類型</th>
                            <th class="px-3 py-2 text-left font-normal">年級</th>
                            <th class="px-3 py-2 text-left font-normal">學號</th>
                            <th class="px-3 py-2 text-left font-normal">課程</th>
                            <th class="px-3 py-2 text-left font-normal">操作者</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in logs"
                            :key="log.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2.5 whitespace-nowrap tabular-nums">
                                {{ log.created_at ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5 font-medium">
                                {{ log.student_name }}
                            </td>
                            <td class="px-3 py-2.5">{{ log.action_label }}</td>
                            <td class="px-3 py-2.5 text-muted-foreground">
                                {{ log.from_grade_name ?? '—' }} →
                                {{ log.to_grade_name ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs">
                                <template
                                    v-if="
                                        log.old_student_code &&
                                        log.new_student_code &&
                                        log.old_student_code !==
                                            log.new_student_code
                                    "
                                >
                                    {{ log.old_student_code }} →
                                    {{ log.new_student_code }}
                                </template>
                                <template v-else>
                                    {{
                                        log.new_student_code ||
                                        log.old_student_code ||
                                        '—'
                                    }}
                                </template>
                            </td>
                            <td class="px-3 py-2.5 text-muted-foreground">
                                {{ log.course_mode_label }}
                                <span
                                    v-if="log.note"
                                    class="block text-xs"
                                    >{{ log.note }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">{{ log.performed_by }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
