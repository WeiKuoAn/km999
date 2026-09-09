<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import SessionDateCalendar from '@/components/SessionDateCalendar.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    uniqueDatesFromSessions,
    type SessionEntry,
    type YearMonth,
} from '@/lib/weekdayDates';
import {
    classroomCalendarSurface,
    normalizeClassroomHex,
} from '@/lib/classroomColor';

type Subject = {
    id: number;
    name: string;
    color: string | null;
    category: string | null;
    pricing_group: string;
    pricing_group_label: string;
    unit: string;
    list: number;
    q_single: number;
    q_double: number;
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

const props = defineProps<{
    student: StudentInfo | null;
    subjects: Subject[];
    warnings: string[];
    holidays?: Array<{ date: string; name: string }>;
    schedule_closures?: Array<{
        date_from: string;
        date_to: string;
        name: string;
        grade_level_id: number | null;
        course_ids: number[];
    }>;
    schedule_makeups?: Array<{
        date_from: string;
        date_to: string;
        name: string;
        grade_level_id: number | null;
        course_ids: number[];
        start_time?: string | null;
        end_time?: string | null;
    }>;
    has_prior_payments?: boolean;
    suggested_start_date?: string | null;
    suggested_course_ids?: number[];
    suggested_pay_cycle?: 'monthly' | 'quarterly' | 'annual' | null;
    material_status?: Array<{
        course_id: number;
        can_charge: boolean;
        amount: number;
        period_year: number;
        period_half: string;
        period_label: string;
        paid_at: string | null;
        note: string | null;
    }>;
    next_receipt_no?: string | null;
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

const holidaySet = computed(
    () => new Set((props.holidays ?? []).map((h) => h.date.slice(0, 10))),
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
const allowance = ref(0);
const selectedDiscountId = ref<number | null>(null);
const startDate = ref(
    hasPriorPayments.value && suggestedStartDate.value
        ? suggestedStartDate.value
        : new Date().toISOString().slice(0, 10),
);
const sessions = ref<SessionEntry[]>([]);
/** 帳期月份勾選（例：8 月不足 + 9–11 季繳連續） */
const selectedMonthKeys = ref<string[]>([]);
/** 本次要收取半年教材的科目（打勾） */
const chargeMaterialIds = ref<number[]>([]);

const materialStatusByCourse = computed(() => {
    const map = new Map<
        number,
        NonNullable<typeof props.material_status>[number]
    >();
    for (const row of props.material_status ?? []) {
        map.set(row.course_id, row);
    }
    return map;
});

const syncDefaultMaterialChecks = () => {
    if (isGrade9PackageMode.value) {
        chargeMaterialIds.value = [];
        return;
    }
    const next: number[] = [];
    for (const id of selected.value) {
        const s = props.subjects.find((x) => x.id === id);
        if (!s || !s.material) {
            continue;
        }
        if (s.material_unit === 'class_day') {
            // 耗材：預設勾選（可取消）
            next.push(id);
            continue;
        }
        const status = materialStatusByCourse.value.get(id);
        // 半年教材：可收且尚未收過本半年 → 預設勾選
        if (!status || status.can_charge) {
            next.push(id);
        }
    }
    chargeMaterialIds.value = next;
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
    chargeMaterialIds.value = [];
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

const monthCheckboxOptions = computed(() => {
    if (!startDate.value) {
        return [];
    }
    return billingMonthOptions(startDate.value, 12).map((m) => ({
        key: monthKey(m.y, m.m),
        label: `${m.m}月`,
        yearLabel: `${m.y}/${m.m}`,
        y: m.y,
        m: m.m,
    }));
});

/** 行事曆可瀏覽月數：至少涵蓋已勾選月份 */
const calendarMonthSpan = computed(() => {
    if (selectedMonths.value.length === 0) {
        return sessionMonthSpan.value;
    }
    const start = startDate.value
        ? new Date(`${startDate.value.slice(0, 10)}T12:00:00`)
        : new Date();
    const last = selectedMonths.value[selectedMonths.value.length - 1];
    const end = new Date(last.y, last.m - 1, 1);
    return Math.max(
        sessionMonthSpan.value,
        (end.getFullYear() - start.getFullYear()) * 12 +
            (end.getMonth() - start.getMonth()) +
            1,
    );
});

const syncSuggestedMonths = () => {
    if (!startDate.value) {
        selectedMonthKeys.value = [];
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
        selectedMonthKeys.value = [...selectedMonthKeys.value, key];
    }
    refillSessionDates();
};

const selectedWeekdays = computed(() => {
    const set = new Set<number>();
    for (const id of selected.value) {
        const s = props.subjects.find((x) => x.id === id);
        for (const d of s?.weekdays ?? []) {
            set.add(d);
        }
    }
    return [...set].sort((a, b) => a - b);
});

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

const sessionCourseSummary = computed(() =>
    calendarCourses.value.map((course) => ({
        ...course,
        count: countSessionsForCourse(sessions.value, course.id),
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
    if (selectedMonthKeys.value.length === 0) {
        syncSuggestedMonths();
    }
    sessions.value = buildDefaultSessionEntries(
        startDate.value,
        calendarCourses.value,
        sessionMonthSpan.value,
        holidaySet.value,
        {
            closures: props.schedule_closures ?? [],
            makeups: props.schedule_makeups ?? [],
            gradeLevelId: props.student?.grade_level_id ?? null,
            months: selectedMonths.value,
        },
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
        syncSuggestedMonths();
        refillSessionDates();
    },
);

watch(
    () => [props.suggested_course_ids, props.suggested_pay_cycle] as const,
    () => {
        selected.value = defaultCourseIds();
        payCycle.value = defaultPayCycle();
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
        chargeMaterialIds.value = [];
        syncSuggestedMonths();
    }
});

watch(
    [selected, holidaySet, () => props.schedule_closures, () => props.schedule_makeups],
    () => {
        refillSessionDates();
        if (isGrade9PackageMode.value) {
            chargeMaterialIds.value = [];
            syncSuggestedMonths();
        }
    },
);

if (props.student) {
    syncSuggestedMonths();
    refillSessionDates();
    syncDefaultMaterialChecks();
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
        chargeMaterialIds.value = chargeMaterialIds.value.filter((id) => {
            const status = materialStatusByCourse.value.get(id);
            return !status || status.can_charge;
        });
    },
);

const form = useForm({
    course_ids: [] as number[],
    pay_cycle: 'quarterly' as 'monthly' | 'quarterly' | 'annual',
    sessions: [] as SessionEntry[],
    allowance: 0,
    start_date: '' as string | null,
    charge_material_course_ids: [] as number[],
    fee_discount_id: null as number | null,
});

const coreCount = computed(
    () =>
        selected.value.filter(
            (id) =>
                props.subjects.find((s) => s.id === id)?.pricing_group ===
                'core',
        ).length,
);

const unitPrice = (s: Subject) => {
    if (payCycle.value === 'monthly') return s.list;
    if (payCycle.value === 'annual') return s.q_double;
    if (s.pricing_group === 'core' && coreCount.value >= 2) return s.q_double;
    return s.q_single;
};

const sessionDates = computed(() => uniqueDatesFromSessions(sessions.value));
const billingMonths = computed(() => monthsFromDates(sessionDates.value));
const monthCount = computed(() =>
    isGrade9PackageMode.value
        ? Math.max(6, selectedMonths.value.length)
        : billingMonths.value.length,
);
const sessionCount = computed(() => sessions.value.length);

const countSessionsInMonth = (courseId: number, y: number, m: number) =>
    sessions.value.filter((entry) => {
        if (entry.course_id !== courseId) return false;
        const [ey, em] = entry.date.slice(0, 10).split('-').map(Number);
        return ey === y && em === m;
    }).length;

const lineTuitionForMonthExact = (s: Subject, y: number, m: number) => {
    const price = unitPrice(s);
    const attended = countSessionsInMonth(s.id, y, m);
    if (s.unit === 'session_block') {
        return 0;
    }
    return proratedMonthTuitionExact(
        price,
        attended,
        billingBaselineSessions(s.weekdays),
    );
};

const lineTuition = (s: Subject) => {
    const price = unitPrice(s);
    if (s.unit === 'session_block') {
        return (
            price * Math.max(1, Math.ceil(Math.max(1, monthCount.value) / 3))
        );
    }
    // 各月：該科精確值加總後再進位會與「多科合計再進位」不一致；
    // 單科小計仍用各月 round(精確值)，月合計另以多科加總後 round。
    return billingMonths.value.reduce((sum, month) => {
        return sum + Math.round(lineTuitionForMonthExact(s, month.y, month.m));
    }, 0);
};

/** 教材年費 ÷ 2；1–6、7–12 各收一次（需打勾） */
const semiAnnualMaterialFee = (annualOrTermFee: number) =>
    annualOrTermFee > 0 ? Math.round(annualOrTermFee / 2) : 0;

const isChargingMaterial = (courseId: number) =>
    chargeMaterialIds.value.includes(courseId);

const toggleMaterialCharge = (courseId: number) => {
    const status = materialStatusByCourse.value.get(courseId);
    if (status && !status.can_charge) {
        return;
    }
    if (chargeMaterialIds.value.includes(courseId)) {
        chargeMaterialIds.value = chargeMaterialIds.value.filter(
            (id) => id !== courseId,
        );
    } else {
        chargeMaterialIds.value = [...chargeMaterialIds.value, courseId];
    }
};

const lineMaterial = (s: Subject) => {
    if (!s.material) return 0;
    if (!isChargingMaterial(s.id)) {
        return 0;
    }
    if (s.material_unit === 'class_day') {
        return countSessionsForCourse(sessions.value, s.id) * s.material;
    }
    return semiAnnualMaterialFee(s.material);
};

/** 半年教材掛在帳期第一個月；耗材仍按月 */
const lineMaterialForMonth = (s: Subject, y: number, m: number) => {
    if (!s.material || !isChargingMaterial(s.id)) return 0;
    if (s.material_unit === 'class_day') {
        return countSessionsInMonth(s.id, y, m) * s.material;
    }
    if (billingMonths.value.length === 0) {
        return 0;
    }
    const first = billingMonths.value[0];
    if (first.y !== y || first.m !== m) {
        return 0;
    }
    return semiAnnualMaterialFee(s.material);
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

/** 已選科目中可勾選教材／耗材的列 */
const materialChargeRows = computed(() => {
    if (isGrade9PackageMode.value) {
        return [];
    }
    const rows: Array<{
        id: number;
        name: string;
        unit: string;
        locked: boolean;
        note: string | null;
        periodLabel: string | null;
        amountLabel: string;
        amount: number;
    }> = [];
    for (const id of selected.value) {
        const s = props.subjects.find((x) => x.id === id);
        if (!s || !s.material) {
            continue;
        }
        if (s.material_unit === 'class_day') {
            const days = countSessionsForCourse(sessions.value, s.id);
            const amount = days * s.material;
            rows.push({
                id: s.id,
                name: s.name,
                unit: 'class_day',
                locked: false,
                note: null,
                periodLabel: null,
                amountLabel: `${s.material.toLocaleString()}/日 × ${days}天`,
                amount,
            });
            continue;
        }
        const status = materialStatusByCourse.value.get(s.id);
        const semi = semiAnnualMaterialFee(s.material);
        const locked = !!(status && !status.can_charge);
        rows.push({
            id: s.id,
            name: s.name,
            unit: 'term',
            locked,
            note: status?.note ?? null,
            periodLabel: status?.period_label ?? null,
            amountLabel: `半年 ${semi.toLocaleString()}`,
            amount: semi,
        });
    }
    return rows;
});

const materialHalfSummary = computed(() => {
    const rows: Array<{ label: string; amount: number }> = [];
    for (const id of selected.value) {
        const s = props.subjects.find((x) => x.id === id);
        if (!s || !s.material || s.material_unit === 'class_day') {
            continue;
        }
        if (!isChargingMaterial(id)) {
            continue;
        }
        const status = materialStatusByCourse.value.get(id);
        const label = status?.period_label
            ? `${s.name}｜${status.period_label}`
            : `${s.name}｜半年教材`;
        rows.push({ label, amount: semiAnnualMaterialFee(s.material) });
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

    const blockSubjects = selectedSubjects.filter((s) => s.unit === 'session_block');
    const blockTuitionTotal = blockSubjects.reduce((sum, s) => {
        const price = unitPrice(s);
        return (
            sum +
            price * Math.max(1, Math.ceil(Math.max(1, monthCount.value) / 3))
        );
    }, 0);
    const blockMonths = Math.max(1, monthCount.value);
    const blockPerMonth = Math.floor(blockTuitionTotal / blockMonths);
    const blockRem = blockTuitionTotal % blockMonths;

    return billingMonths.value.map((month, index) => {
        const exactTuition = selectedSubjects
            .filter((s) => s.unit !== 'session_block')
            .reduce(
                (sum, s) => sum + lineTuitionForMonthExact(s, month.y, month.m),
                0,
            );
        let tuition = Math.round(exactTuition);
        tuition += blockPerMonth + (index === 0 ? blockRem : 0);

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
                    isChargingMaterial(s.id),
            )
                ? '含半年教材'
                : null;

        const prorateHints = selectedSubjects
            .filter((s) => s.unit !== 'session_block')
            .map((s) => {
                const attended = countSessionsInMonth(s.id, month.y, month.m);
                const baseline = billingBaselineSessions(s.weekdays);
                if (attended <= 0 || attended >= baseline) {
                    return null;
                }
                const exact = lineTuitionForMonthExact(s, month.y, month.m);
                return `${s.name} ${attended}/${baseline}＝${Math.round(exact).toLocaleString()}`;
            })
            .filter((hint): hint is string => hint !== null);

        return {
            y: month.y,
            m: month.m,
            label: `${month.y}/${month.m}`,
            tuition,
            material,
            subtotal: tuition + material,
            prorateHints,
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
        chargeMaterialIds.value = chargeMaterialIds.value.filter((x) => x !== id);
    } else {
        selected.value = [...selected.value, id];
        const s = props.subjects.find((x) => x.id === id);
        const status = materialStatusByCourse.value.get(id);
        if (
            s &&
            s.material > 0 &&
            (s.material_unit === 'class_day' ||
                !status ||
                status.can_charge)
        ) {
            if (!chargeMaterialIds.value.includes(id)) {
                chargeMaterialIds.value = [...chargeMaterialIds.value, id];
            }
        }
    }
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
    router.get(
        '/student-payments/create',
        {},
        { preserveState: false, replace: true },
    );
};

const submit = () => {
    if (!props.student) {
        return;
    }
    form.course_ids = [...selected.value];
    form.pay_cycle = payCycle.value;
    form.sessions = [...sessions.value];
    form.allowance = Number(allowance.value || 0);
    form.start_date = startDate.value || null;
    form.charge_material_course_ids = isGrade9PackageMode.value
        ? []
        : chargeMaterialIds.value.filter((id) =>
              selected.value.includes(id),
          );
    form.fee_discount_id = selectedDiscountId.value;
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
    <Head title="新增收款" />

    <div class="page-shell w-full text-base [&_.text-xs]:text-sm [&_.text-sm]:text-base">
        <div class="mb-2">
            <Link
                href="/student-payments"
                class="text-base text-primary underline-offset-4 hover:underline"
            >
                ← 返回明細紀錄
            </Link>
        </div>

        <PageHeader
            title="新增收款／報名計價"
            description="先輸入學生，系統依年級自動帶入可報名科目與價目；以行事曆選上課日後確認收款並產生帳期。"
        />

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
            <div
                class="rounded-xl border border-dashed bg-card p-10 text-center text-sm text-muted-foreground"
            >
                請先在上方選擇學生，再進行科目勾選與報名計價。
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
                            尚無可報名課目。請確認學生年級與收費標準的適用課目。
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
                                    {{ s.pricing_group_label }}
                                    <span v-if="s.group_name"
                                        >｜{{ s.group_name }}</span
                                    >
                                    <span v-else>｜尚未設定適用價目</span>
                                    <span v-if="s.weekdays?.length"
                                        >｜{{
                                            formatWeekdays(s.weekdays)
                                        }}</span
                                    >
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    定價 {{ s.list.toLocaleString() }}｜季繳
                                    {{ s.q_single.toLocaleString() }}／雙
                                    {{ s.q_double.toLocaleString() }}｜{{
                                        materialHint(s)
                                    }}
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
                                <p class="text-xs text-muted-foreground">
                                    決定第一個月從哪天開始計堂。例如 8
                                    月底入班，只會算當月剩餘堂次（價 ×
                                    堂數／基準堂數）。
                                </p>
                                <InputError :message="form.errors.start_date" />
                            </div>
                            <div v-else class="grid gap-1">
                                <p class="text-sm font-medium">帳期起算</p>
                                <p class="text-sm text-muted-foreground">
                                    已有收款紀錄，本帳期自
                                    <span
                                        class="font-medium text-foreground tabular-nums"
                                        >{{ startDate }}</span
                                    >
                                    起算。可再勾選月份或點行事曆加課／減課。
                                </p>
                                <InputError :message="form.errors.start_date" />
                            </div>
                        </div>

                        <div class="mt-4 grid gap-2">
                            <Label>帳期月份（可勾選）</Label>
                            <p
                                v-if="isGrade9PackageMode"
                                class="text-xs text-muted-foreground"
                            >
                                國三全科年繳固定 7–12 月共 6
                                期，每月合計 20,000（20,000 ÷
                                科目數寫入各科該月應收）。
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                依繳別預勾連續月，可自行增減。例：8
                                月底入班可勾 8、9、10、11——8
                                月按實際上課堂次比例計，9–11 收整月季繳價；同一筆收完。
                            </p>
                            <div class="flex flex-wrap gap-2">
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

                        <h2 class="mt-4 text-base font-semibold">
                            上課日行事曆
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            預設星期：{{
                                formatWeekdays(selectedWeekdays)
                            }}｜已涵蓋帳期月數 {{ monthCount }}｜選中
                            {{ sessionCount }} 堂
                        </p>
                        <div class="mt-3">
                            <SessionDateCalendar
                                v-model="sessions"
                                :start-date="startDate"
                                :courses="calendarCourses"
                                :month-span="calendarMonthSpan"
                                :holidays="holidays ?? []"
                                :closures="schedule_closures ?? []"
                                :grade-level-id="student?.grade_level_id ?? null"
                            />
                        </div>
                        <div class="mt-3 rounded-lg border bg-muted/20 p-3">
                            <h3 class="text-sm font-medium">各科已選堂數</h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                起算日前的上課日不計費。不足整月：學費＝該科月費 ×
                                堂數／基準（雙天為 8，例：上 2 堂＝月費 × 2/8）。
                            </p>
                            <ul class="mt-2 flex flex-col gap-1.5 text-sm">
                                <li
                                    v-for="course in sessionCourseSummary"
                                    :key="course.id"
                                    class="flex items-center justify-between gap-3 rounded-md bg-background px-3 py-2"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <span
                                            class="inline-block size-2.5 shrink-0 rounded-sm border border-black/10"
                                            :style="{
                                                backgroundColor:
                                                    normalizeClassroomHex(
                                                        course.color,
                                                    ),
                                            }"
                                        />
                                        <span class="truncate">{{
                                            course.name
                                        }}</span>
                                    </span>
                                    <span
                                        class="shrink-0 font-semibold tabular-nums"
                                        >{{ course.count }} 堂</span
                                    >
                                </li>
                            </ul>
                        </div>

                        <div
                            v-if="materialChargeRows.length > 0"
                            class="mt-4 rounded-xl border border-amber-300/80 bg-amber-50/50 p-4"
                        >
                            <h2 class="text-lg font-semibold text-amber-950">
                                教材／耗材
                            </h2>
                            <p class="mt-1 text-sm text-amber-900/80">
                                請確認本次是否收取。半年教材（1–6／7–12）同一半年只收一次；耗材依上課日計算。
                            </p>
                            <ul class="mt-3 space-y-2">
                                <li
                                    v-for="row in materialChargeRows"
                                    :key="row.id"
                                    class="rounded-lg border border-amber-200/80 bg-background px-3 py-2.5"
                                >
                                    <label
                                        class="flex cursor-pointer items-start gap-3"
                                        :class="
                                            row.locked
                                                ? 'cursor-not-allowed opacity-80'
                                                : ''
                                        "
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-1 size-4 shrink-0 accent-[var(--brand-green)]"
                                            :checked="
                                                isChargingMaterial(row.id)
                                            "
                                            :disabled="row.locked"
                                            @change="
                                                toggleMaterialCharge(row.id)
                                            "
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
                                                        row.locked ||
                                                        !isChargingMaterial(
                                                            row.id,
                                                        )
                                                            ? '—'
                                                            : row.amount.toLocaleString()
                                                    }}
                                                </span>
                                            </span>
                                            <span
                                                class="mt-0.5 block text-sm text-muted-foreground"
                                            >
                                                <template v-if="row.locked">
                                                    {{ row.note }}
                                                </template>
                                                <template
                                                    v-else-if="
                                                        row.unit === 'class_day'
                                                    "
                                                >
                                                    耗材｜{{ row.amountLabel }}
                                                </template>
                                                <template v-else>
                                                    半年教材｜{{
                                                        row.amountLabel
                                                    }}{{
                                                        row.periodLabel
                                                            ? `｜${row.periodLabel}`
                                                            : ''
                                                    }}
                                                </template>
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            </ul>
                            <InputError
                                :message="
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
                                <dt class="text-muted-foreground">
                                    同組核心科數
                                </dt>
                                <dd>{{ coreCount }}（影響雙科價）</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-muted-foreground">選中堂次</dt>
                                <dd>{{ sessionCount }} 堂</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-muted-foreground">帳期月數</dt>
                                <dd>{{ monthCount }}</dd>
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
                                                {{
                                                    isGrade9PackageMode
                                                        ? '各科該月應收：'
                                                        : '學費比例計價：'
                                                }}{{
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
                                v-if="materialChargeRows.length"
                                class="space-y-1 rounded-md border border-dashed border-amber-200/80 bg-amber-50/40 px-2.5 py-2 text-sm"
                            >
                                <li
                                    v-for="row in materialChargeRows"
                                    :key="`sum-${row.id}`"
                                    class="flex justify-between gap-2"
                                    :class="
                                        isChargingMaterial(row.id) && !row.locked
                                            ? 'text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <span class="min-w-0 truncate">
                                        {{
                                            isChargingMaterial(row.id) &&
                                            !row.locked
                                                ? '✓'
                                                : '○'
                                        }}
                                        {{ row.name }}
                                    </span>
                                    <span class="shrink-0 tabular-nums">{{
                                        isChargingMaterial(row.id) && !row.locked
                                            ? row.amount.toLocaleString()
                                            : row.locked
                                              ? '已收'
                                              : '未收'
                                    }}</span>
                                </li>
                            </ul>
                            <ul
                                v-if="materialHalfSummary.length"
                                class="space-y-1 border-t border-dashed pt-2 text-sm text-muted-foreground"
                            >
                                <li
                                    v-for="row in materialHalfSummary"
                                    :key="row.label"
                                    class="flex justify-between gap-2"
                                >
                                    <span>{{ row.label }}</span>
                                    <span class="tabular-nums">{{
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
                                    尚無可用優惠（可至設定管理 → 優惠管理新增）
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
                            <div
                                class="flex justify-between gap-2 border-t pt-2 text-sm"
                            >
                                <dt class="text-muted-foreground">單據編號</dt>
                                <dd class="font-mono tabular-nums">
                                    {{ next_receipt_no ?? '確認後產生' }}
                                </dd>
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
                            僅學費按比例：基準堂數＝每週上課日數 × 4（例：雙天 8
                            堂，上 3 堂則學費 × 3/8）。教材為年費 ÷ 2，帳期碰到的半年（1–6／7–12）各收一次，不拆月。
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
                            確認收款並產生帳期
                        </Button>
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
