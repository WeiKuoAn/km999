<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    classroomCalendarSurface,
    normalizeClassroomHex,
} from '@/lib/classroomColor';
import { studentStatusLabel, studentStatusPillClass } from '@/lib/studentStatus';
import {
    billingBaselineSessions,
    billingMonthOptions,
    buildDefaultSessionEntries,
    countSessionsForCourse,
    defaultBillingMonths,
    formatWeekdays,
    monthKey,
    monthsFromDates,
    parseMonthKey,
    proratedMonthTuitionExact,
    uniqueDatesFromSessions
    
    
} from '@/lib/weekdayDates';
import type {SessionEntry, YearMonth} from '@/lib/weekdayDates';

type Subject = {
    id: number;
    name: string;
    color: string | null;
    category: string | null;
    pricing_group: string;
    pricing_group_label: string;
    unit: string;
    session_block_size?: number | null;
    list: number;
    q_single: number;
    material: number;
    material_unit: string;
    weekdays: number[];
    start_date?: string | null;
    end_date?: string | null;
    fee_plan_id: number | null;
    group_name: string | null;
};

type StudentInfo = {
    id: number;
    student_code: string | null;
    name: string;
    grade_name: string | null;
    grade_code?: number | null;
    grade_level_id?: number | null;
    academic_year_name: string | null;
};

type StudentOption = {
    id: number;
    student_code: string | null;
    name: string;
    grade_name: string | null;
    status: string;
};

type EditContext = {
    from: string;
    to: string;
    period_label: string;
    month_keys: string[];
    course_months?: Record<string, string[]>;
    receipt_no: string | null;
    fee_discount_id: number | null;
    allowance: number;
    material_halves: Array<{
        course_id: number;
        period_year: number;
        period_half: string;
        amount: number;
    }>;
};

type GradeStudent = {
    id: number;
    student_code: string | null;
    name: string;
    status: string;
    paid_through: string | null;
};

const props = defineProps<{
    edit?: EditContext | null;
    grade_levels?: Array<{ id: number; name: string }>;
    grade_filter?: number | null;
    grade_students?: GradeStudent[];
    return_grade_level_id?: number | null;
    student: StudentInfo | null;
    subjects: Subject[];
    warnings: string[];
    has_prior_payments?: boolean;
    suggested_start_date?: string | null;
    suggested_course_ids?: number[];
    suggested_pay_cycle?: 'monthly' | 'quarterly' | 'annual' | null;
    suggested_course_months?: Record<string, string[]> | null;
    enrolled_course_ids?: number[];
    bundles?: Array<{
        id: number;
        name: string;
        min_courses: number;
        price: number;
        course_ids: number[];
    }>;
    material_status?: Array<{
        course_id: number;
        can_charge: boolean;
        amount: number;
        period_year: number;
        period_half: string;
        period_label: string;
        months_label?: string;
        paid_at: string | null;
        note: string | null;
    }>;
    fee_discounts?: Array<{
        id: number;
        name: string;
        type: 'amount' | 'percent';
        value: number;
        label: string;
    }>;
    grade9_package?: {
        eligible: boolean;
        required_course_ids: number[];
        required_count: number;
        total: number;
        monthly_total: number;
        months_label: string;
        reason: string | null;
    } | null;
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);

const hasPriorPayments = computed(() => !!props.has_prior_payments);
const suggestedStartDate = computed(() => props.suggested_start_date ?? null);

const defaultCourseIds = (): number[] => {
    const suggested = (props.suggested_course_ids ?? []).filter((id) =>
        props.subjects.some((s) => s.id === id),
    );

    if (suggested.length > 0) {
        return [...suggested];
    }

    // 全新報名：預選最多兩門已設定價目的核心科
    return props.subjects
        .filter((s) => s.fee_plan_id !== null && s.pricing_group === 'core')
        .slice(0, 2)
        .map((s) => s.id);
};

const defaultPayCycle = (): 'monthly' | 'quarterly' | 'annual' => {
    const cycle = props.suggested_pay_cycle;

    if (cycle === 'monthly' || cycle === 'quarterly' || cycle === 'annual') {
        return cycle;
    }

    return 'quarterly';
};

const query = ref(
    props.student
        ? `${props.student.student_code ? `${props.student.student_code} ` : ''}${props.student.name}`.trim()
        : '',
);
const results = ref<StudentOption[]>([]);
const loading = ref(false);
const open = ref(false);
const searchError = ref('');
let timer: ReturnType<typeof setTimeout> | null = null;
let abortController: AbortController | null = null;

const selected = ref<number[]>(defaultCourseIds());
const payCycle = ref<'monthly' | 'quarterly' | 'annual'>(defaultPayCycle());
const editContext = computed(() => props.edit ?? null);
const isEditMode = computed(() => editContext.value !== null);

const editBackHref = computed(() => {
    const edit = editContext.value;

    if (!edit && props.return_grade_level_id) {
        return `/student-payments/create?grade_level_id=${props.return_grade_level_id}`;
    }

    if (!edit || !props.student) {
        return '/student-payments';
    }

    const from = parseMonthKey(edit.from);
    const to = parseMonthKey(edit.to);

    if (!from || !to) {
        return `/student-payments/${props.student.id}`;
    }

    return `/student-payments/${props.student.id}?from_year=${from.y}&from_month=${from.m}&to_year=${to.y}&to_month=${to.m}`;
});

const initialDiscountId = (): number | null => {
    const id = props.edit?.fee_discount_id ?? null;

    return id !== null && (props.fee_discounts ?? []).some((d) => d.id === id)
        ? id
        : null;
};

const allowance = ref(props.edit?.allowance ?? 0);
const selectedDiscountId = ref<number | null>(initialDiscountId());
const receiptNo = ref(props.edit?.receipt_no ?? '');
const startDate = ref(
    hasPriorPayments.value && suggestedStartDate.value
        ? suggestedStartDate.value
        : new Date().toISOString().slice(0, 10),
);
const sessions = ref<SessionEntry[]>([]);
/** 帳期月份勾選（例：8 月不足 + 9–11 季繳連續） */
const selectedMonthKeys = ref<string[]>([]);
/** 各科分開勾帳期月份；開啟時 selectedMonthKeys 為各科月份聯集 */
const splitByCourse = ref(false);
const courseMonthKeys = ref<Record<number, string[]>>({});
/** 教材費大總覽（勾了才展開各科半年） */
const chargeMaterialMaster = ref(false);
/** 耗材 class_day 科目 id */
const chargeConsumableIds = ref<number[]>([]);
/** 各科半年勾選與金額：key = `${courseId}-${year}-H1|H2` */
const materialHalfState = ref<
    Record<string, { checked: boolean; amount: number }>
>({});
/** 使用者手動勾／取消過的半年（不再被自動預設覆蓋） */
const materialManualKeys = ref<Set<string>>(new Set());

type MaterialStatusRow = NonNullable<typeof props.material_status>[number];
type HalfPeriod = { year: number; half: 'H1' | 'H2' };

const materialStatusRows = computed(() => props.material_status ?? []);

const halfKey = (courseId: number, year: number, half: string) =>
    `${courseId}-${year}-${half}`;

const materialStatusByKey = computed(() => {
    const map = new Map<string, MaterialStatusRow>();

    for (const row of materialStatusRows.value) {
        map.set(halfKey(row.course_id, row.period_year, row.period_half), row);
    }

    return map;
});

const materialPeriodYear = computed(() => {
    if (startDate.value && startDate.value.length >= 4) {
        return Number(startDate.value.slice(0, 4));
    }

    return new Date().getFullYear();
});

const suggestedHalfFromStart = computed(() => {
    if (!startDate.value || startDate.value.length < 7) {
        return 'H2';
    }

    const month = Number(startDate.value.slice(5, 7));

    return month <= 6 ? 'H1' : 'H2';
});

const selectedYearMonths = computed(() =>
    selectedMonthKeys.value
        .map((key) => key.split('-').map(Number))
        .filter(([y, m]) => y > 0 && m > 0)
        .map(([y, m]) => ({ y, m }))
        .sort((a, b) => a.y * 12 + a.m - (b.y * 12 + b.m)),
);

/** 起算年上／下半年＋帳期跨到的其他半年（例：2026/12–2027/2 → 多一個 2027 上半年） */
const materialPeriods = computed<HalfPeriod[]>(() => {
    const year = materialPeriodYear.value;
    const periods: HalfPeriod[] = [
        { year, half: 'H1' },
        { year, half: 'H2' },
    ];

    for (const { y, m } of selectedYearMonths.value) {
        const half = m <= 6 ? 'H1' : 'H2';

        if (!periods.some((p) => p.year === y && p.half === half)) {
            periods.push({ year: y, half });
        }
    }

    return periods.sort(
        (a, b) => a.year * 2 + (a.half === 'H1' ? 0 : 1) - (b.year * 2 + (b.half === 'H1' ? 0 : 1)),
    );
});

/** 該科帳期月份（分科模式用各科自己的月份） */
const courseYearMonths = (courseId: number) =>
    splitByCourse.value && courseMonthKeys.value[courseId]
        ? courseMonthKeys.value[courseId]
              .map((key) => key.split('-').map(Number))
              .map(([y, m]) => ({ y, m }))
        : selectedYearMonths.value;

