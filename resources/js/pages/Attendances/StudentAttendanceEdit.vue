<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AttendanceStatusSelect from '@/components/AttendanceStatusSelect.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { classroomSwatchStyle } from '@/lib/classroomColor';
import { formatCourseLabel, formatDurationHours } from '@/lib/courseLabel';

const normalizeDuration = (hours: number | null | undefined): number | null => {
    if (hours === null || hours === undefined) {
        return null;
    }

    return Math.round(Number(hours) * 10) / 10;
};

const props = defineProps<{
    attendance: {
        id: number;
        class_date: string;
        status: string;
        note: string;
        makeup_date: string;
        makeup_for_classroom_id: number | null;
        duration_hours: number | null;
    };
    student: {
        id: number;
        name: string;
        phone: string | null;
    };
    classroom: {
        id: number;
        name: string;
        color: string | null;
        teacher_name: string | null;
        course_category_name: string;
        course_name: string;
    };
    makeupClassOptions: Array<{ classroom_id: number; label: string }>;
    /** 該生在各班的請假日期：leaveDates[classroom_id] = ['Y-m-d', ...] */
    leaveDates: Record<number, string[]>;
    durationOptions: number[];
    requiresDurationChoice: boolean;
    returnUrl: string;
}>();

const form = useForm<{
    class_date: string;
    status: string;
    note: string;
    makeup_date: string;
    makeup_for_classroom_id: number | null;
    duration_hours: number | null;
}>({
    class_date: props.attendance.class_date,
    status: props.attendance.status,
    note: props.attendance.note,
    makeup_date: props.attendance.makeup_date,
    makeup_for_classroom_id: props.attendance.makeup_for_classroom_id,
    duration_hours: normalizeDuration(props.attendance.duration_hours),
});

/** HTML select 以字串比對 option，避免 0.5 / 1 等浮點數選不到。 */
const durationSelectValue = computed({
    get(): string {
        const value = normalizeDuration(form.duration_hours);

        return value === null ? '' : String(value);
    },
    set(raw: string) {
        form.duration_hours = raw === '' ? null : normalizeDuration(Number(raw));
    },
});

const showMakeupClass = computed(() => form.status === 'makeup');
const displayCourseLabel = computed(() =>
    formatCourseLabel(
        props.classroom.course_category_name,
        props.classroom.course_name,
        props.requiresDurationChoice ? form.duration_hours : null,
    ),
);
const showExtraClass = computed(() => form.status === 'extra');
/** 補課時間僅在已選補課班級後出現 */
const showMakeupDate = computed(() => form.status === 'makeup' && form.makeup_for_classroom_id !== null);

/** 補課時間下拉選項：該生在所選班級的請假日（保留目前已選值）。 */
const makeupDateOptions = computed<string[]>(() => {
    if (form.makeup_for_classroom_id === null) {
        return [];
    }
    const leaves = props.leaveDates[form.makeup_for_classroom_id] ?? [];
    if (form.makeup_date && !leaves.includes(form.makeup_date)) {
        return [form.makeup_date, ...leaves];
    }

    return leaves;
});

/** 依目前補課班級重新對應補課時間：預設帶最近一筆請假日，沒有請假則留空。 */
const resolveMakeupTime = () => {
    if (form.makeup_for_classroom_id === null) {
        return;
    }
    const leaves = props.leaveDates[form.makeup_for_classroom_id] ?? [];
    if (!leaves.includes(form.makeup_date)) {
        form.makeup_date = leaves.length > 0 ? leaves[0] : '';
    }
};

const onMakeupClassChanged = () => {
    if (form.status === 'makeup') {
        resolveMakeupTime();
    }
};

const onStatusChanged = () => {
    if (form.status === 'makeup') {
        if (form.makeup_for_classroom_id === null && props.makeupClassOptions.length === 1) {
            form.makeup_for_classroom_id = props.makeupClassOptions[0].classroom_id;
        }
        resolveMakeupTime();
        return;
    }
    if (form.status === 'extra') {
        form.makeup_date = '';
        if (form.makeup_for_classroom_id === null && props.makeupClassOptions.length === 1) {
            form.makeup_for_classroom_id = props.makeupClassOptions[0].classroom_id;
        }
        return;
    }
    form.makeup_for_classroom_id = null;
    form.makeup_date = '';
};

