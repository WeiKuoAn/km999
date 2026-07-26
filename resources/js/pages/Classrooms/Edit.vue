<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import ClassroomColorInput from '@/components/ClassroomColorInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCourseSelectLabel } from '@/lib/courseLabel';

const props = defineProps<{
    classroom: {
        id: number;
        course_id: number | null;
        grade_level_id: number | null;
        teacher_id: number | null;
        name: string;
        schedules: Array<{
            id: number;
            course_id: number | null;
            weekday: number;
            start_time: string;
            end_time: string;
        }>;
        start_date: string | null;
        end_date: string | null;
        active_periods?: Array<{ start_date: string | null; end_date: string | null }> | null;
        status: 'active' | 'paused';
        color: string | null;
    };
    courses: Array<{
        id: number;
        course_category: { name: string };
        name: string;
        course_prices: Array<{ level: string | null; tuition: number }>;
    }>;
    teachers: Array<{ id: number; name: string }>;
    gradeLevels: Array<{ id: number; name: string; code: number }>;
}>();

const toHm = (value: string | null | undefined): string => {
    if (!value) return '';
    return value.slice(0, 5);
};

const rowFromPeriod = (start: string | null | undefined, end: string | null | undefined) => ({
    start_date: start ? String(start).slice(0, 10) : '',
    end_date: end ? String(end).slice(0, 10) : '',
});

const initialActivePeriods = (): Array<{ start_date: string; end_date: string }> => {
    const ap = props.classroom.active_periods;
    if (Array.isArray(ap) && ap.length > 0) {
        return ap.map((r) => rowFromPeriod(r.start_date ?? null, r.end_date ?? null));
    }
    if (props.classroom.start_date || props.classroom.end_date) {
        return [rowFromPeriod(props.classroom.start_date, props.classroom.end_date)];
    }

    return [];
};

const fallbackCourseId = props.classroom.course_id ? String(props.classroom.course_id) : '';

const form = useForm({
    grade_level_id: props.classroom.grade_level_id ? String(props.classroom.grade_level_id) : '',
    teacher_id: props.classroom.teacher_id ? String(props.classroom.teacher_id) : '',
    name: props.classroom.name,
    color:
        props.classroom.color && /^#[0-9A-Fa-f]{6}$/i.test(props.classroom.color)
            ? props.classroom.color.toLowerCase()
            : '',
    schedules: props.classroom.schedules.length
        ? props.classroom.schedules.map((s) => ({
              course_id: s.course_id ? String(s.course_id) : fallbackCourseId,
              weekday: String(s.weekday),
              start_time: toHm(s.start_time),
              end_time: toHm(s.end_time),
          }))
        : [{ course_id: fallbackCourseId, weekday: '1', start_time: '', end_time: '' }],
    active_periods: initialActivePeriods(),
    status: props.classroom.status,
});

const addSchedule = () => {
    form.schedules.push({ course_id: '', weekday: '1', start_time: '', end_time: '' });
};

const removeSchedule = (index: number) => {
    if (form.schedules.length <= 1) {
        return;
    }
    form.schedules.splice(index, 1);
};

const addActivePeriod = () => {
    form.active_periods.push({ start_date: '', end_date: '' });
};

