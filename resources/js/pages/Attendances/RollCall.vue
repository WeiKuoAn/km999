<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCourseCellParts, formatDurationHours } from '@/lib/courseLabel';
import AttendanceStatusSelect from '@/components/AttendanceStatusSelect.vue';
import { Trash2 } from 'lucide-vue-next';
import { attendanceStatusBadgeClass, rollCallStatusLabel } from '@/lib/attendanceStatus';

type Row = {
    attendance_id: number | null;
    student_id: number | null;
    student_name: string;
    student_phone: string | null;
    from_enrollment: boolean;
    /** 離班學生的歷史點名列，不可從點名表移除 */
    protected?: boolean;
    status: string;
    note: string;
    makeup_date: string;
    makeup_for_classroom_id: number | null;
    row_key: number;
};

type StudentOption = { id: number; name: string; phone: string | null };
type MakeupClassOption = { classroom_id: number; label: string };

const props = defineProps<{
    classroom: {
        id: number;
        name: string;
        course: {
            id: number;
            course_category: { name: string };
            name: string;
            course_prices: Array<{ level: string | null; duration_hours?: number; tuition: number }>;
        };
        teacher: { id: number; name: string } | null;
    };
    classDate: string;
    classDateWeekdayLabel: string;
    scheduleForDate: Array<{
        weekday: number;
        start_time: string;
        end_time: string;
    }>;
    rows: Omit<Row, 'row_key'>[];
    studentsForAdd: StudentOption[];
    /** 每位學生在籍的課程選項（key 為 student_id），補課／加課時用來選班級 */
    makeupClassOptions: Record<number, MakeupClassOption[]>;
    /** 每位學生在各班的請假日期：leaveDates[student_id][classroom_id] = ['Y-m-d', ...] */
    leaveDates: Record<number, Record<number, string[]>>;
    /** 此日已完成點名（在籍皆已存出勤），僅能查看 */
    readonly: boolean;
    /** 返回線上點名首頁的網址（保留原本篩選） */
    backUrl: string;
    /** 此課程可選的計費時數（來自價目表） */
    durationOptions: number[];
    /** 今日已選／預設時數 */
    durationHours: number | null;
    /** 是否需老師在點名時選擇時數 */
    requiresDurationChoice: boolean;
}>();

const courseCell = computed(() => formatCourseCellParts(props.classroom.course));

let rowSeq = 0;
const nextRowKey = () => {
    rowSeq += 1;

    return rowSeq;
};

const normalizeRollStatus = (s: string) =>
    s === 'present' || s === 'excused' || s === 'makeup' || s === 'extra' ? s : 'present';

const form = useForm({
    class_date: props.classDate,
    duration_hours: props.durationHours as number | null,
    entries: props.rows.map((r) => ({
        attendance_id: r.attendance_id ?? null,
        student_id: r.student_id as number | null,
        student_name: r.student_name,
        student_phone: r.student_phone,
        from_enrollment: r.from_enrollment,
        protected: r.protected ?? false,
        status: normalizeRollStatus(r.status),
        note: r.note ?? '',
        makeup_date: r.makeup_date ?? '',
        makeup_for_classroom_id: r.makeup_for_classroom_id ?? null,
        row_key: nextRowKey(),
    })),
});

const classDateInput = ref(props.classDate);
const localError = ref('');

// 已完成點名預設唯讀；按「編輯點名」可解鎖，用於補登漏記或修正錯誤。
const editing = ref(false);
const locked = computed(() => props.readonly && !editing.value);

const selectedStudentIds = computed(() =>
    form.entries.map((e) => e.student_id).filter((id): id is number => typeof id === 'number' && id > 0),
);

const optionsForRow = (row: Row) =>
    props.studentsForAdd.filter((s) => s.id === row.student_id || !selectedStudentIds.value.includes(s.id));

const formatHm = (t: string | null): string => {
    if (!t) {
        return '';
    }
    const [h, m] = t.split(':');

    return `${h}:${(m ?? '00').slice(0, 2)}`;
};

