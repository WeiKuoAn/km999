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
    buildDefaultSessionEntries,
    countSessionsForCourse,
    formatWeekdays,
    monthsFromDates,
    proratedMonthTuitionExact,
    uniqueDatesFromSessions,
    type SessionEntry,
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
    fee_plan_id: number | null;
    group_name: string | null;
};

type StudentInfo = {
    id: number;
    student_code: string | null;
    name: string;
    grade_name: string | null;
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
    has_prior_payments?: boolean;
    suggested_start_date?: string | null;
    suggested_course_ids?: number[];
    suggested_pay_cycle?: 'monthly' | 'quarterly' | 'annual' | null;
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
const startDate = ref(
    hasPriorPayments.value && suggestedStartDate.value
        ? suggestedStartDate.value
        : new Date().toISOString().slice(0, 10),
);
const sessions = ref<SessionEntry[]>([]);

/** 依繳別決定預選／可瀏覽堂次月數 */
const sessionMonthSpan = computed(() => {
    switch (payCycle.value) {
        case 'monthly':
            return 1;
        case 'annual':
            return 12;
        default:
            return 3;
    }
});

const sessionMonthSpanLabel = computed(() => {
    switch (payCycle.value) {
        case 'monthly':
            return '一個月';
        case 'annual':
            return '十二個月';
        default:
            return '三個月';
    }
});

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
    sessions.value = buildDefaultSessionEntries(
        startDate.value,
        calendarCourses.value,
        sessionMonthSpan.value,
        holidaySet.value,
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
        refillSessionDates();
    },
);

watch(
    () => [props.suggested_course_ids, props.suggested_pay_cycle] as const,
    () => {
        selected.value = defaultCourseIds();
        payCycle.value = defaultPayCycle();
        refillSessionDates();
    },
);

watch(
    () => [props.has_prior_payments, props.suggested_start_date] as const,
    ([hasPrior, suggested]) => {
        if (hasPrior && suggested) {
            startDate.value = suggested;
        }
    },
);

watch([startDate, selected, payCycle, holidaySet], () => {
    refillSessionDates();
});

if (props.student) {
    refillSessionDates();
}

const form = useForm({
    course_ids: [] as number[],
    pay_cycle: 'quarterly' as 'monthly' | 'quarterly' | 'annual',
    sessions: [] as SessionEntry[],
    allowance: 0,
    start_date: '' as string | null,
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
const monthCount = computed(() => billingMonths.value.length);
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

const monthlyMaterialFee = (annualOrTermFee: number) =>
    annualOrTermFee > 0 ? Math.round(annualOrTermFee / 12) : 0;

const lineMaterial = (s: Subject) => {
    if (!s.material) return 0;
    if (s.material_unit === 'class_day') {
        return countSessionsForCourse(sessions.value, s.id) * s.material;
    }
    return monthlyMaterialFee(s.material) * Math.max(0, monthCount.value);
};

const lineMaterialForMonth = (s: Subject, _y: number, _m: number, _isFirst: boolean) => {
    if (!s.material) return 0;
    if (s.material_unit === 'class_day') {
        return countSessionsInMonth(s.id, _y, _m) * s.material;
    }
    // 教材年費 ÷ 12 = 每月費用；帳期內每個月都收（不因中旬入班打折）
    return monthlyMaterialFee(s.material);
};

const materialHint = (s: Subject): string => {
    if (s.material_unit === 'class_day') {
        const days = countSessionsForCourse(sessions.value, s.id);
        return `耗材 ${s.material.toLocaleString()}/日 × ${days}天＝${lineMaterial(s).toLocaleString()}｜${formatWeekdays(s.weekdays)}`;
    }
    const monthly = monthlyMaterialFee(s.material);
    return `教材 ${s.material.toLocaleString()}（月 ${monthly.toLocaleString()}）`;
};

const tuitionTotal = computed(() =>
    // 與試算「各月金額」一致：每月先加總各科精確學費，再四捨五入後加總
    monthBreakdown.value.reduce((sum, row) => sum + row.tuition, 0),
);

const materialTotal = computed(() =>
    selected.value.reduce((sum, id) => {
        const s = props.subjects.find((x) => x.id === id);
        return s ? sum + lineMaterial(s) : sum;
    }, 0),
);

const monthBreakdown = computed(() => {
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
            (sum, s) => sum + lineMaterialForMonth(s, month.y, month.m, index === 0),
            0,
        );

        const prorateHints = selectedSubjects
            .filter((s) => s.unit !== 'session_block')
            .map((s) => {
                const attended = countSessionsInMonth(s.id, month.y, month.m);
                const baseline = billingBaselineSessions(s.weekdays);
                if (attended <= 0 || attended >= baseline) {
                    return null;
                }
                return `${s.name} ${attended}/${baseline}`;
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
        };
    });
});