/** 該科帳期含 1 月或 7 月，或為起算月所屬半年 → 預設要收 */
const isHalfSuggested = (period: HalfPeriod, courseId: number) => {
    const startMonth = period.half === 'H1' ? 1 : 7;

    if (
        courseYearMonths(courseId).some(
            ({ y, m }) => y === period.year && m === startMonth,
        )
    ) {
        return true;
    }

    return (
        period.year === materialPeriodYear.value &&
        period.half === suggestedHalfFromStart.value
    );
};

/** 教材年費 ÷ 2；1–6、7–12 各收一次（需打勾） */
const semiAnnualMaterialFee = (annualOrTermFee: number) =>
    annualOrTermFee > 0 ? Math.round(annualOrTermFee / 2) : 0;

const syncDefaultMaterialChecks = () => {
    if (isGrade9PackageMode.value) {
        chargeMaterialMaster.value = false;
        chargeConsumableIds.value = [];
        materialHalfState.value = {};

        return;
    }

    const consumables: number[] = [];
    const nextHalf: Record<string, { checked: boolean; amount: number }> = {
        ...materialHalfState.value,
    };
    let autoAdded = false;

    for (const id of selected.value) {
        const s = props.subjects.find((x) => x.id === id);

        if (!s || !s.material) {
            continue;
        }

        if (s.material_unit === 'class_day') {
            consumables.push(id);
            continue;
        }

        const semi = semiAnnualMaterialFee(s.material);

        for (const period of materialPeriods.value) {
            const key = halfKey(id, period.year, period.half);
            const status = materialStatusByKey.value.get(key);
            const locked = !!(status && !status.can_charge);
            const prev = nextHalf[key];
            const checked = locked
                ? false
                : props.edit || materialManualKeys.value.has(key)
                  ? !!prev?.checked
                  : isHalfSuggested(period, id);

            if (checked && !prev?.checked) {
                autoAdded = true;
            }

            nextHalf[key] = {
                checked,
                amount:
                    prev?.amount && prev.amount > 0
                        ? prev.amount
                        : (status?.amount ?? semi),
            };
        }
    }

    // 清掉已不在選課或已不在帳期內的半年
    for (const key of Object.keys(nextHalf)) {
        const [courseId, year, half] = key.split('-');

        if (
            !selected.value.includes(Number(courseId)) ||
            !materialPeriods.value.some(
                (p) => p.year === Number(year) && p.half === half,
            )
        ) {
            delete nextHalf[key];
        }
    }

    chargeConsumableIds.value = consumables;
    materialHalfState.value = nextHalf;

    if (autoAdded) {
        chargeMaterialMaster.value = true;
    }
};

/** 依繳別決定預選／可瀏覽堂次月數 */
const sessionMonthSpan = computed(() => {
    if (isGrade9PackageMode.value) {
        return 6;
    }

    switch (payCycle.value) {
        case 'monthly':
            return 1;
        case 'annual':
            return 12;
        default:
            return 3;
    }
});

const grade9Package = computed(() => props.grade9_package ?? null);

const packageRequiredIds = computed(
    () => grade9Package.value?.required_course_ids ?? [],
);

const isGrade9PackageMode = computed(() => {
    const meta = grade9Package.value;

    if (!meta?.eligible || payCycle.value !== 'annual') {
        return false;
    }

    if (!startDate.value || startDate.value.length < 7) {
        return false;
    }

    const month = Number(startDate.value.slice(5, 7));

    if (month !== 7) {
        return false;
    }

    const required = [...meta.required_course_ids].sort((a, b) => a - b);
    const sel = [...selected.value].sort((a, b) => a - b);

    return (
        required.length > 0 &&
        required.length === sel.length &&
        required.every((id, i) => id === sel[i])
    );
});

const packageAlmostReady = computed(() => {
    const meta = grade9Package.value;

    if (!meta?.eligible) {
        return false;
    }

    return !isGrade9PackageMode.value;
});

/** 續繳且帳期起算非 7 月時不可硬改為方案 */
const canApplyGrade9Package = computed(() => {
    if (!grade9Package.value?.eligible) {
        return false;
    }

    if (!hasPriorPayments.value) {
        return true;
    }

    const suggested = suggestedStartDate.value;

    if (!suggested || suggested.length < 7) {
        return true;
    }

    return Number(suggested.slice(5, 7)) === 7;
});

const applyGrade9Package = () => {
    const meta = grade9Package.value;

    if (!meta?.eligible || meta.required_course_ids.length === 0) {
        return;
    }

    if (!canApplyGrade9Package.value) {
        return;
    }

    const year =
        hasPriorPayments.value && suggestedStartDate.value
            ? Number(suggestedStartDate.value.slice(0, 4))
            : startDate.value
              ? Number(startDate.value.slice(0, 4))
              : new Date().getFullYear();
    selected.value = [...meta.required_course_ids];
    payCycle.value = 'annual';
    startDate.value =
        hasPriorPayments.value && suggestedStartDate.value
            ? suggestedStartDate.value
            : `${year}-07-01`;
    selectedDiscountId.value = null;
    allowance.value = 0;
    chargeMaterialMaster.value = false;
    chargeConsumableIds.value = [];
    materialHalfState.value = {};
    syncSuggestedMonths();
    refillSessionDates();
};

/** 國三方案：每月 2 萬均攤各科 */
const packageShareForCourse = (index: number, count: number) => {
    const monthly = grade9Package.value?.monthly_total ?? 20000;
    const base = Math.floor(monthly / count);
    const rem = monthly % count;

    return base + (index < rem ? 1 : 0);
};

const packageMonthBreakdown = computed(() => {
    if (!isGrade9PackageMode.value || !startDate.value) {
        return [] as Array<{
            y: number;
            m: number;
            label: string;
            tuition: number;
            material: number;
            subtotal: number;
            prorateHints: string[];
            materialHalfHint: string | null;
        }>;
    }

    const year = Number(startDate.value.slice(0, 4));
    const ids = [...selected.value];
    const n = ids.length;
    const monthly = grade9Package.value?.monthly_total ?? 20000;

    return [7, 8, 9, 10, 11, 12].map((m) => ({
        y: year,
        m,
        label: `${year}/${m}`,
        tuition: monthly,
        material: 0,
        subtotal: monthly,
        prorateHints: ids.map((id, index) => {
            const s = props.subjects.find((x) => x.id === id);

            return `${s?.name ?? id} ${packageShareForCourse(index, n).toLocaleString()}`;
        }),
        materialHalfHint: null,
    }));
});

const selectedMonths = computed((): YearMonth[] =>
    selectedMonthKeys.value
        .map((key) => parseMonthKey(key))
        .filter((m): m is YearMonth => m !== null)
        .sort((a, b) => a.y - b.y || a.m - b.m),
);

/** 暫時開放補登：帳期月份最早可選到此日所在月份 */
const BACKFILL_FROM = '2026-07-01';

const monthCheckboxOptions = computed(() => {
    if (!startDate.value) {
        return [];
    }

    const from = startDate.value < BACKFILL_FROM ? startDate.value : BACKFILL_FROM;
    const extra =
        (Number(startDate.value.slice(0, 4)) - Number(from.slice(0, 4))) * 12 +
        (Number(startDate.value.slice(5, 7)) - Number(from.slice(5, 7)));

    return billingMonthOptions(from, 12 + extra).map((m) => ({
        key: monthKey(m.y, m.m),
        label: `${m.m}月`,
        yearLabel: `${m.y}/${m.m}`,
        y: m.y,
        m: m.m,
    }));
});

/** 勾選早於起算日的月份時，起算日會前移；前移後保留使用者勾選的月份 */
let pendingMonthKeys: string[] | null = null;
let pendingCourseMonths: Record<number, string[]> | null = null;

const monthKeyIndex = (key: string) => {
    const ym = parseMonthKey(key);

    return ym ? ym.y * 12 + ym.m : 0;
};

const sortMonthKeys = (keys: string[]) =>
    [...new Set(keys)]
        .filter((key) => monthKeyIndex(key) > 0)
        .sort((a, b) => monthKeyIndex(a) - monthKeyIndex(b));

/** 寫入各科月份，並把聯集同步到 selectedMonthKeys；新加入的科目沿用目前全部月份 */
const applyCourseMonths = (next: Record<number, string[]>) => {
    const cleaned: Record<number, string[]> = {};

    for (const id of selected.value) {
        const keys = sortMonthKeys(next[id] ?? selectedMonthKeys.value);

        if (keys.length > 0) {
            cleaned[id] = keys;
        }
    }

    courseMonthKeys.value = cleaned;
    selectedMonthKeys.value = sortMonthKeys(Object.values(cleaned).flat());
};

/** 編輯或建議帶入的各科月份不一致時，自動開啟分科模式 */
const splitSource = (): Record<number, string[]> | null => {
    const source = props.edit?.course_months ?? props.suggested_course_months;

    if (!source) {
        return null;
    }

    const entries = Object.entries(source)
        .map(([id, keys]) => [Number(id), sortMonthKeys(keys)] as const)
        .filter(([id, keys]) => selected.value.includes(id) && keys.length > 0);

    if (entries.length < 2) {
        return null;
    }

    const first = entries[0][1].join(',');

    if (entries.every(([, keys]) => keys.join(',') === first)) {
        return null;
    }

    return Object.fromEntries(entries);
};

