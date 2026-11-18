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
import {
    billingMonthOptions,
    defaultBillingMonths,
    monthKey,
    parseMonthKey,
} from '@/lib/weekdayDates';
import type { YearMonth } from '@/lib/weekdayDates';

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
type TargetSubject = {
    id: number;
    name: string;
    category: string | null;
    unit?: string;
    list?: number;
    q_single?: number;
    material?: number;
    material_unit?: string;
    periods?: Array<{
        start_month: number;
        end_month: number;
        total_price: number;
        double_price?: number | null;
        triple_price?: number | null;
    }>;
    allow_annual?: boolean;
    annual_start_month?: number | null;
    annual_end_month?: number | null;
    annual_end_next_year?: boolean;
    annual_price?: number | null;
};
type AnnualPackage = {
    fee_plan_id: number;
    label: string;
    total: number;
    monthly_total: number;
    start_month: number;
    end_month: number;
    ready: boolean;
    required_course_ids?: number[];
    required_count?: number;
};

type AnnualSelectionRow = {
    fee_plan_id: string;
    deposit: string;
    next_fee: string;
    note: string;
    next_fee_manual: boolean;
};
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
    needs_annual_dialog?: boolean;
    annual_packages?: AnnualPackage[];
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
    annual_selections: [] as Array<{
        student_id: number;
        fee_plan_id: number | null;
        deposit: number | null;
        next_fee: number | null;
        pay_cycle: string | null;
        course_months: string[] | null;
        note: string;
    }>,
});

const individualOpen = ref(false);
const individualRow = ref<PreviewRow | null>(null);
const individualCourseIds = ref<number[]>([]);
const individualError = ref('');
const individualAnnual = reactive<AnnualSelectionRow>({
    fee_plan_id: '',
    deposit: '0',
    next_fee: '0',
    note: '',
    next_fee_manual: false,
});
const individualPayCycle = ref<'monthly' | 'quarterly' | 'annual'>('quarterly');
const individualStartDate = ref('');
const individualMonthKeys = ref<string[]>([]);

const annualOpen = ref(false);
const annualRows = ref<PreviewRow[]>([]);
const annualSelections = reactive<Record<number, AnnualSelectionRow>>({});

const needsAnnualDialog = computed(() => Boolean(props.needs_annual_dialog));
const annualPackages = computed(() => props.annual_packages ?? []);

const emptyAnnualSelection = (): AnnualSelectionRow => ({
    fee_plan_id: '',
    deposit: '0',
    next_fee: '0',
    note: '',
    next_fee_manual: false,
});

const packageOptionLabel = (pack: AnnualPackage) =>
    `${pack.label}（$${pack.total.toLocaleString()}｜${pack.start_month}–${pack.end_month}月）`;

const findPackage = (feePlanId: string): AnnualPackage | null =>
    annualPackages.value.find((p) => String(p.fee_plan_id) === feePlanId) ??
    null;

const idsMatch = (a: number[], b: number[]) => {
    if (a.length !== b.length) {
        return false;
    }
    const left = [...a].sort((x, y) => x - y);
    const right = [...b].sort((x, y) => x - y);
    return left.every((id, i) => id === right[i]);
};

const packageMatchesCourses = (pack: AnnualPackage, courseIds: number[]) =>
    idsMatch(pack.required_course_ids ?? [], courseIds);

const parseMoney = (raw: string): number => {
    const n = Number(String(raw).replace(/[^\d]/g, ''));
    return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
};

const suggestedNextFee = (total: number, deposit: number) =>
    Math.max(0, total - deposit);

const syncAnnualNextFee = (sel: AnnualSelectionRow) => {
    const pack = findPackage(sel.fee_plan_id);
    if (!pack) {
        sel.deposit = '0';
        return;
    }
    if (sel.next_fee_manual) {
        return;
    }
    sel.next_fee = String(
        suggestedNextFee(pack.total, parseMoney(sel.deposit)),
    );
};

