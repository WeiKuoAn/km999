<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    defaultTuitionFromCoursePrices,
    formatDurationHours,
    hasMultipleCourseDurations,
    SCHOOL_SEGMENTS,
    tuitionAmountsByDurationForSegment,
    tuitionFromSchoolSegment,
} from '@/lib/courseLabel';
import { ChevronDown } from 'lucide-vue-next';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconButton from '@/components/table/TableEditIconButton.vue';

type DurationTuition = {
    duration_hours: number;
    tuition_amount: number;
};

const props = defineProps<{
    classroom: {
        id: number;
        name: string;
        course_id: number;
    };
    course_prices: Array<{ level: string | null; duration_hours?: number; tuition: number }>;
    students: Array<{
        id: number;
        name: string;
        school_segment: string | null;
    }>;
    enrollments: Array<{
        id: number;
        student_id: number;
        tuition_amount: number;
        status: string;
        tuition_by_duration: DurationTuition[];
        student: { id: number; name: string; school_segment: string | null } | null;
    }>;
}>();

const defaultTuition = () => defaultTuitionFromCoursePrices(props.course_prices);

const multipleDurations = computed(() => hasMultipleCourseDurations(props.course_prices));

const buildTuitionByDuration = (segment: string | null | undefined): DurationTuition[] =>
    tuitionAmountsByDurationForSegment(props.course_prices, segment).map((item) => ({
        duration_hours: item.durationHours,
        tuition_amount: item.tuition,
    }));

const segmentForRow = (row: EnrollmentRow): string | null => {
    if (row.segment.trim() !== '') {
        return row.segment;
    }
    if (row.student_id > 0) {
        return props.students.find((s) => s.id === row.student_id)?.school_segment ?? null;
    }

    return null;
};

const legacyTuitionFromRows = (rows: DurationTuition[]) =>
    rows.length > 0 ? rows[0]!.tuition_amount : defaultTuition();

type EnrollmentRow = {
    student_id: number;
    studentSearch: string;
    listOpen: boolean;
    segment: string;
    tuition_amount: number;
    tuition_by_duration: DurationTuition[];
};

const emptyEnrollmentRow = (): EnrollmentRow => ({
    student_id: 0,
    studentSearch: '',
    listOpen: false,
    segment: '',
    tuition_amount: defaultTuition(),
    tuition_by_duration: buildTuitionByDuration(null),
});

const enrollmentRows = ref<EnrollmentRow[]>([emptyEnrollmentRow()]);

const enrollmentForm = useForm({
    entries: [] as Array<{
        student_id: number;
        tuition_amount: number;
        tuition_by_duration?: DurationTuition[];
    }>,
});

const studentsFilteredForRow = (index: number) => {
    const row = enrollmentRows.value[index];
    const q = row?.studentSearch.trim().toLowerCase() ?? '';
    if (!q) {
        return props.students;
    }

    return props.students.filter((s) => s.name.toLowerCase().includes(q));
};

let blurCloseTimer: ReturnType<typeof setTimeout> | null = null;

type ListAnchor = { top: number; left: number; width: number };

const comboAnchorEls = ref<Record<number, HTMLElement | null>>({});

const listAnchors = ref<Record<number, ListAnchor>>({});

const setComboAnchorEl = (index: number, el: unknown) => {
    comboAnchorEls.value[index] = (el as HTMLElement | null) ?? null;
};

const refreshListAnchor = (index: number) => {
    const el = comboAnchorEls.value[index];
    if (!el) {
        return;
    }
    const rect = el.getBoundingClientRect();
    listAnchors.value = {
        ...listAnchors.value,
        [index]: {
            top: rect.bottom + 4,
            left: rect.left,
            width: Math.max(rect.width, 192),
        },
    };
};

const listAnchorStyle = (index: number) => {
    const anchor = listAnchors.value[index];
    if (!anchor) {
        return { visibility: 'hidden' as const };
    }

    return {
        top: `${anchor.top}px`,
        left: `${anchor.left}px`,
        width: `${anchor.width}px`,
    };
};

const syncOpenListAnchors = () => {
    enrollmentRows.value.forEach((row, index) => {
        if (row.listOpen) {
            refreshListAnchor(index);
        }
    });
};