const syncSuggestedMonths = () => {
    if (!startDate.value) {
        selectedMonthKeys.value = [];

        return;
    }

    if (splitByCourse.value && !isGrade9PackageMode.value) {
        if (pendingCourseMonths !== null) {
            const next = pendingCourseMonths;
            pendingCourseMonths = null;
            applyCourseMonths(next);

            return;
        }

        // 起算日或繳別變動：各科從自己的第一個月（不早於起算月）重排一個週期
        const startIndex = monthKeyIndex(startDate.value.slice(0, 7));
        const next: Record<number, string[]> = {};

        for (const id of selected.value) {
            const first = sortMonthKeys(courseMonthKeys.value[id] ?? [])[0];
            const from =
                first && monthKeyIndex(first) > startIndex
                    ? `${first.split('-')[0]}-${first.split('-')[1].padStart(2, '0')}-01`
                    : startDate.value;
            next[id] = defaultBillingMonths(from, sessionMonthSpan.value).map(
                (m) => monthKey(m.y, m.m),
            );
        }

        applyCourseMonths(next);

        return;
    }

    if (pendingMonthKeys !== null) {
        selectedMonthKeys.value = pendingMonthKeys;
        pendingMonthKeys = null;

        return;
    }

    if (isGrade9PackageMode.value) {
        const year = Number(startDate.value.slice(0, 4));
        selectedMonthKeys.value = [7, 8, 9, 10, 11, 12].map((m) =>
            monthKey(year, m),
        );

        return;
    }

    selectedMonthKeys.value = defaultBillingMonths(
        startDate.value,
        sessionMonthSpan.value,
    ).map((m) => monthKey(m.y, m.m));
};

const toggleBillingMonth = (key: string) => {
    const idx = selectedMonthKeys.value.indexOf(key);

    if (idx >= 0) {
        if (selectedMonthKeys.value.length <= 1) {
            return;
        }

        selectedMonthKeys.value = selectedMonthKeys.value.filter((k) => k !== key);
    } else {
        const next = [...selectedMonthKeys.value, key];
        const ym = parseMonthKey(key);
        const monthStart = ym ? `${ym.y}-${String(ym.m).padStart(2, '0')}-01` : '';

        if (monthStart !== '' && startDate.value && monthStart < startDate.value.slice(0, 7) + '-01') {
            pendingMonthKeys = next;
            startDate.value = monthStart;

            return;
        }

        selectedMonthKeys.value = next;
    }

    refillSessionDates();
};

const initSplitFromProps = () => {
    const source = splitSource();
    splitByCourse.value = source !== null;
    courseMonthKeys.value = {};
    pendingCourseMonths = source;

    if (source !== null) {
        pendingMonthKeys = null;
    }
};

const toggleCourseMonth = (courseId: number, key: string) => {
    const current = courseMonthKeys.value[courseId] ?? [];
    let next: string[];

    if (current.includes(key)) {
        if (current.length <= 1) {
            return;
        }

        next = current.filter((k) => k !== key);
    } else {
        next = [...current, key];
        const ym = parseMonthKey(key);
        const monthStart = ym ? `${ym.y}-${String(ym.m).padStart(2, '0')}-01` : '';

        if (monthStart !== '' && startDate.value && monthStart < startDate.value.slice(0, 7) + '-01') {
            pendingCourseMonths = { ...courseMonthKeys.value, [courseId]: next };
            startDate.value = monthStart;

            return;
        }
    }

    applyCourseMonths({ ...courseMonthKeys.value, [courseId]: next });
    refillSessionDates();
};

const setSplitByCourse = (on: boolean) => {
    if (on) {
        splitByCourse.value = true;
        applyCourseMonths(
            Object.fromEntries(
                selected.value.map((id) => [id, [...selectedMonthKeys.value]]),
            ),
        );
    } else {
        splitByCourse.value = false;
        courseMonthKeys.value = {};
    }

    refillSessionDates();
};

const onSplitToggle = (event: Event) => {
    setSplitByCourse(!!(event.target as HTMLInputElement | null)?.checked);
};

const calendarCourses = computed(() =>
    selected.value
        .map((id) => props.subjects.find((x) => x.id === id))
        .filter((s): s is Subject => !!s)
        .map((s) => ({
            id: s.id,
            name: s.name,
            weekdays: s.weekdays ?? [],
            color: s.color,
            start_date: s.start_date ?? null,
            end_date: s.end_date ?? null,
        })),
);

const subjectCardStyle = (s: Subject, isSelected: boolean) => {
    if (!isSelected) {
        return undefined;
    }

    return classroomCalendarSurface(s.color);
};

const subjectSwatchStyle = (s: Subject) => ({
    backgroundColor: normalizeClassroomHex(s.color),
});

const refillSessionDates = () => {
    if (!startDate.value) {
        sessions.value = [];

        return;
    }

    if (splitByCourse.value && !isGrade9PackageMode.value) {
        const ids = Object.keys(courseMonthKeys.value).map(Number).sort();
        const sel = [...selected.value].sort();

        if (ids.join(',') !== sel.join(',')) {
            applyCourseMonths(courseMonthKeys.value);
        }

        const merged: SessionEntry[] = [];

        for (const course of calendarCourses.value) {
            const months = (courseMonthKeys.value[course.id] ?? [])
                .map((key) => parseMonthKey(key))
                .filter((m): m is YearMonth => m !== null);

            if (months.length === 0) {
                continue;
            }

            merged.push(
                ...buildDefaultSessionEntries(
                    startDate.value,
                    [course],
                    sessionMonthSpan.value,
                    undefined,
                    { months },
                ),
            );
        }

        sessions.value = merged.sort(
            (a, b) => a.date.localeCompare(b.date) || a.course_id - b.course_id,
        );

        return;
    }

    if (selectedMonthKeys.value.length === 0) {
        syncSuggestedMonths();
    }

    sessions.value = buildDefaultSessionEntries(
        startDate.value,
        calendarCourses.value,
        sessionMonthSpan.value,
        undefined,
        { months: selectedMonths.value },
    );
};

watch(
    () => props.student?.id,
    () => {
        selected.value = defaultCourseIds();
        payCycle.value = defaultPayCycle();

        if (props.student) {
            query.value =
                `${props.student.student_code ? `${props.student.student_code} ` : ''}${props.student.name}`.trim();
        }

        if (props.has_prior_payments && props.suggested_start_date) {
            startDate.value = props.suggested_start_date;
        }

        initSplitFromProps();
        syncSuggestedMonths();
        refillSessionDates();
    },
);

watch(
    () => [props.suggested_course_ids, props.suggested_pay_cycle] as const,
    () => {
        selected.value = defaultCourseIds();
        payCycle.value = defaultPayCycle();
        initSplitFromProps();
        syncSuggestedMonths();
        refillSessionDates();
    },
);

watch(
    () => [props.has_prior_payments, props.suggested_start_date] as const,
    ([hasPrior, suggested]) => {
        if (hasPrior && suggested) {
            startDate.value = suggested;
            syncSuggestedMonths();
        }
    },
);

watch([startDate, payCycle], () => {
    syncSuggestedMonths();
    refillSessionDates();
});

watch(isGrade9PackageMode, (active) => {
    if (active) {
        splitByCourse.value = false;
        courseMonthKeys.value = {};
        chargeMaterialMaster.value = false;
        chargeConsumableIds.value = [];
        materialHalfState.value = {};
        syncSuggestedMonths();
    }
});

watch(
    selected,
    () => {
        refillSessionDates();

        if (isGrade9PackageMode.value) {
            chargeMaterialMaster.value = false;
            chargeConsumableIds.value = [];
            materialHalfState.value = {};
            syncSuggestedMonths();
        } else {
            syncDefaultMaterialChecks();
        }
    },
);

if (props.student && props.edit) {
    pendingMonthKeys = [...props.edit.month_keys];
    const seeded: Record<string, { checked: boolean; amount: number }> = {};

    for (const row of props.edit.material_halves) {
        seeded[halfKey(row.course_id, row.period_year, row.period_half)] = {
            checked: true,
            amount: row.amount,
        };
    }

    materialHalfState.value = seeded;
}

if (props.student) {
    initSplitFromProps();
    syncSuggestedMonths();
    refillSessionDates();
    syncDefaultMaterialChecks();
    chargeMaterialMaster.value =
        Object.values(materialHalfState.value).some((v) => v.checked) ||
        chargeConsumableIds.value.length > 0;
}