const submit = () => {
    const returnQuery = encodeURIComponent(props.returnUrl);
    form
        .transform((data) => ({
            ...data,
            duration_hours: props.requiresDurationChoice ? data.duration_hours : null,
        }))
        .put(`/student-attendances/${props.attendance.id}?return=${returnQuery}`);
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生出勤', href: '/student-attendances' },
            { title: '編輯出勤', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`編輯出勤 — ${student.name}`" />

    <div class="page-shell w-full xl:mx-auto xl:max-w-3xl">
        <PageHeader title="編輯出勤">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="returnUrl">返回學生出勤</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm">
            <div class="border-b border-sidebar-border/70 bg-muted/30 px-5 py-4">
                <h2 class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    紀錄資訊
                </h2>
                <dl class="mt-3 grid gap-x-6 gap-y-4 text-sm xl:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="text-xs text-muted-foreground">學生</dt>
                        <dd class="mt-0.5 font-medium leading-snug">
                            {{ student.name }}
                            <span v-if="student.phone" class="block text-xs font-normal text-muted-foreground sm:inline sm:ml-1">
                                {{ student.phone }}
                            </span>
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-muted-foreground">班級</dt>
                        <dd class="mt-0.5 font-medium">
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60"
                                    :style="classroomSwatchStyle(classroom.color)"
                                />
                                {{ classroom.name }}
                            </span>
                        </dd>
                    </div>
                    <div class="min-w-0 xl:col-span-2">
                        <dt class="text-xs text-muted-foreground">課程</dt>
                        <dd class="mt-0.5 font-medium leading-snug break-words">
                            {{ displayCourseLabel }}
                        </dd>
                    </div>
                    <div v-if="classroom.teacher_name" class="min-w-0">
                        <dt class="text-xs text-muted-foreground">老師</dt>
                        <dd class="mt-0.5 font-medium">{{ classroom.teacher_name }}</dd>
                    </div>
                </dl>
            </div>

            <form class="p-4 md:p-5" @submit.prevent="submit">
                <p class="text-sm text-muted-foreground">
                    僅更新此筆出勤紀錄，不會影響同班同日的其他學生。
                </p>

                <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 xl:grid-cols-2">
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="class_date">上課日期（點名日期）</Label>
                        <Input
                            id="class_date"
                            v-model="form.class_date"
                            type="date"
                            class="h-10 w-full"
                            required
                        />
                        <InputError :message="form.errors.class_date" />
                    </div>

                    <div class="grid min-w-0 gap-1.5">
                        <Label for="status">狀態</Label>
                        <AttendanceStatusSelect id="status" v-model="form.status" trigger-class="h-10 w-full" @update:model-value="onStatusChanged" />
                        <InputError :message="form.errors.status" />
                    </div>

                    <div v-if="requiresDurationChoice" class="grid min-w-0 gap-1.5">
                        <Label for="duration_hours">時數</Label>
                        <select
                            id="duration_hours"
                            v-model="durationSelectValue"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                            required
                        >
                            <option value="" disabled>請選擇</option>
                            <option v-for="d in durationOptions" :key="d" :value="String(d)">
                                {{ formatDurationHours(d) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.duration_hours" />
                    </div>

                    <div v-if="showMakeupClass || showExtraClass" class="grid min-w-0 gap-1.5">
                        <Label for="makeup_for_classroom_id">{{ showExtraClass ? '加課班級' : '補課班級' }}</Label>
                        <select
                            id="makeup_for_classroom_id"
                            v-model.number="form.makeup_for_classroom_id"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                            @change="onMakeupClassChanged"
                        >
                            <option :value="null">請選擇班級</option>
                            <option v-for="o in makeupClassOptions" :key="o.classroom_id" :value="o.classroom_id">
                                {{ o.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.makeup_for_classroom_id" />
                    </div>

                    <div v-if="showMakeupDate" class="grid min-w-0 gap-1.5">
                        <Label for="makeup_date">補課時間</Label>
                        <select
                            id="makeup_date"
                            v-model="form.makeup_date"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                        >
                            <option value="" disabled>{{ makeupDateOptions.length ? '請選擇請假日' : '無請假紀錄' }}</option>
                            <option v-for="d in makeupDateOptions" :key="d" :value="d">{{ d }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">被補的原請假日，與上方「上課日期（點名日期）」不同。</p>
                        <InputError :message="form.errors.makeup_date" />
                    </div>

                    <div class="grid min-w-0 gap-1.5 xl:col-span-2">
                        <Label for="note">備註</Label>
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            placeholder="選填"
                        />
                        <InputError :message="form.errors.note" />
                    </div>
                </div>

                <div class="form-actions mt-6 border-t border-sidebar-border/70 pt-5">
                    <Button type="button" variant="outline" class="w-full sm:w-auto" as-child>
                        <Link :href="returnUrl">取消</Link>
                    </Button>
                    <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">
                        儲存
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