onMounted(() => {
    window.addEventListener('scroll', syncOpenListAnchors, true);
    window.addEventListener('resize', syncOpenListAnchors);
});

onUnmounted(() => {
    window.removeEventListener('scroll', syncOpenListAnchors, true);
    window.removeEventListener('resize', syncOpenListAnchors);
});

const openStudentList = (index: number) => {
    const row = enrollmentRows.value[index];
    if (row) {
        row.listOpen = true;
    }
    refreshListAnchor(index);
};

const closeStudentListDelayed = (index: number) => {
    if (blurCloseTimer !== null) {
        clearTimeout(blurCloseTimer);
    }
    blurCloseTimer = setTimeout(() => {
        const row = enrollmentRows.value[index];
        if (row) {
            row.listOpen = false;
        }
        blurCloseTimer = null;
    }, 150);
};

const pickStudent = (index: number, s: (typeof props.students)[0]) => {
    const row = enrollmentRows.value[index];
    if (!row) {
        return;
    }
    row.student_id = s.id;
    row.studentSearch = s.name;
    row.listOpen = false;
    if (s.school_segment) {
        row.segment = s.school_segment;
    }
    applyTuitionForRow(index);
};

const onStudentSearchInput = (index: number) => {
    const row = enrollmentRows.value[index];
    if (!row) {
        return;
    }
    const selected = props.students.find((st) => st.id === row.student_id);
    if (selected !== undefined && row.studentSearch !== selected.name) {
        row.student_id = 0;
    }
    row.listOpen = true;
    refreshListAnchor(index);
};

const applyTuitionForRow = (index: number) => {
    const row = enrollmentRows.value[index];
    if (!row) {
        return;
    }
    const segment = segmentForRow(row);

    if (multipleDurations.value) {
        row.tuition_by_duration = buildTuitionByDuration(segment);
        row.tuition_amount = legacyTuitionFromRows(row.tuition_by_duration);
    } else {
        row.tuition_amount = tuitionFromSchoolSegment(props.course_prices, segment);
    }
};

const onSegmentRowChange = (index: number) => {
    applyTuitionForRow(index);
};

const addEnrollmentRow = () => {
    enrollmentRows.value.push(emptyEnrollmentRow());
};

const removeEnrollmentRow = (index: number) => {
    enrollmentRows.value.splice(index, 1);
};

const submitEnrollments = () => {
    enrollmentRows.value.forEach((_, index) => applyTuitionForRow(index));

    const entries = enrollmentRows.value
        .filter((row) => row.student_id > 0)
        .map((row) => ({
            student_id: row.student_id,
            tuition_amount: multipleDurations.value
                ? legacyTuitionFromRows(row.tuition_by_duration)
                : Number(row.tuition_amount),
            tuition_by_duration: multipleDurations.value ? row.tuition_by_duration : undefined,
        }));

    if (entries.length === 0) {
        enrollmentForm.setError('entries', '請先在下方選擇要新增的學生，再按「儲存」。（現有名單以各列的編輯／移除管理）');
        return;
    }

    enrollmentForm.clearErrors();
    enrollmentForm.entries = entries;
    enrollmentForm.post(`/classrooms/${props.classroom.id}/enrollments`, {
        preserveScroll: true,
        onSuccess: () => {
            enrollmentRows.value = [emptyEnrollmentRow()];
        },
    });
};

const editOpen = ref(false);
const editingEnrollment = ref<{
    id: number;
    student_name: string;
    tuition_amount: number;
    tuition_by_duration: DurationTuition[];
} | null>(null);
const editTuitionForm = useForm<{
    tuition_amount: number;
    tuition_by_duration: DurationTuition[];
}>({
    tuition_amount: 0,
    tuition_by_duration: [],
});

const openEditEnrollment = (enrollment: (typeof props.enrollments)[0]) => {
    const tuitionByDuration = enrollment.tuition_by_duration.length
        ? enrollment.tuition_by_duration.map((row) => ({ ...row }))
        : buildTuitionByDuration(enrollment.student?.school_segment);

    editingEnrollment.value = {
        id: enrollment.id,
        student_name: enrollment.student?.name ?? '-',
        tuition_amount: enrollment.tuition_amount,
        tuition_by_duration: tuitionByDuration,
    };
    editTuitionForm.tuition_amount = enrollment.tuition_amount;
    editTuitionForm.tuition_by_duration = tuitionByDuration.map((row) => ({ ...row }));
    editTuitionForm.clearErrors();
    editOpen.value = true;
};