const onAnnualPlanChange = (sel: AnnualSelectionRow) => {
    sel.next_fee_manual = false;
    sel.deposit = '0';
    syncAnnualNextFee(sel);
    if (sel === individualAnnual && sel.fee_plan_id === '') {
        syncIndividualNonAnnualMonths();
        syncIndividualNonAnnualFee();
    }
};

const onAnnualDepositChange = (sel: AnnualSelectionRow) => {
    syncAnnualNextFee(sel);
};

const onAnnualNextFeeInput = (sel: AnnualSelectionRow) => {
    sel.next_fee_manual = true;
};

const initAnnualSelections = (rows: PreviewRow[]) => {
    for (const key of Object.keys(annualSelections)) {
        delete annualSelections[Number(key)];
    }
    for (const row of rows) {
        annualSelections[row.id] = emptyAnnualSelection();
    }
};

const resetIndividualAnnual = () => {
    Object.assign(individualAnnual, emptyAnnualSelection());
    individualPayCycle.value = 'quarterly';
    individualStartDate.value = defaultPromotionStartDate();
    individualMonthKeys.value = [];
};

const defaultPromotionStartDate = (): string => {
    const current =
        props.years.find((y) => y.is_current) ?? props.years[0] ?? null;
    const code = Number(current?.year_code ?? new Date().getFullYear() - 1911);
    const year = Number.isFinite(code) ? code + 1911 : new Date().getFullYear();

    return `${year}-07-01`;
};

const monthSpanForCycle = (cycle: 'monthly' | 'quarterly' | 'annual') => {
    switch (cycle) {
        case 'monthly':
            return 1;
        case 'annual':
            return 12;
        default:
            return 3;
    }
};

const syncIndividualNonAnnualMonths = () => {
    if (!individualStartDate.value) {
        individualStartDate.value = defaultPromotionStartDate();
    }
    individualMonthKeys.value = defaultBillingMonths(
        individualStartDate.value,
        monthSpanForCycle(individualPayCycle.value),
    ).map((m) => monthKey(m.y, m.m));
};

const subjectById = (id: number) =>
    (props.target_subjects ?? []).find((s) => s.id === id) ?? null;

const periodMonthCount = (start: number, end: number): number => {
    let count = 0;
    let m = start;
    for (let i = 0; i < 12; i++) {
        count += 1;
        if (m === end) {
            break;
        }
        m = (m % 12) + 1;
    }
    return Math.max(1, count);
};

/** 不年繳簡易試算（與收款頁接近：月／季／年＋自訂期別均攤） */
const estimateNonAnnualFee = (
    courseIds: number[],
    cycle: 'monthly' | 'quarterly' | 'annual',
    monthKeys: string[],
): { tuition: number; material: number; total: number; months: YearMonth[] } => {
    const months = monthKeys
        .map((key) => parseMonthKey(key))
        .filter((m): m is YearMonth => m !== null)
        .sort((a, b) => a.y * 12 + a.m - (b.y * 12 + b.m));
    const monthCount = Math.max(1, months.length);
    let tuition = 0;
    let material = 0;

    for (const id of courseIds) {
        const s = subjectById(id);
        if (!s) {
            continue;
        }

        if (s.unit === 'custom_period' && (s.periods?.length ?? 0) > 0) {
            const subjectCount = Math.max(1, courseIds.length);
            for (const month of months) {
                const period = (s.periods ?? []).find((p) => {
                    const nums: number[] = [];
                    let cursor = p.start_month;
                    for (let i = 0; i < 12; i++) {
                        nums.push(cursor);
                        if (cursor === p.end_month) {
                            break;
                        }
                        cursor = (cursor % 12) + 1;
                    }
                    return nums.includes(month.m);
                });
                if (!period) {
                    continue;
                }
                const size = periodMonthCount(period.start_month, period.end_month);
                let periodTotal = period.total_price;
                if (subjectCount >= 3 && period.triple_price != null) {
                    periodTotal = period.triple_price;
                } else if (subjectCount === 2 && period.double_price != null) {
                    periodTotal = period.double_price;
                }
                tuition += Math.round(periodTotal / size);
            }
        } else if (cycle === 'monthly') {
            tuition += Number(s.list ?? 0) * monthCount;
        } else if (cycle === 'annual') {
            tuition +=
                s.annual_price != null && s.annual_price > 0
                    ? Number(s.annual_price)
                    : Number(s.list ?? 0) * 12;
        } else {
            // 季繳：以單科季繳價 × 季數
            const quarters = Math.max(1, Math.ceil(monthCount / 3));
            tuition += Number(s.q_single ?? s.list ?? 0) * quarters;
        }

        const mat = Number(s.material ?? 0);
        if (mat > 0 && (s.material_unit ?? 'term') !== 'class_day') {
            const halves = new Set(
                months
                    .filter((m) => m.m === 1 || m.m === 7)
                    .map((m) => `${m.y}-${m.m <= 6 ? 'H1' : 'H2'}`),
            );
            material += Math.round(mat / 2) * halves.size;
        }
    }

    return {
        tuition,
        material,
        total: tuition + material,
        months,
    };
};