const scheduleForDateText = computed(() =>
    props.scheduleForDate.length
        ? props.scheduleForDate.map((s) => `${formatHm(s.start_time)} — ${formatHm(s.end_time)}`).join('、')
        : '未設定時段',
);

/** 所選日期與班級排課不符（常見於忘記改點名日期）。 */
const scheduleDateMismatch = computed(() => props.scheduleForDate.length === 0);

const changeDate = (event: Event) => {
    const el = event.target as HTMLInputElement;
    if (!el.value) {
        return;
    }
    classDateInput.value = el.value;
    router.get(`/attendances/classrooms/${props.classroom.id}/roll-call`, { date: el.value, return: props.backUrl });
};

const setStudentOnRow = (row: Row, studentId: number | null) => {
    if (studentId === null) {
        row.student_id = null;
        row.student_name = '';
        row.student_phone = null;
        return;
    }
    const s = props.studentsForAdd.find((x) => x.id === studentId);
    if (!s) {
        return;
    }
    row.student_id = s.id;
    row.student_name = s.name;
    row.student_phone = s.phone;
    // 換學生後，補課／加課班級需重新對應到新學生的在籍課程。
    const opts = props.makeupClassOptions[s.id] ?? [];
    row.makeup_for_classroom_id = opts.length === 1 ? opts[0].classroom_id : null;
    if (row.status === 'makeup') {
        resolveMakeupTime(row);
    }
};

/** 該學生在籍的課程選項（補課／加課時選班級）。 */
const makeupOptionsFor = (studentId: number | null): MakeupClassOption[] =>
    studentId !== null ? (props.makeupClassOptions[studentId] ?? []) : [];

/** 唯讀檢視時顯示補課／加課班級的名稱。 */
const makeupClassLabelFor = (entry: { student_id: number | null; makeup_for_classroom_id: number | null }): string => {
    const found = makeupOptionsFor(entry.student_id).find((o) => o.classroom_id === entry.makeup_for_classroom_id);

    return found?.label ?? '—';
};

/** 該學生在指定班級的請假日期（補課時可直接選被補的那一天）。 */
const leaveDatesFor = (studentId: number | null, classroomId: number | null): string[] => {
    if (studentId === null || classroomId === null) {
        return [];
    }

    return props.leaveDates[studentId]?.[classroomId] ?? [];
};

/** 補課時間下拉選項：該生在該班的請假日（保留目前已選值）。 */
const makeupDateOptions = (entry: { student_id: number | null; makeup_for_classroom_id: number | null; makeup_date: string }): string[] => {
    const leaves = leaveDatesFor(entry.student_id, entry.makeup_for_classroom_id);
    if (entry.makeup_date && !leaves.includes(entry.makeup_date)) {
        return [entry.makeup_date, ...leaves];
    }

    return leaves;
};

/** 依目前補課班級重新對應補課時間：預設帶最近一筆請假日，沒有請假則留空。 */
const resolveMakeupTime = (row: Row) => {
    if (row.makeup_for_classroom_id === null) {
        return;
    }
    const leaves = leaveDatesFor(row.student_id, row.makeup_for_classroom_id);
    if (!leaves.includes(row.makeup_date)) {
        row.makeup_date = leaves.length > 0 ? leaves[0] : '';
    }
};

/** 補課班級改變時，重新對應該班的請假日期。 */
const onMakeupClassChanged = (row: Row) => {
    if (row.status === 'makeup') {
        resolveMakeupTime(row);
    }
};

/** 狀態改變時依新狀態調整補課／加課所需欄位。 */
const onStatusChanged = (row: Row) => {
    const opts = makeupOptionsFor(row.student_id);
    if (row.status === 'makeup') {
        if (row.makeup_for_classroom_id === null && opts.length === 1) {
            row.makeup_for_classroom_id = opts[0].classroom_id;
        }
        resolveMakeupTime(row);
        return;
    }
    if (row.status === 'extra') {
        if (row.makeup_for_classroom_id === null && opts.length === 1) {
            row.makeup_for_classroom_id = opts[0].classroom_id;
        }
        row.makeup_date = '';
        return;
    }
    row.makeup_for_classroom_id = null;
    row.makeup_date = '';
};