const grandTotal = computed(() =>
    Math.max(
        0,
        tuitionTotal.value + materialTotal.value - Number(allowance.value || 0),
    ),
);

const toggleSubject = (id: number) => {
    if (!props.subjects.find((subject) => subject.id === id)?.fee_plan_id) {
        return;
    }

    if (selected.value.includes(id)) {
        selected.value = selected.value.filter((x) => x !== id);
    } else {
        selected.value = [...selected.value, id];
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
    form.post(`/student-payments/${props.student.id}/quote`, {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
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
                            <button
                                v-for="s in subjects"
                                :key="s.id"
                                type="button"
                                class="rounded-lg border px-3 py-2 text-left text-sm transition"
                                :class="[
                                    selected.includes(s.id)
                                        ? ''
                                        : 'hover:border-primary/40',
                                    s.fee_plan_id === null
                                        ? 'cursor-not-allowed opacity-50'
                                        : '',
                                ]"
                                :style="
                                    subjectCardStyle(s, selected.includes(s.id))
                                "
                                :disabled="s.fee_plan_id === null"
                                @click="toggleSubject(s.id)"
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
                            </button>
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
                                    變更後會依已選科目的上課日（如週二、週四）自動預選往後{{
                                        sessionMonthSpanLabel
                                    }}的堂次（依繳別）；可再點行事曆加課／減課。
                                </p>
                                <InputError :message="form.errors.start_date" />
                            </div>
                            <div v-else class="grid gap-1">
                                <p class="text-sm font-medium">帳期起算</p>
                                <p class="text-sm text-muted-foreground">
                                    已有收款紀錄，本帳期自
                                    <span class="font-medium text-foreground tabular-nums">{{
                                        startDate
                                    }}</span>
                                    起算（完整{{ sessionMonthSpanLabel }}）。可再點行事曆加課／減課。
                                </p>
                                <InputError :message="form.errors.start_date" />
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
                                :month-span="sessionMonthSpan"
                                :holidays="holidays ?? []"
                            />
                        </div>
                        <div class="mt-3 rounded-lg border bg-muted/20 p-3">
                            <h3 class="text-sm font-medium">各科已選堂數</h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                起算日前的上課日不計費。基準堂數＝每週上課日數×4（雙天為
                                8）；故中旬入班常會顯示 7/8，並非日曆少算。
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
                        <dl class="mt-3 space-y-2.5 text-base">
                            <div class="flex justify-between gap-2">
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
                                                v-if="row.prorateHints.length"
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                學費比例計價（教材不打折）：{{
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
                                <dd class="tabular-nums">
                                    {{ materialTotal.toLocaleString() }}
                                </dd>
                            </div>
                            <div class="grid gap-1 border-t pt-2">
                                <Label>折讓金額</Label>
                                <Input
                                    v-model.number="allowance"
                                    type="number"
                                    min="0"
                                    class="h-11 text-base"
                                />
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
                            堂，上 3 堂則學費 × 3/8）。教材為年費 ÷ 12，帳期內每月收取，不打折。
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