const individualUsesAnnualPackage = computed(
    () => individualAnnual.fee_plan_id !== '',
);

const individualNonAnnualEstimate = computed(() =>
    estimateNonAnnualFee(
        individualCourseIds.value,
        individualPayCycle.value,
        individualMonthKeys.value,
    ),
);

const individualMonthOptions = computed(() => {
    if (!individualStartDate.value) {
        return [] as Array<{ key: string; label: string }>;
    }
    return billingMonthOptions(individualStartDate.value, 18).map((m) => ({
        key: monthKey(m.y, m.m),
        label: `${m.y}/${m.m}`,
    }));
});

const syncIndividualNonAnnualFee = () => {
    if (individualUsesAnnualPackage.value || individualAnnual.next_fee_manual) {
        return;
    }
    individualAnnual.next_fee = String(individualNonAnnualEstimate.value.total);
};

const toggleIndividualMonth = (key: string) => {
    if (individualMonthKeys.value.includes(key)) {
        individualMonthKeys.value = individualMonthKeys.value.filter(
            (k) => k !== key,
        );
    } else {
        individualMonthKeys.value = [...individualMonthKeys.value, key].sort();
    }
    if (!individualAnnual.next_fee_manual) {
        syncIndividualNonAnnualFee();
    }
};

const onIndividualPayCycleChange = () => {
    individualAnnual.next_fee_manual = false;
    syncIndividualNonAnnualMonths();
    syncIndividualNonAnnualFee();
};

const buildAnnualPayloadFromSelection = (
    studentId: number,
    sel: AnnualSelectionRow,
    extras?: {
        pay_cycle?: string | null;
        course_months?: string[] | null;
    },
) => {
    const raw = sel.fee_plan_id;
    const planId = raw === '' ? null : Number(raw);
    if (planId === null) {
        return {
            student_id: studentId,
            fee_plan_id: null,
            deposit: null,
            next_fee: parseMoney(sel.next_fee) || null,
            pay_cycle: extras?.pay_cycle ?? null,
            course_months: extras?.course_months ?? null,
            note: (sel.note ?? '').trim(),
        };
    }

    return {
        student_id: studentId,
        fee_plan_id: planId,
        deposit: parseMoney(sel.deposit),
        next_fee: parseMoney(sel.next_fee),
        pay_cycle: null,
        course_months: null,
        note: (sel.note ?? '').trim(),
    };
};

const buildAnnualPayload = (rows: PreviewRow[]) =>
    rows.map((row) =>
        buildAnnualPayloadFromSelection(
            row.id,
            annualSelections[row.id] ?? emptyAnnualSelection(),
        ),
    );

const individualSelectedPackage = computed(() =>
    findPackage(individualAnnual.fee_plan_id),
);