const addGuestStudent = () => {
    if (locked.value) {
        return;
    }
    localError.value = '';
    form.entries.push({
        attendance_id: null,
        student_id: null,
        student_name: '',
        student_phone: null,
        from_enrollment: false,
        status: 'makeup',
        note: '',
        makeup_date: '',
        makeup_for_classroom_id: null,
        row_key: nextRowKey(),
    });
};

const removeRow = (index: number) => {
    const entry = form.entries[index];
    if (entry?.from_enrollment || entry?.protected) {
        return;
    }
    form.entries.splice(index, 1);
};

const submit = () => {
    if (locked.value) {
        return;
    }
    localError.value = '';

    const invalid = form.entries.find((e) => !e.from_enrollment && (e.student_id === null || e.student_id <= 0));
    if (invalid) {
        localError.value = '補課／加課學生列請先選擇學生，再儲存出勤。';
        return;
    }
    const missingMakeupClass = form.entries.find(
        (e) => e.status === 'makeup' && (e.makeup_for_classroom_id === null || e.makeup_for_classroom_id <= 0),
    );
    if (missingMakeupClass) {
        localError.value = '狀態為「補課」時，請選擇「補課班級」。';
        return;
    }
    const missingMakeupDate = form.entries.find((e) => e.status === 'makeup' && e.makeup_date === '');
    if (missingMakeupDate) {
        localError.value = '狀態為「補課」時，請選擇或填寫補課時間。';
        return;
    }
    const missingExtraClass = form.entries.find(
        (e) => e.status === 'extra' && (e.makeup_for_classroom_id === null || e.makeup_for_classroom_id <= 0),
    );
    if (missingExtraClass) {
        localError.value = '狀態為「加課」時，請選擇「加課班級」。';
        return;
    }
    if (props.requiresDurationChoice && (form.duration_hours === null || form.duration_hours === undefined)) {
        localError.value = '請選擇今日時數。';
        return;
    }

    form.transform((data) => ({
        class_date: data.class_date,
        duration_hours: props.requiresDurationChoice ? data.duration_hours : null,
        entries: data.entries
            .filter((e) => e.student_id !== null && e.student_id > 0)
            .map((e) => {
                if (e.status === 'extra') {
                    return {
                        student_id: e.student_id,
                        status: e.status,
                        note: e.note || null,
                        makeup_for_classroom_id: e.makeup_for_classroom_id,
                    };
                }
                if (e.status !== 'makeup' || !e.makeup_date) {
                    return {
                        student_id: e.student_id,
                        status: e.status,
                        note: e.note || null,
                    };
                }
                // 補課時間 = 被補的原請假日；實際補課當天 = 點名日期。
                const missed = e.makeup_date;
                const attended = data.class_date;
                const note =
                    missed === attended
                        ? `補課日期:${missed}`
                        : `補課日期:${missed} 補課已排:${attended}`;

                return {
                    student_id: e.student_id,
                    status: e.status,
                    note,
                    makeup_for_classroom_id: e.makeup_for_classroom_id,
                };
            }),
    })).post(
        `/attendances/classrooms/${props.classroom.id}/day?return=${encodeURIComponent(props.backUrl)}`,
    );
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '線上點名', href: '/attendances' },
            { title: '出勤', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`${locked ? '查看出勤' : '點名'} — ${classroom.name}`" />

    <div class="page-shell mx-auto w-full max-w-4xl space-y-6">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">{{ locked ? '查看出勤' : '點名' }}</h1>
                <p class="text-sm text-muted-foreground">
                    <Link :href="backUrl" class="text-primary underline-offset-4 hover:underline">返回線上點名首頁</Link>
                </p>
            </div>
            <div class="page-header__actions w-full sm:w-auto">
                <div class="grid w-full gap-1 sm:w-auto">
                    <Label for="roll_date">上課日期</Label>
                    <Input
                        id="roll_date"
                        v-model="classDateInput"
                        type="date"
                        class="h-10 w-full"
                        @change="changeDate"
                    />
                </div>
            </div>
        </div>

        <p
            v-if="scheduleDateMismatch"
            class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-950 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-50"
        >
            所選日期（{{ classDateWeekdayLabel }} {{ classDate }}）與此班級排課不符。若為補登週六班等過往上課日，請先將右上角「上課日期」改為正確日期再儲存。
        </p>
        <p
            v-if="locked"
            class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-50"
        >
            此日已完成點名。如需補登漏記的學生或修正狀態，請按「編輯點名」。
        </p>
        <p
            v-else-if="readonly"
            class="rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm text-sky-950 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-50"
        >
            編輯模式：可修改狀態、新增補課學生，完成後請按「儲存出勤」。
        </p>

        <section class="space-y-2 rounded-xl border p-4">
            <h2 class="text-lg font-semibold">課程資訊</h2>
            <dl class="grid gap-2 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">班級</dt>
                    <dd class="font-medium">{{ classroom.name }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">課程</dt>
                    <dd>
                        <span class="block font-medium">{{ courseCell.title }}</span>
                        <span
                            v-for="(line, li) in courseCell.tierLines"
                            :key="li"
                            class="mt-0.5 block text-muted-foreground"
                        >
                            {{ line }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">老師</dt>
                    <dd>{{ classroom.teacher?.name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">節次</dt>
                    <dd>
                        {{ classDateWeekdayLabel }} {{ classDate }} · {{ scheduleForDateText }}
                    </dd>
                </div>
                <div v-if="requiresDurationChoice" class="md:col-span-2">
                    <dt class="text-muted-foreground">今日時數</dt>
                    <dd class="mt-1">
                        <select
                            v-model.number="form.duration_hours"
                            class="h-9 w-full max-w-xs rounded-md border px-3 text-sm"
                            :disabled="locked"
                        >
                            <option :value="null" disabled>請選擇</option>
                            <option v-for="d in durationOptions" :key="d" :value="d">
                                {{ formatDurationHours(d) }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-muted-foreground">
                            此課程有多種時數方案，請依今日實際上課時數選擇（影響學費袋計費）。
                        </p>
                    </dd>
                </div>
            </dl>
        </section>

        <form class="space-y-4" @submit.prevent="submit">
            <section class="space-y-3 rounded-xl border p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">學生出勤</h2>
                    <Button v-if="locked" type="button" variant="outline" size="sm" @click="editing = true">編輯點名</Button>
                    <Button v-else type="button" variant="outline" size="sm" @click="addGuestStudent">新增學生</Button>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ locked ? '以下為已儲存的出勤紀錄。' : '在籍學生不可移除；新增列為補課／加課生，可指定班級。' }}
                </p>

                <div class="space-y-2 md:hidden">
                    <div v-for="(entry, index) in form.entries" :key="`m-${entry.row_key}`" class="rounded-md border p-3 space-y-2">
                        <div class="flex items-center gap-2 font-medium">
                            <template v-if="entry.from_enrollment">
                                <span class="grow">{{ entry.student_name }}</span>
                            </template>
                            <template v-else>
                                <select
                                    :value="entry.student_id ?? ''"
                                    class="h-9 w-full grow rounded-md border px-2"
                                    :disabled="locked"
                                    @change="setStudentOnRow(entry, ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                                >
                                    <option value="">請選擇</option>
                                    <option v-for="s in optionsForRow(entry)" :key="s.id" :value="s.id">
                                        {{ s.name }}
                                    </option>
                                </select>
                            </template>
                            <Button
                                v-if="!locked && !entry.from_enrollment && !entry.protected"
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8 shrink-0 text-destructive hover:text-destructive"
                                title="移除補課學生"
                                @click="removeRow(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <div class="text-sm text-muted-foreground">電話：{{ entry.student_phone ?? '-' }}</div>
                        <div>
                            <span
                                v-if="locked"
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="attendanceStatusBadgeClass(entry.status)"
                            >
                                {{ rollCallStatusLabel(entry.status) }}
                            </span>
                            <AttendanceStatusSelect v-else v-model="entry.status" @update:model-value="onStatusChanged(entry)" />
                        </div>
                        <!-- 補課：補課班級 + 補課時間（該生在該班的請假日） -->
                        <template v-if="entry.status === 'makeup'">
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-xs text-muted-foreground">補課班級</span>
                                <select
                                    v-if="!locked"
                                    v-model.number="entry.makeup_for_classroom_id"
                                    class="h-9 w-full grow rounded-md border bg-background px-2"
                                    @change="onMakeupClassChanged(entry)"
                                >
                                    <option :value="null">請選擇</option>
                                    <option v-for="o in makeupOptionsFor(entry.student_id)" :key="o.classroom_id" :value="o.classroom_id">
                                        {{ o.label }}
                                    </option>
                                </select>
                                <span v-else class="text-sm">{{ makeupClassLabelFor(entry) }}</span>
                            </div>
                            <div v-if="entry.makeup_for_classroom_id" class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-xs text-muted-foreground">補課時間</span>
                                <select
                                    v-if="!locked"
                                    v-model="entry.makeup_date"
                                    class="h-9 w-full grow rounded-md border bg-background px-2"
                                >
                                    <option value="" disabled>{{ makeupDateOptions(entry).length ? '請選擇請假日' : '無請假紀錄' }}</option>
                                    <option v-for="d in makeupDateOptions(entry)" :key="d" :value="d">{{ d }}</option>
                                </select>
                                <span v-else class="text-sm tabular-nums text-muted-foreground">{{ entry.makeup_date || '—' }}</span>
                            </div>
                        </template>
                        <!-- 加課：加課班級 -->
                        <div v-else-if="entry.status === 'extra'" class="flex items-center gap-2">
                            <span class="w-16 shrink-0 text-xs text-muted-foreground">加課班級</span>
                            <select
                                v-if="!locked"
                                v-model.number="entry.makeup_for_classroom_id"
                                class="h-9 w-full grow rounded-md border bg-background px-2"
                            >
                                <option :value="null">請選擇</option>
                                <option v-for="o in makeupOptionsFor(entry.student_id)" :key="o.classroom_id" :value="o.classroom_id">
                                    {{ o.label }}
                                </option>
                            </select>
                            <span v-else class="text-sm">{{ makeupClassLabelFor(entry) }}</span>
                        </div>
                        <!-- 其他狀態：備註 -->
                        <div v-else class="flex items-center gap-2">
                            <span v-if="locked" class="text-sm text-muted-foreground">{{ entry.note || '—' }}</span>
                            <Input v-else v-model="entry.note" class="h-9 w-full" placeholder="備註（選填）" />
                        </div>
                    </div>
                    <div v-if="form.entries.length === 0" class="rounded-md border p-6 text-center text-sm text-muted-foreground">
                        此班尚無在籍學生，請用右上角「新增學生」建立補課列。
                    </div>
                </div>

                <div class="hidden overflow-x-auto rounded-md border md:block">
                    <table class="w-full min-w-[42rem] text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th class="px-3 py-2 text-left">姓名</th>
                                <th class="px-3 py-2 text-left">電話</th>
                                <th class="px-3 py-2 text-left">狀態</th>
                                <th class="px-3 py-2 text-left">備註 / 補課・加課</th>
                                <th class="px-3 py-2 text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(entry, index) in form.entries" :key="entry.row_key" class="border-b">
                                <td class="px-3 py-2">
                                    <template v-if="entry.from_enrollment">
                                        {{ entry.student_name }}
                                    </template>
                                    <template v-else>
                                        <select
                                            :value="entry.student_id ?? ''"
                                            class="h-9 min-w-[180px] rounded-md border px-2"
                                            :disabled="locked"
                                            @change="setStudentOnRow(entry, ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                                        >
                                            <option value="">請選擇</option>
                                            <option v-for="s in optionsForRow(entry)" :key="s.id" :value="s.id">
                                                {{ s.name }}
                                            </option>
                                        </select>
                                    </template>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">{{ entry.student_phone ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        v-if="locked"
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="attendanceStatusBadgeClass(entry.status)"
                                    >
                                        {{ rollCallStatusLabel(entry.status) }}
                                    </span>
                                    <AttendanceStatusSelect
                                        v-else
                                        v-model="entry.status"
                                        trigger-class="min-w-[7rem]"
                                        @update:model-value="onStatusChanged(entry)"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <div v-if="entry.status === 'makeup'" class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-16 shrink-0 text-xs text-muted-foreground">補課班級</span>
                                            <select
                                                v-if="!locked"
                                                v-model.number="entry.makeup_for_classroom_id"
                                                class="h-9 w-full min-w-0 flex-1 rounded-md border bg-background px-2"
                                                @change="onMakeupClassChanged(entry)"
                                            >
                                                <option :value="null">請選擇</option>
                                                <option v-for="o in makeupOptionsFor(entry.student_id)" :key="o.classroom_id" :value="o.classroom_id">
                                                    {{ o.label }}
                                                </option>
                                            </select>
                                            <span v-else class="text-sm">{{ makeupClassLabelFor(entry) }}</span>
                                        </div>
                                        <div v-if="entry.makeup_for_classroom_id" class="flex items-center gap-2">
                                            <span class="w-16 shrink-0 text-xs text-muted-foreground">補課時間</span>
                                            <select
                                                v-if="!locked"
                                                v-model="entry.makeup_date"
                                                class="h-9 w-full min-w-0 flex-1 rounded-md border bg-background px-2"
                                            >
                                                <option value="" disabled>{{ makeupDateOptions(entry).length ? '請選擇請假日' : '無請假紀錄' }}</option>
                                                <option v-for="d in makeupDateOptions(entry)" :key="d" :value="d">{{ d }}</option>
                                            </select>
                                            <span v-else class="text-sm tabular-nums text-muted-foreground">
                                                {{ entry.makeup_date || '—' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else-if="entry.status === 'extra'" class="flex items-center gap-2">
                                        <span class="w-16 shrink-0 text-xs text-muted-foreground">加課班級</span>
                                        <select
                                            v-if="!locked"
                                            v-model.number="entry.makeup_for_classroom_id"
                                            class="h-9 w-full min-w-0 flex-1 rounded-md border bg-background px-2"
                                        >
                                            <option :value="null">請選擇</option>
                                            <option v-for="o in makeupOptionsFor(entry.student_id)" :key="o.classroom_id" :value="o.classroom_id">
                                                {{ o.label }}
                                            </option>
                                        </select>
                                        <span v-else class="text-sm">{{ makeupClassLabelFor(entry) }}</span>
                                    </div>
                                    <span v-else-if="locked" class="text-sm text-muted-foreground">{{ entry.note || '—' }}</span>
                                    <Input v-else v-model="entry.note" class="h-9" placeholder="選填" />
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Button
                                        v-if="!locked && !entry.from_enrollment && !entry.protected"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive hover:text-destructive"
                                        title="移除補課學生"
                                        @click="removeRow(index)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                    <span v-else class="text-sm text-muted-foreground">—</span>
                                </td>
                            </tr>
                            <tr v-if="form.entries.length === 0">
                                <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                                    此班尚無在籍學生，請用右上角「新增學生」建立補課列。
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <InputError :message="form.errors.entries" />
                <InputError :message="form.errors.class_date" />
                <InputError :message="localError" />
            </section>

            <div class="flex flex-wrap gap-2">
                <Button v-if="!locked" type="submit" :disabled="form.processing">儲存出勤</Button>
                <Button variant="outline" type="button" as-child>
                    <Link :href="backUrl">返回</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