const saveEditEnrollment = () => {
    if (!editingEnrollment.value) {
        return;
    }

    if (multipleDurations.value) {
        editTuitionForm.tuition_amount = legacyTuitionFromRows(editTuitionForm.tuition_by_duration);
    }

    editTuitionForm.patch(`/classrooms/${props.classroom.id}/enrollments/${editingEnrollment.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            editingEnrollment.value = null;
        },
    });
};

const destroyEnrollment = (enrollmentId: number, studentName: string) => {
    if (!window.confirm(`確定將「${studentName}」從此班級名單移除？`)) {
        return;
    }
    router.delete(`/classrooms/${props.classroom.id}/enrollments/${enrollmentId}`, {
        preserveScroll: true,
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '班級管理', href: '/classrooms' },
            { title: '學生名單', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`班級學生名單 — ${classroom.name}`" />

    <div class="page-shell mx-auto w-full max-w-5xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">班級學生名單</h1>
                <p class="text-sm text-muted-foreground">
                    班級：{{ classroom.name }}
                    <span class="mx-2 text-border">|</span>
                    <Link :href="`/classrooms/${classroom.id}/edit`" class="text-primary underline-offset-4 hover:underline">
                        返回班級設定
                    </Link>
                </p>
            </div>
        </div>

        <section class="space-y-4 rounded-xl border p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">名單與學費</h2>
                <Button type="button" variant="outline" @click="addEnrollmentRow">新增學生</Button>
            </div>

            <form @submit.prevent="submitEnrollments" class="space-y-3">
                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th class="px-3 py-2 text-left">學生</th>
                                <th class="px-3 py-2 text-left w-[100px]">學段</th>
                                <th class="px-3 py-2 text-left w-[140px]">學費</th>
                                <th class="px-3 py-2 text-right w-[88px]">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="enrollment in enrollments" :key="enrollment.id" class="border-b">
                                <td class="px-3 py-2 align-middle">{{ enrollment.student?.name ?? '-' }}</td>
                                <td class="px-3 py-2 align-middle">{{ enrollment.student?.school_segment ?? '—' }}</td>
                                <td class="px-3 py-2 align-middle">
                                    <template v-if="multipleDurations && enrollment.tuition_by_duration.length">
                                        <div
                                            v-for="item in enrollment.tuition_by_duration"
                                            :key="`${enrollment.id}-${item.duration_hours}`"
                                            class="tabular-nums leading-relaxed"
                                        >
                                            {{ formatDurationHours(item.duration_hours) }}：{{ item.tuition_amount }}
                                        </div>
                                    </template>
                                    <span v-else class="tabular-nums">{{ enrollment.tuition_amount }}</span>
                                </td>
                                <td class="px-3 py-2 align-middle text-right">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <TableEditIconButton label="編輯學費" @click="openEditEnrollment(enrollment)" />
                                        <TableDeleteIconButton
                                            label="從班級移除"
                                            @click="destroyEnrollment(enrollment.id, enrollment.student?.name ?? '')"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="enrollments.length === 0 && enrollmentRows.length === 0">
                                <td colspan="4" class="px-3 py-4 text-center text-muted-foreground">目前尚無學生</td>
                            </tr>
                            <tr
                                v-for="(row, index) in enrollmentRows"
                                :key="`new-${index}`"
                                class="border-b bg-muted/10"
                            >
                                <td class="px-3 py-2 align-middle">
                                    <div
                                        class="relative min-w-[12rem]"
                                        :ref="(el) => setComboAnchorEl(index, el)"
                                    >
                                        <Input
                                            :id="`student_combo_${index}`"
                                            v-model="row.studentSearch"
                                            type="text"
                                            autocomplete="off"
                                            placeholder="輸入姓名篩選"
                                            class="h-9 pr-9"
                                            @focus="openStudentList(index)"
                                            @input="onStudentSearchInput(index)"
                                            @blur="closeStudentListDelayed(index)"
                                        />
                                        <ChevronDown
                                            class="pointer-events-none absolute right-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <select
                                        :id="`segment_${index}`"
                                        v-model="row.segment"
                                        class="flex h-9 w-full min-w-[5.5rem] rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        @change="onSegmentRowChange(index)"
                                    >
                                        <option value="">請選擇</option>
                                        <option v-for="seg in SCHOOL_SEGMENTS" :key="seg" :value="seg">{{ seg }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <template v-if="multipleDurations">
                                        <div class="space-y-1.5">
                                            <div
                                                v-for="(item, di) in row.tuition_by_duration"
                                                :key="`${index}-${item.duration_hours}`"
                                                class="flex items-center gap-1.5"
                                            >
                                                <span class="w-9 shrink-0 text-xs text-muted-foreground tabular-nums">
                                                    {{ formatDurationHours(item.duration_hours) }}
                                                </span>
                                                <Input
                                                    :id="`tuition_${index}_${di}`"
                                                    type="number"
                                                    min="0"
                                                    class="h-8 w-full min-w-[4.5rem] tabular-nums"
                                                    v-model.number="item.tuition_amount"
                                                />
                                            </div>
                                            <p
                                                v-if="row.tuition_by_duration.length === 0"
                                                class="text-xs text-muted-foreground"
                                            >
                                                請先選學生或學段
                                            </p>
                                        </div>
                                    </template>
                                    <Input
                                        v-else
                                        :id="`tuition_${index}`"
                                        type="number"
                                        min="0"
                                        class="h-9 w-full min-w-[4.5rem] tabular-nums"
                                        v-model.number="row.tuition_amount"
                                    />
                                </td>
                                <td class="px-3 py-2 align-middle text-right">
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        :disabled="enrollmentRows.length === 1"
                                        @click="removeEnrollmentRow(index)"
                                    >
                                        移除
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <InputError :message="enrollmentForm.errors.entries" />
                <InputError :message="enrollmentForm.errors['entries.0.student_id']" />

                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="enrollmentForm.processing">儲存</Button>
                    <Button variant="outline" as-child><Link href="/classrooms">返回班級列表</Link></Button>
                </div>
            </form>
        </section>

        <Dialog :open="editOpen" @update:open="editOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>編輯學費</DialogTitle>
                    <p v-if="editingEnrollment" class="text-sm text-muted-foreground">學生：{{ editingEnrollment.student_name }}</p>
                </DialogHeader>
                <div class="grid gap-3 py-2">
                    <template v-if="multipleDurations">
                        <div
                            v-for="(item, di) in editTuitionForm.tuition_by_duration"
                            :key="`edit-${item.duration_hours}`"
                            class="grid gap-1"
                        >
                            <Label :for="`edit_tuition_${di}`">{{ formatDurationHours(item.duration_hours) }}</Label>
                            <Input
                                :id="`edit_tuition_${di}`"
                                type="number"
                                min="0"
                                class="tabular-nums"
                                v-model.number="item.tuition_amount"
                            />
                        </div>
                    </template>
                    <template v-else>
                        <Label for="edit_tuition">學費金額</Label>
                        <Input id="edit_tuition" type="number" min="0" v-model.number="editTuitionForm.tuition_amount" />
                    </template>
                    <InputError :message="editTuitionForm.errors.tuition_amount" />
                </div>
                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="editOpen = false">取消</Button>
                    <Button type="button" :disabled="editTuitionForm.processing" @click="saveEditEnrollment">儲存</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>

    <Teleport to="body">
        <template v-for="(row, index) in enrollmentRows" :key="`student-list-${index}`">
            <div
                v-show="row.listOpen && studentsFilteredForRow(index).length > 0"
                class="fixed z-[200] max-h-52 overflow-auto rounded-md border bg-popover text-popover-foreground shadow-md"
                :style="listAnchorStyle(index)"
                role="listbox"
            >
                <button
                    v-for="s in studentsFilteredForRow(index)"
                    :key="s.id"
                    type="button"
                    role="option"
                    class="flex w-full px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                    @mousedown.prevent="pickStudent(index, s)"
                >
                    {{ s.name }}
                </button>
            </div>
        </template>
    </Teleport>
</template>