const individualPackageTotal = computed(
    () => individualSelectedPackage.value?.total ?? 0,
);

const individualDepositAmount = computed(() =>
    individualSelectedPackage.value ? parseMoney(individualAnnual.deposit) : 0,
);

const individualBalance = computed(() =>
    Math.max(
        0,
        individualPackageTotal.value - individualDepositAmount.value,
    ),
);

const individualNextFeeAmount = computed(() => {
    if (individualUsesAnnualPackage.value) {
        return parseMoney(individualAnnual.next_fee);
    }
    // 不年繳：以欄位為準；若尚未同步則用學費＋教材試算加總
    const typed = parseMoney(individualAnnual.next_fee);
    if (individualAnnual.next_fee_manual || typed > 0) {
        return typed;
    }
    return individualNonAnnualEstimate.value.total;
});

const individualPackageMatch = computed(() => {
    const pack = individualSelectedPackage.value;
    if (!pack) {
        return true;
    }
    return packageMatchesCourses(pack, individualCourseIds.value);
});

const matchingAnnualPackages = computed(() =>
    annualPackages.value.filter((pack) =>
        packageMatchesCourses(pack, individualCourseIds.value),
    ),
);

const openIndividual = (row: PreviewRow) => {
    if (!fromGrade.value || !toTarget.value) {
        window.alert('請先選擇目前年級與新的年級（或已畢業）。');
        return;
    }
    individualRow.value = row;
    individualError.value = '';
    resetIndividualAnnual();
    if (isGraduate() || row.action === 'graduate') {
        individualCourseIds.value = [];
    } else {
        // 預選可延續科目；可點掉國二有、國三不修的課，也可加選新年級科目
        individualCourseIds.value = [...(row.transferable_ids ?? [])];
        if (needsAnnualDialog.value) {
            syncIndividualNonAnnualMonths();
            syncIndividualNonAnnualFee();
        }
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
    if (!individualUsesAnnualPackage.value) {
        syncIndividualNonAnnualFee();
    }
};

watch(matchingAnnualPackages, (packs) => {
    if (!individualOpen.value || !needsAnnualDialog.value) {
        return;
    }
    if (individualAnnual.fee_plan_id !== '') {
        return;
    }
    if (packs.length === 1) {
        individualAnnual.fee_plan_id = String(packs[0].fee_plan_id);
        onAnnualPlanChange(individualAnnual);
    }
});

const postPromotion = (opts: {
    studentIds: number[];
    graduate: boolean;
    courseSelections: Array<{ student_id: number; course_ids: number[] }>;
    annualSelections: Array<{
        student_id: number;
        fee_plan_id: number | null;
        deposit: number | null;
        next_fee: number | null;
        pay_cycle: string | null;
        course_months: string[] | null;
        note: string;
    }>;
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
}) => {
    form.from_grade_level_id = fromGrade.value;
    form.to_grade_level_id = opts.graduate ? null : toTarget.value;
    form.graduate = opts.graduate;
    form.from_academic_year_id = fromYear.value || null;
    form.status = status.value;
    form.student_ids = opts.studentIds;
    form.course_selections = opts.courseSelections;
    form.annual_selections = opts.annualSelections;
    form.post('/student-promotions', {
        preserveScroll: true,
        onSuccess: opts.onSuccess,
        onError: (errors) => opts.onError?.(errors as Record<string, string>),
    });
};

const openAnnualDialog = (rows: PreviewRow[]) => {
    annualRows.value = rows;
    initAnnualSelections(rows);
    annualOpen.value = true;
};

const confirmAnnualSubmit = () => {
    const rows = annualRows.value;
    if (rows.length === 0) {
        return;
    }

    postPromotion({
        studentIds: rows.map((r) => r.id),
        graduate: false,
        courseSelections: selectedManualRows.value
            .filter((row) => rows.some((r) => r.id === row.id))
            .map((row) => ({
                student_id: row.id,
                course_ids: manualCourses[row.id] ?? [],
            })),
        annualSelections: buildAnnualPayload(rows),
        onSuccess: () => {
            annualOpen.value = false;
        },
    });
};

const submitIndividual = () => {
    const row = individualRow.value;
    if (!row || !fromGrade.value || !toTarget.value) {
        return;
    }

    const graduate = isGraduate() || row.action === 'graduate';
    if (!graduate && individualCourseIds.value.length === 0) {
        individualError.value = `請選擇${toGradeOption.value?.name ?? '新年級'}要修的課程（可保留原有科目並點掉不修的科目）。`;
        return;
    }

    const fromName = fromGradeOption.value?.name ?? '';
    const toName = graduate
        ? '已畢業'
        : (toGradeOption.value?.name ?? '');
    const annualPayload =
        !graduate && needsAnnualDialog.value
            ? [
                  buildAnnualPayloadFromSelection(row.id, individualAnnual, {
                      pay_cycle: individualUsesAnnualPackage.value
                          ? null
                          : individualPayCycle.value,
                      course_months: individualUsesAnnualPackage.value
                          ? null
                          : [...individualMonthKeys.value],
                  }),
              ]
            : [];

    const confirmMsg = graduate
        ? `確定將「${row.name}」標記為「已畢業」？`
        : needsAnnualDialog.value
          ? `確定將「${row.name}」由「${fromName}」個別轉至「${toName}」，並登記年繳意向？（不立刻開帳）`
          : `確定將「${row.name}」由「${fromName}」個別轉至「${toName}」？\n將依你勾選的課程設定${toName}要修科目。`;

    if (!window.confirm(confirmMsg)) {
        return;
    }

    postPromotion({
        studentIds: [row.id],
        graduate,
        courseSelections: graduate
            ? []
            : [
                  {
                      student_id: row.id,
                      course_ids: [...individualCourseIds.value],
                  },
              ],
        annualSelections: annualPayload,
        onSuccess: () => {
            individualOpen.value = false;
            individualRow.value = null;
            resetIndividualAnnual();
        },
        onError: (errors) => {
            const key = `course_selections.${row.id}`;
            individualError.value =
                errors[key] ??
                errors[`annual_selections.${row.id}`] ??
                errors.student_ids ??
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

    const selectedRows = props.preview.filter((r) =>
        selectedIds.value.includes(r.id),
    );

    if (!isGraduate() && needsAnnualDialog.value) {
        openAnnualDialog(selectedRows);
        return;
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

    postPromotion({
        studentIds: selectedIds.value,
        graduate: isGraduate(),
        courseSelections: selectedManualRows.value.map((row) => ({
            student_id: row.id,
            course_ids: manualCourses[row.id] ?? [],
        })),
        annualSelections: [],
    });
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
            description="批次轉檔：科目不變可自動帶課；科目有變請勾選後於上方選課。國二升國三時會跳出視窗登記年繳意向。右側「個別轉檔」可為單人調整課程。國三轉已畢業。"
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
                    if (!v) {
                        individualRow = null;
                        resetIndividualAnnual();
                    }
                }
            "
        >
            <DialogContent
                :class="
                    needsAnnualDialog &&
                    individualRow &&
                    !(isGraduate() || individualRow.action === 'graduate')
                        ? 'max-h-[92vh] overflow-y-auto sm:max-w-3xl'
                        : 'sm:max-w-lg'
                "
            >
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

                <div
                    v-else-if="individualRow"
                    class="grid gap-4"
                    :class="
                        needsAnnualDialog
                            ? 'lg:grid-cols-[minmax(0,1.2fr)_minmax(16rem,0.9fr)]'
                            : ''
                    "
                >
                    <div class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            請勾選升上「{{
                                toGradeOption?.name ?? '新年級'
                            }}」後要修的課。國二有修、國三不修可點掉；也可加選新科目。
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
                            新年級尚無可選課程，請先至收費設定／課程確認適用年級。
                        </p>

                        <template v-if="needsAnnualDialog">
                            <div class="grid gap-2 border-t pt-3 sm:grid-cols-2">
                                <div class="grid gap-1 sm:col-span-2">
                                    <Label for="ind-annual-plan">年繳方案</Label>
                                    <select
                                        id="ind-annual-plan"
                                        v-model="individualAnnual.fee_plan_id"
                                        class="h-9 rounded-md border bg-background px-3 text-sm"
                                        @change="
                                            onAnnualPlanChange(individualAnnual)
                                        "
                                    >
                                        <option value="">不年繳</option>
                                        <option
                                            v-for="pack in annualPackages"
                                            :key="pack.fee_plan_id"
                                            :value="String(pack.fee_plan_id)"
                                        >
                                            {{ packageOptionLabel(pack)
                                            }}{{
                                                packageMatchesCourses(
                                                    pack,
                                                    individualCourseIds,
                                                )
                                                    ? '｜科目相符'
                                                    : ''
                                            }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="
                                            individualSelectedPackage &&
                                            !individualPackageMatch
                                        "
                                        class="text-xs text-amber-700"
                                    >
                                        目前勾選科目與方案科目不完全相同；仍可登記意向，之後收款再調整。
                                    </p>
                                </div>

                                <template v-if="!individualUsesAnnualPackage">
                                    <div class="grid gap-1 sm:col-span-2">
                                        <Label>繳別</Label>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="opt in [
                                                    {
                                                        value: 'monthly',
                                                        label: '月繳（定價）',
                                                    },
                                                    {
                                                        value: 'quarterly',
                                                        label: '季繳',
                                                    },
                                                    {
                                                        value: 'annual',
                                                        label: '年繳',
                                                    },
                                                ]"
                                                :key="opt.value"
                                                type="button"
                                                class="rounded-md border px-3 py-1.5 text-sm"
                                                :class="
                                                    individualPayCycle ===
                                                    opt.value
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-border text-muted-foreground hover:border-primary/40'
                                                "
                                                @click="
                                                    individualPayCycle =
                                                        opt.value as
                                                            | 'monthly'
                                                            | 'quarterly'
                                                            | 'annual';
                                                    onIndividualPayCycleChange();
                                                "
                                            >
                                                {{ opt.label }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid gap-1 sm:col-span-2">
                                        <Label for="ind-start-date"
                                            >帳期起算</Label
                                        >
                                        <input
                                            id="ind-start-date"
                                            v-model="individualStartDate"
                                            type="date"
                                            class="h-9 rounded-md border bg-background px-3 text-sm"
                                            @change="
                                                individualAnnual.next_fee_manual = false;
                                                syncIndividualNonAnnualMonths();
                                                syncIndividualNonAnnualFee();
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-1 sm:col-span-2">
                                        <Label>帳期月份</Label>
                                        <div
                                            class="grid grid-cols-3 gap-1.5 sm:grid-cols-4"
                                        >
                                            <label
                                                v-for="opt in individualMonthOptions"
                                                :key="opt.key"
                                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border px-2 py-1.5 text-xs"
                                                :class="
                                                    individualMonthKeys.includes(
                                                        opt.key,
                                                    )
                                                        ? 'border-sky-500 bg-sky-50 text-sky-950'
                                                        : 'border-border text-muted-foreground'
                                                "
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="size-3.5 accent-sky-600"
                                                    :checked="
                                                        individualMonthKeys.includes(
                                                            opt.key,
                                                        )
                                                    "
                                                    @change="
                                                        toggleIndividualMonth(
                                                            opt.key,
                                                        )
                                                    "
                                                />
                                                {{ opt.label }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="grid gap-1">
                                        <Label for="ind-next-fee-na"
                                            >下次應收</Label
                                        >
                                        <input
                                            id="ind-next-fee-na"
                                            v-model="individualAnnual.next_fee"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="h-9 rounded-md border bg-background px-3 text-sm tabular-nums"
                                            @input="
                                                onAnnualNextFeeInput(
                                                    individualAnnual,
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-1 sm:col-span-2">
                                        <Label for="ind-annual-note-na"
                                            >備註（選填）</Label
                                        >
                                        <input
                                            id="ind-annual-note-na"
                                            v-model="individualAnnual.note"
                                            type="text"
                                            maxlength="500"
                                            class="h-9 rounded-md border bg-background px-3 text-sm"
                                            placeholder="例：國三不修數學"
                                        />
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="grid gap-1">
                                        <Label for="ind-deposit">訂金</Label>
                                        <input
                                            id="ind-deposit"
                                            v-model="individualAnnual.deposit"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="h-9 rounded-md border bg-background px-3 text-sm tabular-nums"
                                            @input="
                                                onAnnualDepositChange(
                                                    individualAnnual,
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-1">
                                        <Label for="ind-next-fee"
                                            >下次應收</Label
                                        >
                                        <input
                                            id="ind-next-fee"
                                            v-model="individualAnnual.next_fee"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="h-9 rounded-md border bg-background px-3 text-sm tabular-nums"
                                            @input="
                                                onAnnualNextFeeInput(
                                                    individualAnnual,
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-1 sm:col-span-2">
                                        <Label for="ind-annual-note"
                                            >備註（選填）</Label
                                        >
                                        <input
                                            id="ind-annual-note"
                                            v-model="individualAnnual.note"
                                            type="text"
                                            maxlength="500"
                                            class="h-9 rounded-md border bg-background px-3 text-sm"
                                            placeholder="例：家長已付訂金／確認全科年繳"
                                        />
                                    </div>
                                </template>
                            </div>
                        </template>

                        <p v-if="individualError" class="text-xs text-destructive">
                            {{ individualError }}
                        </p>
                        <p
                            v-if="
                                form.errors[
                                    `course_selections.${individualRow.id}`
                                ]
                            "
                            class="text-xs text-destructive"
                        >
                            {{
                                form.errors[
                                    `course_selections.${individualRow.id}`
                                ]
                            }}
                        </p>
                    </div>

                    <aside
                        v-if="needsAnnualDialog"
                        class="rounded-xl border border-primary/25 bg-accent/40 p-4"
                    >
                        <h3 class="text-base font-semibold text-primary">
                            試算
                        </h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            只登記意向，不會立刻開帳。之後在「新增收款」再正式套用。
                        </p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-2">
                                <dt class="text-muted-foreground">已選科目</dt>
                                <dd>{{ individualCourseIds.length }} 科</dd>
                            </div>
                            <template v-if="individualSelectedPackage">
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted-foreground">
                                        方案總額
                                    </dt>
                                    <dd class="tabular-nums">
                                        ${{
                                            individualPackageTotal.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted-foreground">訂金</dt>
                                    <dd class="tabular-nums">
                                        ${{
                                            individualDepositAmount.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted-foreground">
                                        餘額
                                    </dt>
                                    <dd class="tabular-nums">
                                        ${{
                                            individualBalance.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted-foreground">繳別</dt>
                                    <dd>
                                        {{
                                            individualPayCycle === 'monthly'
                                                ? '月繳'
                                                : individualPayCycle ===
                                                    'annual'
                                                  ? '年繳'
                                                  : '季繳'
                                        }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted-foreground">
                                        帳期月數
                                    </dt>
                                    <dd>
                                        {{
                                            individualNonAnnualEstimate.months
                                                .length
                                        }}
                                        個月
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted-foreground">學費</dt>
                                    <dd class="tabular-nums">
                                        ${{
                                            individualNonAnnualEstimate.tuition.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-muted-foreground">教材</dt>
                                    <dd class="tabular-nums">
                                        ${{
                                            individualNonAnnualEstimate.material.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                            </template>
                            <div
                                class="flex justify-between gap-2 border-t pt-2 text-base font-semibold"
                            >
                                <dt>下次應收</dt>
                                <dd class="tabular-nums text-primary">
                                    ${{
                                        individualNextFeeAmount.toLocaleString()
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </aside>
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
                        {{
                            needsAnnualDialog
                                ? '確認轉檔並登記意向'
                                : '確認個別轉檔'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="annualOpen"
            @update:open="
                (v) => {
                    annualOpen = v;
                    if (!v) annualRows = [];
                }
            "
        >
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>國二 → 國三：登記年繳</DialogTitle>
                    <DialogDescription>
                        先記下每位學生是否走年繳方案（只登記意向，不會立刻開帳）。之後在「新增收款」再正式套用。
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="annualPackages.length === 0"
                    class="rounded-md border border-amber-200 bg-amber-50/60 px-3 py-2 text-sm text-amber-900"
                >
                    尚無可用的國三年繳方案。請先至收費設定新增「年繳方案」，或全部選「不年繳」後繼續轉檔。
                </div>

                <div class="space-y-3">
                    <div
                        v-for="row in annualRows"
                        :key="`annual-${row.id}`"
                        class="rounded-lg border border-border bg-card p-3"
                    >
                        <div
                            class="mb-2 flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <p class="text-sm font-medium">
                                {{ row.name }}
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ row.student_code }} →
                                    {{ row.new_student_code ?? '—' }}
                                </span>
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="grid gap-1 sm:col-span-2">
                                <Label :for="`annual-plan-${row.id}`"
                                    >年繳方案</Label
                                >
                                <select
                                    :id="`annual-plan-${row.id}`"
                                    v-model="
                                        annualSelections[row.id].fee_plan_id
                                    "
                                    class="h-9 rounded-md border bg-background px-3 text-sm"
                                    @change="
                                        onAnnualPlanChange(
                                            annualSelections[row.id],
                                        )
                                    "
                                >
                                    <option value="">不年繳</option>
                                    <option
                                        v-for="pack in annualPackages"
                                        :key="pack.fee_plan_id"
                                        :value="String(pack.fee_plan_id)"
                                    >
                                        {{ packageOptionLabel(pack) }}
                                    </option>
                                </select>
                            </div>
                            <template
                                v-if="
                                    annualSelections[row.id].fee_plan_id !== ''
                                "
                            >
                                <div class="grid gap-1">
                                    <Label :for="`annual-deposit-${row.id}`"
                                        >訂金</Label
                                    >
                                    <input
                                        :id="`annual-deposit-${row.id}`"
                                        v-model="
                                            annualSelections[row.id].deposit
                                        "
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="h-9 rounded-md border bg-background px-3 text-sm tabular-nums"
                                        @input="
                                            onAnnualDepositChange(
                                                annualSelections[row.id],
                                            )
                                        "
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label :for="`annual-next-${row.id}`"
                                        >下次應收</Label
                                    >
                                    <input
                                        :id="`annual-next-${row.id}`"
                                        v-model="
                                            annualSelections[row.id].next_fee
                                        "
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="h-9 rounded-md border bg-background px-3 text-sm tabular-nums"
                                        @input="
                                            onAnnualNextFeeInput(
                                                annualSelections[row.id],
                                            )
                                        "
                                    />
                                </div>
                            </template>
                            <div class="grid gap-1 sm:col-span-2">
                                <Label :for="`annual-note-${row.id}`"
                                    >備註（選填）</Label
                                >
                                <input
                                    :id="`annual-note-${row.id}`"
                                    v-model="annualSelections[row.id].note"
                                    type="text"
                                    maxlength="500"
                                    class="h-9 rounded-md border bg-background px-3 text-sm"
                                    placeholder="例：家長已付訂金／確認全科年繳"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="annualOpen = false"
                    >
                        返回
                    </Button>
                    <Button
                        type="button"
                        :disabled="form.processing || annualRows.length === 0"
                        @click="confirmAnnualSubmit"
                    >
                        確認轉檔並登記年繳
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