let materialStatusReloadTimer: ReturnType<typeof setTimeout> | null = null;
watch(startDate, (value, oldValue) => {
    if (!props.student || !value || value === oldValue) {
        return;
    }

    if (materialStatusReloadTimer) {
        clearTimeout(materialStatusReloadTimer);
    }

    materialStatusReloadTimer = setTimeout(() => {
        router.get(
            '/student-payments/create',
            {
                student_id: props.student!.id,
                as_of: value,
                course_ids: selected.value,
                pay_cycle: payCycle.value,
                ...(props.edit
                    ? { edit_from: props.edit.from, edit_to: props.edit.to }
                    : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['material_status'],
                replace: true,
            },
        );
    }, 300);
});

watch(
    () => props.material_status,
    () => {
        syncDefaultMaterialChecks();
    },
);

watch(selectedMonthKeys, () => {
    syncDefaultMaterialChecks();
});

const form = useForm({
    course_ids: [] as number[],
    pay_cycle: 'quarterly' as 'monthly' | 'quarterly' | 'annual',
    sessions: [] as SessionEntry[],
    allowance: 0,
    start_date: '' as string | null,
    charge_material_course_ids: [] as number[],
    material_half_charges: [] as Array<{
        course_id: number;
        period_year: number;
        period_half: string;
        amount: number;
    }>,
    fee_discount_id: null as number | null,
    receipt_no: null as string | null,
    edit_from: props.edit?.from ?? null,
    edit_to: props.edit?.to ?? null,
    return_grade_level_id: props.return_grade_level_id ?? null,
    course_months: {} as Record<number, string[]>,
});

/** 組合優惠看報名中的科目（本次收款＋報名科目），各科分開繳也維持優惠；每科只取最便宜的組合 */
const bundleByCourse = computed(() => {
    const enrolled = new Set(
        [...selected.value, ...(props.enrolled_course_ids ?? [])].filter(
            (id) =>
                props.subjects.find((s) => s.id === id)?.fee_plan_id != null,
        ),
    );
    const out = new Map<number, { price: number; name: string }>();

    for (const bundle of props.bundles ?? []) {
        const matched = bundle.course_ids.filter((id) => enrolled.has(id));

        if (matched.length < Math.max(1, bundle.min_courses)) {
continue;
}

        for (const id of bundle.course_ids) {
            if (!selected.value.includes(id)) {
continue;
}

            const current = out.get(id);

            if (!current || bundle.price < current.price) {
                out.set(id, { price: bundle.price, name: bundle.name });
            }
        }
    }

    return out;
});

const appliedBundle = (s: Subject) => {
    if (payCycle.value === 'monthly') {
return null;
}

    const bundle = bundleByCourse.value.get(s.id);

    return bundle && bundle.price < s.q_single ? bundle : null;
};

const appliedBundleNames = computed(() => [
    ...new Set(
        props.subjects
            .filter((s) => selected.value.includes(s.id))
            .map((s) => appliedBundle(s)?.name)
            .filter((name): name is string => !!name),
    ),
]);

/** 一個月單價（定價／優惠／組合優惠） */
const monthlyUnitPrice = (s: Subject) => {
    if (payCycle.value === 'monthly') {
        return s.list;
    }

    return appliedBundle(s)?.price ?? s.q_single;
};

/** 計價單位：按月＝月價；按期＝一期總額（月價 × 期數） */
const unitPrice = (s: Subject) => {
    const monthly = monthlyUnitPrice(s);
    if (s.unit !== 'session_block') {
        return monthly;
    }

    return monthly * Math.max(1, Number(s.session_block_size) || 3);
};

const cardPeriodPrice = (s: Subject, monthly: number) => {
    if (s.unit !== 'session_block') {
        return monthly;
    }

    return monthly * Math.max(1, Number(s.session_block_size) || 3);
};

const sessionDates = computed(() => uniqueDatesFromSessions(sessions.value));
const isSplitMode = computed(
    () => splitByCourse.value && !isGrade9PackageMode.value,
);
/** 分科模式以各科勾選月份聯集為帳期（與後端一致，沒上課的月份也列出） */
const billingMonths = computed(() =>
    isSplitMode.value
        ? selectedMonths.value
        : monthsFromDates(sessionDates.value),
);
const monthCount = computed(() =>
    isGrade9PackageMode.value
        ? Math.max(6, selectedMonths.value.length)
        : billingMonths.value.length,
);
const courseBillingMonths = (courseId: number): YearMonth[] => {
    if (!isSplitMode.value) {
        return billingMonths.value;
    }

    return (courseMonthKeys.value[courseId] ?? [])
        .map((key) => parseMonthKey(key))
        .filter((m): m is YearMonth => m !== null);
};

const isCourseMonth = (courseId: number, y: number, m: number) =>
    !isSplitMode.value ||
    (courseMonthKeys.value[courseId] ?? []).includes(monthKey(y, m));

const blockTuition = (s: Subject) => {
    const blockSize = Math.max(1, Number(s.session_block_size) || 3);

    return (
        unitPrice(s) *
        Math.max(
            1,
            Math.ceil(Math.max(1, courseBillingMonths(s.id).length) / blockSize),
        )
    );
};

const countSessionsInMonth = (courseId: number, y: number, m: number) =>
    sessions.value.filter((entry) => {
        if (entry.course_id !== courseId) {
return false;
}

        const [ey, em] = entry.date.slice(0, 10).split('-').map(Number);

        return ey === y && em === m;
    }).length;

const lineTuitionForMonthExact = (s: Subject, y: number, m: number) => {
    const price = unitPrice(s);
    const attended = countSessionsInMonth(s.id, y, m);

    if (s.unit === 'session_block' || !isCourseMonth(s.id, y, m)) {
        return 0;
    }

    return proratedMonthTuitionExact(
        price,
        attended,
        billingBaselineSessions(s.weekdays),
    );
};

const lineTuition = (s: Subject) => {
    if (s.unit === 'session_block') {
        return blockTuition(s);
    }

    // 各月：該科精確值加總後再進位會與「多科合計再進位」不一致；
    // 單科小計仍用各月 round(精確值)，月合計另以多科加總後 round。
    return courseBillingMonths(s.id).reduce((sum, month) => {
        return sum + Math.round(lineTuitionForMonthExact(s, month.y, month.m));
    }, 0);
};

const isChargingConsumable = (courseId: number) =>
    chargeMaterialMaster.value && chargeConsumableIds.value.includes(courseId);

const toggleConsumable = (courseId: number) => {
    if (chargeConsumableIds.value.includes(courseId)) {
        chargeConsumableIds.value = chargeConsumableIds.value.filter(
            (id) => id !== courseId,
        );
    } else {
        chargeConsumableIds.value = [...chargeConsumableIds.value, courseId];
    }
};

const halfState = (courseId: number, year: number, half: string) => {
    const key = halfKey(courseId, year, half);

    return (
        materialHalfState.value[key] ?? {
            checked: false,
            amount: 0,
        }
    );
};

const setHalfChecked = (
    courseId: number,
    year: number,
    half: string,
    checked: boolean,
) => {
    const key = halfKey(courseId, year, half);
    const status = materialStatusByKey.value.get(key);

    if (checked && status && !status.can_charge) {
        return;
    }

    const current = halfState(courseId, year, half);
    materialManualKeys.value = new Set(materialManualKeys.value).add(key);
    materialHalfState.value = {
        ...materialHalfState.value,
        [key]: { ...current, checked },
    };
};

const setHalfAmount = (
    courseId: number,
    year: number,
    half: string,
    raw: string,
) => {
    const key = halfKey(courseId, year, half);
    const current = halfState(courseId, year, half);
    const amount = Math.max(0, Math.floor(Number(raw) || 0));
    materialHalfState.value = {
        ...materialHalfState.value,
        [key]: { ...current, amount },
    };
};

const toggleMaterialMaster = (event: Event) => {
    const input = event.target as HTMLInputElement | null;
    chargeMaterialMaster.value = !!input?.checked;
};

const onHalfCheckedChange = (
    courseId: number,
    year: number,
    half: string,
    event: Event,
) => {
    const input = event.target as HTMLInputElement | null;
    setHalfChecked(courseId, year, half, !!input?.checked);
};

const onHalfAmountInput = (
    courseId: number,
    year: number,
    half: string,
    value: string | number | null | undefined,
) => {
    setHalfAmount(courseId, year, half, String(value ?? ''));
};

const checkedHalfAmount = (courseId: number, period: HalfPeriod) => {
    const key = halfKey(courseId, period.year, period.half);
    const status = materialStatusByKey.value.get(key);

    if (status && !status.can_charge) {
        return 0;
    }

    const st = halfState(courseId, period.year, period.half);

    return st.checked ? Math.max(0, st.amount) : 0;
};

const courseHalfAmount = (courseId: number) => {
    if (!chargeMaterialMaster.value) {
        return 0;
    }

    return materialPeriods.value.reduce(
        (sum, period) => sum + checkedHalfAmount(courseId, period),
        0,
    );
};

/** 半年教材掛在帳期中第一個落在該半年的月份；帳期不含該半年則掛第一個月（與後端一致） */
const halfTargetMonth = (period: HalfPeriod, courseId: number) => {
    const months = courseBillingMonths(courseId);

    return (
        months.find(
            ({ y, m }) =>
                y === period.year && (m <= 6 ? 'H1' : 'H2') === period.half,
        ) ?? months[0]
    );
};

const lineMaterial = (s: Subject) => {
    if (!s.material || !chargeMaterialMaster.value) {
return 0;
}

    if (s.material_unit === 'class_day') {
        if (!isChargingConsumable(s.id)) {
return 0;
}

        return countSessionsForCourse(sessions.value, s.id) * s.material;
    }

    return courseHalfAmount(s.id);
};

/** 半年教材掛在對應月份（1 月／7 月或帳期首月）；耗材仍按月 */
const lineMaterialForMonth = (s: Subject, y: number, m: number) => {
    if (!s.material || !chargeMaterialMaster.value) {
return 0;
}

    if (s.material_unit === 'class_day') {
        if (!isChargingConsumable(s.id)) {
return 0;
}

        return countSessionsInMonth(s.id, y, m) * s.material;
    }

    if (billingMonths.value.length === 0) {
        return 0;
    }

    return materialPeriods.value.reduce((sum, period) => {
        const target = halfTargetMonth(period, s.id);

        if (!target || target.y !== y || target.m !== m) {
            return sum;
        }

        return sum + checkedHalfAmount(s.id, period);
    }, 0);
};

const materialHint = (s: Subject): string => {
    if (!s.material) {
        return '無教材／耗材';
    }

    if (s.material_unit === 'class_day') {
        return `耗材 ${s.material.toLocaleString()}/日｜${formatWeekdays(s.weekdays)}`;
    }

    const semi = semiAnnualMaterialFee(s.material);

    return `教材 ${s.material.toLocaleString()}（半年 ${semi.toLocaleString()}）`;
};

/** 有教材或耗材的已選科目（總覽區塊是否顯示） */
const hasMaterialSubjects = computed(() => {
    if (isGrade9PackageMode.value) {
        return false;
    }

    return selected.value.some((id) => {
        const s = props.subjects.find((x) => x.id === id);

        return !!(s && s.material > 0);
    });
});

/** 各科教材列（半年兩段） */
const materialTermCourses = computed(() => {
    if (isGrade9PackageMode.value || !chargeMaterialMaster.value) {
        return [] as Array<{
            id: number;
            name: string;
            annual: number;
            semi: number;
            halves: Array<{
                half: 'H1' | 'H2';
                periodYear: number;
                monthsLabel: string;
                periodLabel: string;
                locked: boolean;
                note: string | null;
                checked: boolean;
                amount: number;
            }>;
        }>;
    }

    const rows = [];

    for (const id of selected.value) {
        const s = props.subjects.find((x) => x.id === id);

        if (!s || !s.material || s.material_unit === 'class_day') {
            continue;
        }

        const semi = semiAnnualMaterialFee(s.material);
        const halves = materialPeriods.value.map(({ year, half }) => {
            const key = halfKey(id, year, half);
            const status = materialStatusByKey.value.get(key);
            const st = halfState(id, year, half);

            return {
                half,
                periodYear: year,
                monthsLabel:
                    status?.months_label ?? (half === 'H1' ? '1–6' : '7–12'),
                periodLabel:
                    status?.period_label ??
                    `${year}${half === 'H1' ? '上半年（1–6）' : '下半年（7–12）'}`,
                locked: !!(status && !status.can_charge),
                note: status?.note ?? null,
                checked: st.checked && !(status && !status.can_charge),
                amount: st.amount > 0 ? st.amount : (status?.amount ?? semi),
            };
        });
        rows.push({
            id: s.id,
            name: s.name,
            annual: s.material,
            semi,
            halves,
        });
    }

    return rows;
});

/** 耗材列 */
const consumableRows = computed(() => {
    if (isGrade9PackageMode.value || !chargeMaterialMaster.value) {
        return [] as Array<{
            id: number;
            name: string;
            amountLabel: string;
            amount: number;
        }>;
    }

    const rows = [];

    for (const id of selected.value) {
        const s = props.subjects.find((x) => x.id === id);

        if (!s || !s.material || s.material_unit !== 'class_day') {
            continue;
        }

        const days = countSessionsForCourse(sessions.value, s.id);
        rows.push({
            id: s.id,
            name: s.name,
            amountLabel: `${s.material.toLocaleString()}/日 × ${days}天`,
            amount: days * s.material,
        });
    }

    return rows;
});

const materialHalfSummary = computed(() => {
    const rows: Array<{ label: string; amount: number }> = [];

    if (!chargeMaterialMaster.value) {
        return rows;
    }

    for (const course of materialTermCourses.value) {
        for (const half of course.halves) {
            if (!half.checked || half.locked) {
                continue;
            }

            rows.push({
                label: `${course.name}｜${half.periodLabel}`,
                amount: half.amount,
            });
        }
    }

    for (const row of consumableRows.value) {
        if (!isChargingConsumable(row.id)) {
            continue;
        }

        rows.push({ label: `${row.name}｜耗材`, amount: row.amount });
    }

    return rows;
});

const tuitionTotal = computed(() => {
    if (isGrade9PackageMode.value) {
        return grade9Package.value?.total ?? 120000;
    }

    // 與試算「各月金額」一致：每月先加總各科精確學費，再四捨五入後加總
    return monthBreakdown.value.reduce((sum, row) => sum + row.tuition, 0);
});

const materialTotal = computed(() => {
    if (isGrade9PackageMode.value) {
        return 0;
    }

    return selected.value.reduce((sum, id) => {
        const s = props.subjects.find((x) => x.id === id);

        return s ? sum + lineMaterial(s) : sum;
    }, 0);
});

const monthBreakdown = computed(() => {
    if (isGrade9PackageMode.value) {
        return packageMonthBreakdown.value;
    }

    const selectedSubjects = selected.value
        .map((id) => props.subjects.find((x) => x.id === id))
        .filter((s): s is Subject => !!s);

    // 堂數制科目：總額平均攤到該科自己的帳期月份，餘數放第一個月（與後端一致）
    const blockByMonth = new Map<string, number>();

    for (const s of selectedSubjects.filter((x) => x.unit === 'session_block')) {
        const months = courseBillingMonths(s.id);

        if (months.length === 0) {
            continue;
        }

        const total = blockTuition(s);
        const per = Math.floor(total / months.length);
        const rem = total % months.length;
        months.forEach((month, index) => {
            const key = monthKey(month.y, month.m);
            blockByMonth.set(
                key,
                (blockByMonth.get(key) ?? 0) + per + (index === 0 ? rem : 0),
            );
        });
    }

    return billingMonths.value.map((month) => {
        const exactTuition = selectedSubjects
            .filter((s) => s.unit !== 'session_block')
            .reduce(
                (sum, s) => sum + lineTuitionForMonthExact(s, month.y, month.m),
                0,
            );
        let tuition = Math.round(exactTuition);
        tuition += blockByMonth.get(monthKey(month.y, month.m)) ?? 0;

        const material = selectedSubjects.reduce(
            (sum, s) => sum + lineMaterialForMonth(s, month.y, month.m),
            0,
        );

        const materialHalfHint =
            material > 0 &&
            selectedSubjects.some(
                (s) =>
                    s.material > 0 &&
                    s.material_unit !== 'class_day' &&
                    courseHalfAmount(s.id) > 0,
            )
                ? '含半年教材'
                : null;

        return {
            y: month.y,
            m: month.m,
            label: `${month.y}/${month.m}`,
            tuition,
            material,
            subtotal: tuition + material,
            prorateHints: [] as string[],
            materialHalfHint,
        };
    });
});

const grandTotal = computed(() =>
    Math.max(
        0,
        tuitionTotal.value + materialTotal.value - Number(allowance.value || 0),
    ),
);

const feeDiscounts = computed(() => props.fee_discounts ?? []);

const selectedDiscount = computed(() =>
    feeDiscounts.value.find((d) => d.id === selectedDiscountId.value) ?? null,
);

const computeDiscountAllowance = (discount: {
    type: 'amount' | 'percent';
    value: number;
}) => {
    const subtotal = tuitionTotal.value + materialTotal.value;

    if (subtotal <= 0 || discount.value <= 0) {
        return 0;
    }

    if (discount.type === 'percent') {
        const pct = Math.min(100, Math.max(0, discount.value));

        return Math.min(subtotal, Math.round((subtotal * pct) / 100));
    }

    return Math.min(subtotal, discount.value);
};

const applySelectedDiscount = () => {
    const discount = selectedDiscount.value;

    if (!discount) {
        return;
    }

    allowance.value = computeDiscountAllowance(discount);
};

watch([selectedDiscountId, tuitionTotal, materialTotal], () => {
    if (selectedDiscountId.value !== null) {
        applySelectedDiscount();
    }
});

const onDiscountChange = (raw: string) => {
    if (raw === '' || raw === '0') {
        selectedDiscountId.value = null;

        return;
    }

    selectedDiscountId.value = Number(raw);
    applySelectedDiscount();
};

const onAllowanceManualInput = () => {
    // 手動改折讓時取消優惠選取，避免％數被覆蓋後仍顯示舊優惠
    selectedDiscountId.value = null;
};

const toggleSubject = (id: number) => {
    if (!props.subjects.find((subject) => subject.id === id)?.fee_plan_id) {
        return;
    }

    if (selected.value.includes(id)) {
        selected.value = selected.value.filter((x) => x !== id);
    } else {
        selected.value = [...selected.value, id];
    }

    syncDefaultMaterialChecks();
};

const search = async (q: string) => {
    searchError.value = '';

    if (q.trim() === '') {
        results.value = [];
        open.value = false;

        return;
    }

    abortController?.abort();
    abortController = new AbortController();
    loading.value = true;

    try {
        const res = await fetch(
            `/student-payments/search?q=${encodeURIComponent(q.trim())}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: abortController.signal,
            },
        );

        if (!res.ok) {
            throw new Error('搜尋失敗');
        }

        const data = (await res.json()) as { students: StudentOption[] };
        results.value = data.students ?? [];
        open.value = true;
    } catch (e) {
        if ((e as Error).name === 'AbortError') {
            return;
        }

        searchError.value = '搜尋失敗，請再試一次。';
        results.value = [];
    } finally {
        loading.value = false;
    }
};

watch(query, (value) => {
    if (props.student) {
        const label =
            `${props.student.student_code ? `${props.student.student_code} ` : ''}${props.student.name}`.trim();

        if (value === label) {
            return;
        }
    }

    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        void search(value);
    }, 250);
});

const selectStudent = (student: StudentOption) => {
    open.value = false;
    query.value =
        `${student.student_code ? `${student.student_code} ` : ''}${student.name}`.trim();
    router.get(
        '/student-payments/create',
        { student_id: student.id },
        { preserveState: false, replace: true },
    );
};

const clearStudent = () => {
    query.value = '';
    results.value = [];
    open.value = false;
    const gradeId = props.student?.grade_level_id ?? null;
    router.get(
        '/student-payments/create',
        gradeId ? { grade_level_id: gradeId } : {},
        { preserveState: false, replace: true },
    );
};

const gradeFilterValue = ref(props.grade_filter ? String(props.grade_filter) : '');
const gradeListQuery = ref('');

watch(
    () => props.grade_filter,
    (value) => {
        gradeFilterValue.value = value ? String(value) : '';
    },
);

const onGradeFilterChange = () => {
    gradeListQuery.value = '';
    router.get(
        '/student-payments/create',
        gradeFilterValue.value ? { grade_level_id: Number(gradeFilterValue.value) } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const filteredGradeStudents = computed(() => {
    const keyword = gradeListQuery.value.trim();
    const list = props.grade_students ?? [];

    if (keyword === '') {
        return list;
    }

    return list.filter(
        (s) => s.name.includes(keyword) || (s.student_code ?? '').includes(keyword),
    );
});

const submit = () => {
    if (!props.student) {
        return;
    }

    form.course_ids = [...selected.value];
    form.pay_cycle = payCycle.value;
    form.sessions = [...sessions.value];
    form.allowance = Number(allowance.value || 0);
    form.start_date = startDate.value || null;

    if (isGrade9PackageMode.value || !chargeMaterialMaster.value) {
        form.charge_material_course_ids = [];
        form.material_half_charges = [];
    } else {
        form.charge_material_course_ids = chargeConsumableIds.value.filter((id) =>
            selected.value.includes(id),
        );
        const charges: Array<{
            course_id: number;
            period_year: number;
            period_half: string;
            amount: number;
        }> = [];

        for (const course of materialTermCourses.value) {
            for (const half of course.halves) {
                if (!half.checked || half.locked || half.amount <= 0) {
                    continue;
                }

                charges.push({
                    course_id: course.id,
                    period_year: half.periodYear,
                    period_half: half.half,
                    amount: half.amount,
                });
            }
        }

        form.material_half_charges = charges;
    }

    form.fee_discount_id = selectedDiscountId.value;
    const trimmedReceipt = receiptNo.value.trim();
    form.receipt_no = trimmedReceipt === '' ? null : trimmedReceipt;
    form.course_months = isSplitMode.value
        ? Object.fromEntries(
              selected.value.map((id) => [id, courseMonthKeys.value[id] ?? []]),
          )
        : {};
    form.post(`/student-payments/${props.student.id}/quote`, {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }

    if (materialStatusReloadTimer) {
        clearTimeout(materialStatusReloadTimer);
    }

    abortController?.abort();
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生收款', href: '/student-payments' },
            { title: '新增收款', href: '/student-payments/create' },
        ],
    },
});
</script>

<template>
    <Head :title="isEditMode ? '編輯收款' : '新增收款'" />

    <div class="page-shell w-full text-base [&_.text-xs]:text-sm [&_.text-sm]:text-base">
        <div class="mb-2">
            <Link
                :href="editBackHref"
                class="text-base text-primary underline-offset-4 hover:underline"
            >
                {{
                    isEditMode
                        ? '← 返回收款明細'
                        : return_grade_level_id
                          ? '← 返回年級名單'
                          : '← 返回明細紀錄'
                }}
            </Link>
        </div>

        <PageHeader
            :title="isEditMode ? `編輯收款｜${editContext?.period_label}` : '新增收款／報名計價'"
            :description="
                isEditMode
                    ? '可重新選擇科目、繳別、月份、教材與優惠；儲存後會取代原本這一期的收款紀錄（收款日與收款人保留）。'
                    : '選擇學生、科目與帳期月份後，右側試算確認金額即可收款。'
            "
        />

        <div
            v-if="isEditMode"
            class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
        >
            正在編輯 {{ editContext?.period_label }} 的收款。起算日預設為該期第一個月的 1 號，若原本是月中入班請調整起算日。
        </div>

        <InputError :message="(page.props.errors as Record<string, string>)?.edit" />

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>

        <div
            class="mb-4 rounded-xl border border-sidebar-border/70 bg-card p-4"
        >
            <div class="relative grid gap-2">
                <Label for="student_q">學生</Label>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Input
                        id="student_q"
                        v-model="query"
                        type="search"
                        autocomplete="off"
                        class="h-11 flex-1"
                        placeholder="輸入學號或姓名"
                        @focus="open = results.length > 0"
                        @keydown.escape="open = false"
                    />
                    <Button
                        v-if="student"
                        type="button"
                        variant="outline"
                        class="h-11 shrink-0"
                        @click="clearStudent"
                    >
                        清除
                    </Button>
                    <Button
                        v-if="student"
                        type="button"
                        variant="secondary"
                        class="h-11 shrink-0"
                        as-child
                    >
                        <Link :href="`/student-payments/${student.id}`"
                            >收款明細</Link
                        >
                    </Button>
                </div>
                <p v-if="loading" class="text-xs text-muted-foreground">
                    搜尋中…
                </p>
                <p v-else-if="searchError" class="text-xs text-destructive">
                    {{ searchError }}
                </p>
                <p v-else-if="student" class="text-xs text-muted-foreground">
                    已選：{{ student.student_code ?? '—' }} {{ student.name }}
                    <span v-if="student.grade_name"
                        >｜{{ student.grade_name }}</span
                    >
                    <span v-if="student.academic_year_name"
                        >｜{{ student.academic_year_name }}</span
                    >
                </p>
                <p v-else class="text-xs text-muted-foreground">
                    輸入後從清單選擇學生，科目會依年級自動帶入。
                </p>

                <div
                    v-if="open && results.length > 0"
                    class="absolute top-full z-20 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-md sm:max-w-xl"
                >
                    <button
                        v-for="s in results"
                        :key="s.id"
                        type="button"
                        class="flex w-full items-start gap-3 border-b px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-accent"
                        @click="selectStudent(s)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ s.name }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ s.student_code ?? '無學號' }}
                                <span v-if="s.grade_name"
                                    >｜{{ s.grade_name }}</span
                                >
                            </span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <template v-if="!student">
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="grid gap-2 sm:w-48">
                        <Label for="grade_filter">依年級選學生</Label>
                        <select
                            id="grade_filter"
                            v-model="gradeFilterValue"
                            class="h-11 rounded-md border bg-background px-3"
                            @change="onGradeFilterChange"
                        >
                            <option value="">請選擇年級</option>
                            <option v-for="g in grade_levels ?? []" :key="g.id" :value="String(g.id)">
                                {{ g.name }}
                            </option>
                        </select>
                    </div>
                    <div v-if="grade_filter" class="grid flex-1 gap-2">
                        <Label for="grade_list_q">在此年級中搜尋</Label>
                        <Input
                            id="grade_list_q"
                            v-model="gradeListQuery"
                            class="h-11"
                            placeholder="學號或姓名"
                        />
                    </div>
                </div>

                <p
                    v-if="!grade_filter"
                    class="mt-6 rounded-lg border border-dashed p-8 text-center text-muted-foreground"
                >
                    請在上方搜尋學生，或選擇年級後從清單直接新增收款。
                </p>

                <template v-else>
                    <p class="mt-4 text-sm text-muted-foreground">
                        共 {{ filteredGradeStudents.length }} 位學生（不含已畢業）
                    </p>
                    <div class="mt-2 overflow-hidden rounded-lg border">
                        <div
                            v-for="s in filteredGradeStudents"
                            :key="s.id"
                            class="flex flex-wrap items-center gap-3 border-b px-3 py-2.5 last:border-b-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-medium">
                                    <span class="font-mono">{{ s.student_code ?? '無學號' }}</span>
                                    {{ s.name }}
                                    <span
                                        v-if="s.status !== 'active'"
                                        :class="studentStatusPillClass(s.status)"
                                        class="ml-1 align-middle"
                                    >
                                        {{ studentStatusLabel(s.status) }}
                                    </span>
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ s.paid_through ? `已繳至 ${s.paid_through}` : '尚無收款紀錄' }}
                                </div>
                            </div>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="`/student-payments/${s.id}`">收款明細</Link>
                            </Button>
                            <Button size="sm" as-child>
                                <Link :href="`/student-payments/create?student_id=${s.id}&return_grade=${grade_filter}`">新增收款</Link>
                            </Button>
                        </div>
                        <p
                            v-if="filteredGradeStudents.length === 0"
                            class="p-8 text-center text-muted-foreground"
                        >
                            此年級沒有符合的學生。
                        </p>
                    </div>
                </template>
            </div>
        </template>

        <template v-else>
            <div
                v-for="(w, i) in warnings"
                :key="i"
                class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                {{ w }}
            </div>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0 space-y-4">
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <h2 class="text-lg font-semibold">科目</h2>
                        <p
                            v-if="subjects.length === 0"
                            class="mt-3 text-base text-muted-foreground"
                        >
                            尚無可報名課目。請確認學生年級與收費設定的適用課目。
                        </p>
                        <div
                            v-else
                            class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
                        >
                            <div
                                v-for="s in subjects"
                                :key="s.id"
                                role="button"
                                tabindex="0"
                                class="rounded-lg border px-3 py-2 text-left text-sm transition"
                                :class="[
                                    selected.includes(s.id)
                                        ? ''
                                        : 'hover:border-primary/40',
                                    s.fee_plan_id === null
                                        ? 'cursor-not-allowed opacity-50'
                                        : 'cursor-pointer',
                                ]"
                                :style="
                                    subjectCardStyle(s, selected.includes(s.id))
                                "
                                :aria-disabled="s.fee_plan_id === null"
                                @click="toggleSubject(s.id)"
                                @keydown.enter.prevent="toggleSubject(s.id)"
                                @keydown.space.prevent="toggleSubject(s.id)"
                            >
                                <div
                                    class="flex items-center gap-2 font-medium"
                                >
                                    <span
                                        class="inline-block size-2.5 shrink-0 rounded-sm border border-black/10"
                                        :style="subjectSwatchStyle(s)"
                                    />
                                    {{ s.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    <span v-if="s.group_name">{{
                                        s.group_name
                                    }}</span>
                                    <span v-else>尚未設定適用價目</span>
                                    <span v-if="s.weekdays?.length"
                                        >｜{{
                                            formatWeekdays(s.weekdays)
                                        }}</span
                                    >
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    定價
                                    {{
                                        cardPeriodPrice(
                                            s,
                                            s.list,
                                        ).toLocaleString()
                                    }}｜季繳
                                    {{
                                        cardPeriodPrice(
                                            s,
                                            s.q_single,
                                        ).toLocaleString()
                                    }}
                                    <span
                                        v-if="appliedBundle(s)"
                                        class="text-emerald-700"
                                        >（{{ appliedBundle(s)!.name }}
                                        {{
                                            appliedBundle(s)!.price.toLocaleString()
                                        }}）</span
                                    >｜{{ materialHint(s) }}
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.course_ids" />
                    </div>

                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <h2 class="text-base font-semibold">繳別</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="opt in [
                                    { v: 'monthly', t: '月繳（定價）' },
                                    { v: 'quarterly', t: '季繳' },
                                    { v: 'annual', t: '年繳' },
                                ]"
                                :key="opt.v"
                                type="button"
                                class="rounded-md border px-3 py-1.5 text-sm"
                                :class="
                                    payCycle === opt.v
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : ''
                                "
                                @click="payCycle = opt.v as typeof payCycle"
                            >
                                {{ opt.t }}
                            </button>
                        </div>

                        <div
                            v-if="grade9Package?.eligible"
                            class="mt-4 rounded-lg border border-emerald-300/70 bg-emerald-50/60 p-3"
                        >
                            <p class="text-sm font-medium text-emerald-950">
                                國三全科年繳方案
                            </p>
                            <p class="mt-1 text-sm text-emerald-900/85">
                                選齊全部有價目科目、年繳、起算日為 7
                                月：總額
                                {{
                                    (
                                        grade9Package?.total ?? 120000
                                    ).toLocaleString()
                                }}（含教材），7–12 月共 6 期，每月
                                {{
                                    (
                                        grade9Package?.monthly_total ?? 20000
                                    ).toLocaleString()
                                }}
                                均攤至各科應收。
                            </p>
                            <p
                                v-if="isGrade9PackageMode"
                                class="mt-2 text-sm font-medium text-emerald-800"
                            >
                                已套用方案：教材另收關閉；帳期固定 7–12 月。
                            </p>
                            <p
                                v-else-if="packageAlmostReady"
                                class="mt-2 text-sm text-emerald-900/80"
                            >
                                <template v-if="!canApplyGrade9Package">
                                    續繳起算非 7
                                    月，無法套用此方案（請改用季繳／月繳）。
                                </template>
                                <template v-else>
                                    尚未符合條件（需年繳＋7 月起算＋選齊
                                    {{ grade9Package?.required_count ?? 0 }}
                                    科）。可一鍵套用。
                                </template>
                            </p>
                            <button
                                v-if="!isGrade9PackageMode && canApplyGrade9Package"
                                type="button"
                                class="mt-3 rounded-md border border-emerald-700/40 bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-800"
                                @click="applyGrade9Package"
                            >
                                套用國三全科年繳
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="grid gap-2 sm:grid-cols-2 sm:items-end">
                            <div v-if="!hasPriorPayments" class="grid gap-2">
                                <Label for="start_date">入班／起算日</Label>
                                <Input
                                    id="start_date"
                                    v-model="startDate"
                                    type="date"
                                    class="h-10"
                                />
                                <InputError :message="form.errors.start_date" />
                            </div>
                            <div v-else class="grid gap-1">
                                <p class="text-sm font-medium">帳期起算</p>
                                <p
                                    class="text-sm font-medium tabular-nums text-foreground"
                                >
                                    {{ startDate }}
                                </p>
                                <InputError :message="form.errors.start_date" />
                            </div>
                        </div>

                        <div class="mt-4 grid gap-2">
                            <Label>帳期月份</Label>
                            <p
                                v-if="isGrade9PackageMode"
                                class="text-xs text-muted-foreground"
                            >
                                國三全科年繳固定 7–12 月共 6
                                期，每月合計 20,000（20,000 ÷
                                科目數寫入各科該月應收）。
                            </p>
                            <label
                                v-if="!isGrade9PackageMode && selected.length > 1"
                                class="inline-flex w-fit cursor-pointer items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    class="size-3.5 accent-[var(--brand-green)]"
                                    :checked="splitByCourse"
                                    @change="onSplitToggle"
                                />
                                各科分開勾月份（科目繳到的月份不同時使用）
                            </label>
                            <div v-if="isSplitMode" class="grid gap-3">
                                <div
                                    v-for="course in calendarCourses"
                                    :key="`months-${course.id}`"
                                    class="grid gap-1.5"
                                >
                                    <p
                                        class="inline-flex items-center gap-1.5 text-sm font-medium"
                                    >
                                        <span
                                            class="size-2.5 rounded-full"
                                            :style="{
                                                backgroundColor:
                                                    normalizeClassroomHex(
                                                        course.color,
                                                    ),
                                            }"
                                        />
                                        {{ course.name }}
                                        <span
                                            class="text-xs font-normal text-muted-foreground"
                                            >{{
                                                (courseMonthKeys[course.id] ?? []).length
                                            }}
                                            個月</span
                                        >
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="opt in monthCheckboxOptions"
                                            :key="`${course.id}-${opt.key}`"
                                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm"
                                            :class="
                                                (courseMonthKeys[course.id] ?? []).includes(opt.key)
                                                    ? 'border-primary bg-primary/10 text-foreground'
                                                    : 'border-border text-muted-foreground'
                                            "
                                        >
                                            <input
                                                type="checkbox"
                                                class="size-3.5 rounded border"
                                                :checked="
                                                    (courseMonthKeys[course.id] ?? []).includes(opt.key)
                                                "
                                                @change="
                                                    toggleCourseMonth(course.id, opt.key)
                                                "
                                            />
                                            {{ opt.yearLabel }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <label
                                    v-for="opt in monthCheckboxOptions"
                                    :key="opt.key"
                                    class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm"
                                    :class="[
                                        selectedMonthKeys.includes(opt.key)
                                            ? 'border-primary bg-primary/10 text-foreground'
                                            : 'border-border text-muted-foreground',
                                        isGrade9PackageMode
                                            ? 'cursor-not-allowed opacity-80'
                                            : 'cursor-pointer',
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-3.5 rounded border"
                                        :checked="
                                            selectedMonthKeys.includes(opt.key)
                                        "
                                        :disabled="isGrade9PackageMode"
                                        @change="toggleBillingMonth(opt.key)"
                                    />
                                    {{ opt.yearLabel }}
                                </label>
                            </div>
                        </div>

                        <div
                            v-if="hasMaterialSubjects"
                            class="mt-4 rounded-xl border border-amber-300/80 bg-amber-50/50 p-4"
                        >
                            <label
                                class="flex cursor-pointer items-center gap-3"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 shrink-0 accent-[var(--brand-green)]"
                                    :checked="chargeMaterialMaster"
                                    @change="toggleMaterialMaster"
                                />
                                <span>
                                    <span
                                        class="text-lg font-semibold text-amber-950"
                                        >教材費</span
                                    >
                                    <span
                                        class="mt-0.5 block text-sm text-amber-900/80"
                                    >
                                        勾選後為各科收上／下半年教材（可同時勾兩段）；金額預設年費一半，可改。
                                    </span>
                                </span>
                            </label>

                            <div
                                v-if="chargeMaterialMaster"
                                class="mt-3 space-y-3"
                            >
                                <div
                                    v-for="course in materialTermCourses"
                                    :key="`term-${course.id}`"
                                    class="rounded-lg border border-amber-200/80 bg-background px-3 py-2.5"
                                >
                                    <div
                                        class="flex flex-wrap items-baseline justify-between gap-2"
                                    >
                                        <p class="font-medium">
                                            {{ course.name }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            年費
                                            {{
                                                course.annual.toLocaleString()
                                            }}｜半年預設
                                            {{ course.semi.toLocaleString() }}
                                        </p>
                                    </div>
                                    <ul class="mt-2 space-y-2">
                                        <li
                                            v-for="half in course.halves"
                                            :key="`${course.id}-${half.periodYear}-${half.half}`"
                                            class="flex flex-wrap items-center gap-3 rounded-md border border-border/70 px-2.5 py-2"
                                            :class="
                                                half.locked
                                                    ? 'opacity-70'
                                                    : ''
                                            "
                                        >
                                            <label
                                                class="inline-flex min-w-[7rem] cursor-pointer items-center gap-2 text-sm"
                                                :class="
                                                    half.locked
                                                        ? 'cursor-not-allowed'
                                                        : ''
                                                "
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="size-3.5 accent-[var(--brand-green)]"
                                                    :checked="half.checked"
                                                    :disabled="half.locked"
                                                    @change="
                                                        onHalfCheckedChange(
                                                            course.id,
                                                            half.periodYear,
                                                            half.half,
                                                            $event,
                                                        )
                                                    "
                                                />
                                                {{ half.periodYear }}／{{
                                                    half.monthsLabel
                                                }}
                                                月
                                            </label>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="1"
                                                class="h-9 w-28 tabular-nums"
                                                :disabled="
                                                    half.locked || !half.checked
                                                "
                                                :model-value="half.amount"
                                                @update:model-value="
                                                    onHalfAmountInput(
                                                        course.id,
                                                        half.periodYear,
                                                        half.half,
                                                        $event,
                                                    )
                                                "
                                            />
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                <template v-if="half.locked">{{
                                                    half.note
                                                }}</template>
                                                <template v-else>{{
                                                    half.periodLabel
                                                }}</template>
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                <div
                                    v-for="row in consumableRows"
                                    :key="`day-${row.id}`"
                                    class="rounded-lg border border-amber-200/80 bg-background px-3 py-2.5"
                                >
                                    <label
                                        class="flex cursor-pointer items-start gap-3"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-1 size-4 shrink-0 accent-[var(--brand-green)]"
                                            :checked="
                                                isChargingConsumable(row.id)
                                            "
                                            @change="toggleConsumable(row.id)"
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span
                                                class="flex flex-wrap items-baseline justify-between gap-2"
                                            >
                                                <span class="font-medium">{{
                                                    row.name
                                                }}</span>
                                                <span
                                                    class="tabular-nums font-semibold text-primary"
                                                >
                                                    {{
                                                        isChargingConsumable(
                                                            row.id,
                                                        )
                                                            ? row.amount.toLocaleString()
                                                            : '—'
                                                    }}
                                                </span>
                                            </span>
                                            <span
                                                class="mt-0.5 block text-sm text-muted-foreground"
                                            >
                                                耗材｜{{ row.amountLabel }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <InputError
                                :message="
                                    form.errors.material_half_charges ||
                                    form.errors.charge_material_course_ids
                                "
                            />
                        </div>

                        <InputError :message="form.errors.sessions" />
                        <InputError :message="form.errors['sessions.0.date']" />
                        <InputError
                            :message="form.errors['sessions.0.course_id']"
                        />
                    </div>
                </div>

                <aside class="xl:sticky xl:top-20 xl:self-start">
                    <div
                        class="rounded-xl border border-primary/25 bg-accent/40 p-5 shadow-sm xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto"
                    >
                        <h2 class="text-lg font-semibold text-primary">
                            試算
                        </h2>
                        <p
                            v-if="isGrade9PackageMode"
                            class="mt-2 rounded-md border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-sm text-emerald-950"
                        >
                            國三全科年繳：120,000（含教材）／6 期 ×
                            20,000；各科該月應收＝20,000 ÷
                            {{ selected.length }} 科。
                        </p>
                        <dl class="mt-3 space-y-2.5 text-base">
                            <div
                                v-if="!isGrade9PackageMode"
                                class="flex justify-between gap-2"
                            >
                                <dt class="text-muted-foreground">多科優惠</dt>
                                <dd class="text-right">
                                    {{
                                        appliedBundleNames.length
                                            ? appliedBundleNames.join('、')
                                            : payCycle === 'monthly'
                                              ? '月繳不適用'
                                              : '無'
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-muted-foreground">帳期月數</dt>
                                <dd>{{ monthCount }} 個月</dd>
                            </div>
                            <div
                                v-if="monthBreakdown.length"
                                class="space-y-2 border-t pt-2"
                            >
                                <dt class="font-medium text-foreground">
                                    各月金額
                                </dt>
                                <dd>
                                    <ul class="space-y-2">
                                        <li
                                            v-for="row in monthBreakdown"
                                            :key="`${row.y}-${row.m}`"
                                            class="rounded-lg border border-sidebar-border/60 bg-background/70 px-3 py-2"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-2 font-medium"
                                            >
                                                <span>{{ row.label }}</span>
                                                <span
                                                    class="tabular-nums text-primary"
                                                >
                                                    {{
                                                        row.subtotal.toLocaleString()
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="mt-1 flex justify-between gap-2 text-sm text-muted-foreground"
                                            >
                                                <span
                                                    >學費
                                                    {{
                                                        row.tuition.toLocaleString()
                                                    }}</span
                                                >
                                                <span
                                                    >教材／耗材
                                                    {{
                                                        row.material.toLocaleString()
                                                    }}</span
                                                >
                                            </div>
                                            <p
                                                v-if="row.materialHalfHint"
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                教材：{{
                                                    row.materialHalfHint
                                                }}（半年一次）
                                            </p>
                                            <p
                                                v-if="row.prorateHints.length"
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                各科該月應收：{{
                                                    row.prorateHints.join('、')
                                                }}
                                            </p>
                                        </li>
                                    </ul>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2 border-t pt-2">
                                <dt class="text-muted-foreground">學費小計</dt>
                                <dd class="tabular-nums">
                                    {{ tuitionTotal.toLocaleString() }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-muted-foreground">
                                    教材／耗材
                                </dt>
                                <dd class="tabular-nums font-medium">
                                    {{ materialTotal.toLocaleString() }}
                                </dd>
                            </div>
                            <ul
                                v-if="materialHalfSummary.length"
                                class="space-y-1 rounded-md border border-dashed border-amber-200/80 bg-amber-50/40 px-2.5 py-2 text-sm"
                            >
                                <li
                                    v-for="row in materialHalfSummary"
                                    :key="row.label"
                                    class="flex justify-between gap-2"
                                >
                                    <span class="min-w-0 truncate">✓ {{
                                        row.label
                                    }}</span>
                                    <span class="shrink-0 tabular-nums">{{
                                        row.amount.toLocaleString()
                                    }}</span>
                                </li>
                            </ul>
                            <div class="grid gap-1 border-t pt-2">
                                <Label for="fee_discount">優惠</Label>
                                <select
                                    id="fee_discount"
                                    class="h-11 rounded-md border px-3 text-base"
                                    :value="selectedDiscountId ?? ''"
                                    @change="
                                        onDiscountChange(
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="">不使用優惠</option>
                                    <option
                                        v-for="d in feeDiscounts"
                                        :key="d.id"
                                        :value="d.id"
                                    >
                                        {{ d.label }}
                                    </option>
                                </select>
                                <p
                                    v-if="feeDiscounts.length === 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    尚無可用優惠（可至設定管理 → 優惠設定新增）
                                </p>
                                <InputError
                                    :message="form.errors.fee_discount_id"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="allowance">折讓金額</Label>
                                <Input
                                    id="allowance"
                                    v-model.number="allowance"
                                    type="number"
                                    min="0"
                                    class="h-11 text-base"
                                    @input="onAllowanceManualInput"
                                />
                                <p
                                    v-if="selectedDiscount"
                                    class="text-xs text-muted-foreground"
                                >
                                    已套用「{{ selectedDiscount.label }}」
                                </p>
                            </div>
                            <div class="grid gap-1.5 border-t pt-2">
                                <Label for="receipt_no">單據編號</Label>
                                <Input
                                    id="receipt_no"
                                    v-model="receiptNo"
                                    type="text"
                                    maxlength="20"
                                    placeholder="選填／手動輸入"
                                    class="h-11 font-mono text-base"
                                />
                                <InputError :message="form.errors.receipt_no" />
                            </div>
                            <div
                                class="flex justify-between gap-2 border-t pt-2 text-lg font-semibold"
                            >
                                <dt>應收合計</dt>
                                <dd class="tabular-nums text-primary">
                                    {{ grandTotal.toLocaleString() }}
                                </dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-sm text-muted-foreground">
                            學費依勾選月份計算；教材費請在左側勾選。
                        </p>
                        <Button
                            class="mt-4 h-11 w-full text-base"
                            type="button"
                            :disabled="
                                form.processing ||
                                selected.length === 0 ||
                                sessions.length === 0
                            "
                            @click="submit"
                        >
                            {{ isEditMode ? '儲存修改' : '確認收款' }}
                        </Button>
                        <InputError class="mt-2" :message="form.errors.sessions" />
                        <p class="mt-2 text-sm text-muted-foreground">
                            產生後即視為已收款，可至
                            <Link
                                :href="`/student-payments/${student.id}`"
                                class="underline"
                                >收款明細</Link
                            >
                            查看。
                        </p>
                    </div>
                </aside>
            </div>
        </template>
    </div>
</template>