const removeActivePeriod = (index: number) => {
    form.active_periods.splice(index, 1);
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '班級管理', href: '/classrooms' },
            { title: '編輯', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="編輯班級" />
    <div class="page-shell w-full xl:mx-auto xl:max-w-5xl">
        <h1 class="text-xl font-semibold">編輯班級</h1>

        <section class="space-y-4 rounded-xl border p-4 md:p-5">
            <h2 class="text-lg font-semibold">班級管理資訊</h2>
            <form @submit.prevent="form.put(`/classrooms/${props.classroom.id}`)" class="space-y-5">
                <div class="grid gap-2">
                    <Label for="name">班級名稱</Label>
                    <Input id="name" v-model="form.name" class="h-10" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="color">班級顏色</Label>
                    <ClassroomColorInput id="color" v-model="form.color" placeholder="#64748b" />
                </div>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="grade_level_id">年級</Label>
                        <select id="grade_level_id" v-model="form.grade_level_id" class="h-10 w-full rounded-md border px-3" required>
                            <option value="">請選擇</option>
                            <option v-for="g in gradeLevels" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">一個班對應一個年級（國一／國二／國三）。</p>
                        <InputError :message="form.errors.grade_level_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="teacher_id">老師（可選）</Label>
                        <select id="teacher_id" v-model="form.teacher_id" class="h-10 w-full rounded-md border px-3">
                            <option value="">未指定</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="String(teacher.id)">{{ teacher.name }}</option>
                        </select>
                        <InputError :message="form.errors.teacher_id" />
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="status">狀態</Label>
                        <select id="status" v-model="form.status" class="h-10 w-full rounded-md border px-3">
                            <option value="active">上課</option>
                            <option value="paused">停課</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>
                <div class="space-y-3 rounded-lg border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <Label>開課區間（可多組）</Label>
                            <p class="text-xs text-muted-foreground mt-1">
                                未新增區間，或每組起訖皆留空時，視為開課期間無限制（僅依下方星期與時段）。例如暑假班可新增兩段：七月至八月。
                            </p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addActivePeriod">
                            <Plus class="mr-1 size-4" />
                            新增區間
                        </Button>
                    </div>
                    <div v-for="(p, index) in form.active_periods" :key="index" class="form-period-row">
                        <div class="grid gap-2">
                            <Label :for="`active_start_${index}`">開課日</Label>
                            <Input :id="`active_start_${index}`" type="date" class="h-10 w-full" v-model="p.start_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`active_end_${index}`">結束日</Label>
                            <Input :id="`active_end_${index}`" type="date" class="h-10 w-full" v-model="p.end_date" />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="form-period-row__delete text-destructive"
                            @click="removeActivePeriod(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <InputError :message="form.errors.active_periods" />
                </div>
                <div class="space-y-3 rounded-lg border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <Label>上課時段（可多筆）</Label>
                            <p class="text-xs text-muted-foreground mt-1">
                                每筆為「科目＋星期＋時間」。狀元班可加多科（如國文、英文、數學）。
                            </p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addSchedule">
                            <Plus class="mr-1 size-4" />
                            新增時段
                        </Button>
                    </div>
                    <div v-for="(s, index) in form.schedules" :key="index" class="form-schedule-row">
                        <div class="grid gap-2">
                            <Label :for="`course_${index}`">科目</Label>
                            <select :id="`course_${index}`" v-model="s.course_id" class="h-10 w-full min-w-0 rounded-md border px-3" required>
                                <option value="">請選擇</option>
                                <option v-for="course in courses" :key="course.id" :value="String(course.id)">
                                    {{ formatCourseSelectLabel(course) }}
                                </option>
                            </select>
                            <InputError :message="form.errors[`schedules.${index}.course_id`]" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`weekday_${index}`">星期</Label>
                            <select :id="`weekday_${index}`" v-model="s.weekday" class="h-10 w-full rounded-md border px-3">
                                <option value="1">週一</option>
                                <option value="2">週二</option>
                                <option value="3">週三</option>
                                <option value="4">週四</option>
                                <option value="5">週五</option>
                                <option value="6">週六</option>
                                <option value="7">週日</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`start_time_${index}`">開始時間</Label>
                            <Input :id="`start_time_${index}`" type="time" class="h-10 w-full" v-model="s.start_time" />
                            <InputError :message="form.errors[`schedules.${index}.start_time`]" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`end_time_${index}`">結束時間</Label>
                            <Input :id="`end_time_${index}`" type="time" class="h-10 w-full" v-model="s.end_time" />
                            <InputError :message="form.errors[`schedules.${index}.end_time`]" />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="form-schedule-row__delete text-destructive"
                            @click="removeSchedule(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <InputError :message="form.errors.schedules" />
                </div>
                <p class="text-sm text-muted-foreground rounded-lg border border-dashed px-3 py-2">
                    臨時加課請於「線上點名」時，將學生狀態設為「加課」並選擇加課班級。
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="form.processing">更新班級</Button>
                    <Button variant="outline" as-child><Link href="/classrooms">返回</Link></Button>
                    <Button variant="secondary" as-child>
                        <Link :href="`/classrooms/${props.classroom.id}/students`">班級學生名單</Link>
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>
