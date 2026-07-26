<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import ClassroomColorInput from '@/components/ClassroomColorInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type GradeOption = { id: number; name: string; code: number };
type PricingGroupOption = { value: string; label: string };

type CoursePrice = {
    id: number;
    level: string | null;
    duration_hours: number;
    tuition: number;
};

type ScheduleRow = {
    level: string | null;
    weekday: string;
    start_time: string;
    end_time: string;
};

const props = defineProps<{
    course: {
        id: number;
        course_category_id: number;
        name: string;
        color: string | null;
        status: 'active' | 'paused';
        pricing_group: string | null;
        schedules: Array<{
            level?: string | null;
            weekday: number;
            start_time: string | null;
            end_time: string | null;
        }>;
        course_prices: CoursePrice[];
    };
    categories: Array<{ id: number; name: string; sort_order: number }>;
    gradeLevels: GradeOption[];
    pricingGroups: PricingGroupOption[];
}>();

const gradeNames = props.gradeLevels.map((g) => g.name);
const initialLevels = props.course.course_prices
    .map((p) => p.level)
    .filter((l): l is string => !!l && gradeNames.includes(l));

const toHm = (value: string | null | undefined): string =>
    value ? value.slice(0, 5) : '';

const initialSchedules = (): ScheduleRow[] => {
    if (!props.course.schedules?.length) {
        return [];
    }
    return props.course.schedules.map((s) => ({
        level: s.level?.trim() ? s.level.trim() : null,
        weekday: String(s.weekday),
        start_time: toHm(s.start_time),
        end_time: toHm(s.end_time),
    }));
};

const form = useForm({
    course_category_id: String(props.course.course_category_id),
    name: props.course.name,
    color:
        props.course.color && /^#[0-9A-Fa-f]{6}$/i.test(props.course.color)
            ? props.course.color.toLowerCase()
            : '#0d9488',
    status: props.course.status,
    pricing_group: props.course.pricing_group ?? '',
    schedules: initialSchedules(),
    levels: initialLevels,
});

const selectedGrades = computed(() =>
    props.gradeLevels.filter((g) => form.levels.includes(g.name)),
);

const schedulesForLevel = (level: string | null) =>
    form.schedules
        .map((s, index) => ({ s, index }))
        .filter(({ s }) => (s.level ?? null) === level);

const toggleLevel = (level: string) => {
    if (form.levels.includes(level)) {
        form.levels = form.levels.filter((l) => l !== level);
        form.schedules = form.schedules.filter((s) => s.level !== level);
    } else {
        form.levels = [...form.levels, level];
    }
};

const addSchedule = (level: string | null) => {
    form.schedules.push({ level, weekday: '1', start_time: '', end_time: '' });
};

const removeSchedule = (index: number) => {
    form.schedules.splice(index, 1);
};

const submit = () =>
    form
        .transform((data) => {
            const levelSet = new Set(data.levels);
            const schedules = data.schedules
                .filter((s) => {
                    if (data.levels.length === 0) {
                        return true;
                    }
                    return s.level != null && levelSet.has(s.level);
                })
                .map((s) => ({
                    level: data.levels.length === 0 ? null : s.level,
                    weekday: Number(s.weekday),
                    start_time: s.start_time || null,
                    end_time: s.end_time || null,
                }));

            return {
                ...data,
                pricing_group:
                    data.pricing_group === '' ? null : data.pricing_group,
                color: data.color === '' ? null : data.color,
                schedules,
            };
        })
        .put(`/courses/${props.course.id}`);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '課程管理', href: '/courses' },
            { title: '編輯', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="編輯課程" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">編輯課程</h1>
        <form class="space-y-4 rounded-xl border p-4" @submit.prevent="submit">
            <div class="grid gap-2 md:grid-cols-2 md:gap-4">
                <div class="grid gap-2">
                    <Label for="course_category_id">類別</Label>
                    <select
                        id="course_category_id"
                        v-model="form.course_category_id"
                        class="h-9 rounded-md border px-3"
                    >
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.course_category_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="name">名稱</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="color">課程顏色</Label>
                <ClassroomColorInput
                    id="color"
                    v-model="form.color"
                    placeholder="#0d9488"
                />
                <p class="text-xs text-muted-foreground">
                    用於學生收款科目卡片與上課日行事曆辨識。
                </p>
                <InputError :message="form.errors.color" />
            </div>

            <div class="grid gap-2">
                <Label for="pricing_group">收費組別</Label>
                <select
                    id="pricing_group"
                    v-model="form.pricing_group"
                    class="h-9 rounded-md border px-3"
                >
                    <option value="">未設定</option>
                    <option
                        v-for="g in pricingGroups"
                        :key="g.value"
                        :value="g.value"
                    >
                        {{ g.label }}
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    用於核心科單科／雙科等優惠分類；實際價目請至「收費標準」勾選適用課目。
                </p>
                <InputError :message="form.errors.pricing_group" />
            </div>

            <div class="space-y-2 rounded-lg border p-4">
                <Label class="text-base font-medium">年級</Label>
                <p class="text-xs text-muted-foreground">
                    勾選適用年級後，下方可分別設定該年級的上課時段；都不勾則視為不分年級。
                </p>
                <div
                    v-if="props.gradeLevels.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    尚未設定年級，請先至「設定管理 → 年級編號」新增。
                </div>
                <div v-else class="flex flex-wrap gap-3">
                    <label
                        v-for="g in props.gradeLevels"
                        :key="g.id"
                        class="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            class="size-4 accent-[var(--brand-green)]"
                            :checked="form.levels.includes(g.name)"
                            @change="toggleLevel(g.name)"
                        />
                        {{ g.name }}
                    </label>
                </div>
                <InputError :message="form.errors.levels" />
            </div>

            <div
                v-if="form.levels.length === 0"
                class="space-y-2 rounded-lg border p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <Label class="text-base font-medium"
                            >上課時段（不分年級）</Label
                        >
                        <p class="mt-1 text-xs text-muted-foreground">
                            每筆為「星期＋時間」。耗材按日計費時依星期計算堂數。
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addSchedule(null)"
                    >
                        <Plus class="mr-1 size-4" />
                        新增時段
                    </Button>
                </div>
                <div
                    v-if="schedulesForLevel(null).length === 0"
                    class="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground"
                >
                    尚未新增時段。
                </div>
                <div
                    v-for="{ s, index } in schedulesForLevel(null)"
                    :key="`all-${index}`"
                    class="form-schedule-row"
                >
                    <div class="grid gap-2">
                        <Label :for="`weekday_${index}`">星期</Label>
                        <select
                            :id="`weekday_${index}`"
                            v-model="s.weekday"
                            class="h-10 w-full rounded-md border px-3"
                        >
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
                        <Input
                            :id="`start_time_${index}`"
                            v-model="s.start_time"
                            type="time"
                            class="h-10 w-full"
                        />
                        <InputError
                            :message="
                                form.errors[`schedules.${index}.start_time`]
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`end_time_${index}`">結束時間</Label>
                        <Input
                            :id="`end_time_${index}`"
                            v-model="s.end_time"
                            type="time"
                            class="h-10 w-full"
                        />
                        <InputError
                            :message="
                                form.errors[`schedules.${index}.end_time`]
                            "
                        />
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
            </div>

            <div
                v-for="g in selectedGrades"
                :key="g.id"
                class="space-y-2 rounded-lg border p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <Label class="text-base font-medium"
                            >{{ g.name }}上課時段</Label
                        >
                        <p class="mt-1 text-xs text-muted-foreground">
                            可加多筆「星期＋時間」。耗材按日計費時依此年級時段計算堂數。
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addSchedule(g.name)"
                    >
                        <Plus class="mr-1 size-4" />
                        新增時段
                    </Button>
                </div>
                <div
                    v-if="schedulesForLevel(g.name).length === 0"
                    class="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground"
                >
                    尚未新增 {{ g.name }} 時段。
                </div>
                <div
                    v-for="{ s, index } in schedulesForLevel(g.name)"
                    :key="`${g.name}-${index}`"
                    class="form-schedule-row"
                >
                    <div class="grid gap-2">
                        <Label :for="`weekday_${g.name}_${index}`">星期</Label>
                        <select
                            :id="`weekday_${g.name}_${index}`"
                            v-model="s.weekday"
                            class="h-10 w-full rounded-md border px-3"
                        >
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
                        <Label :for="`start_time_${g.name}_${index}`"
                            >開始時間</Label
                        >
                        <Input
                            :id="`start_time_${g.name}_${index}`"
                            v-model="s.start_time"
                            type="time"
                            class="h-10 w-full"
                        />
                        <InputError
                            :message="
                                form.errors[`schedules.${index}.start_time`]
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`end_time_${g.name}_${index}`"
                            >結束時間</Label
                        >
                        <Input
                            :id="`end_time_${g.name}_${index}`"
                            v-model="s.end_time"
                            type="time"
                            class="h-10 w-full"
                        />
                        <InputError
                            :message="
                                form.errors[`schedules.${index}.end_time`]
                            "
                        />
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
            </div>
            <InputError :message="form.errors.schedules" />

            <div class="grid w-full gap-2">
                <Label for="status">狀態</Label>
                <select
                    id="status"
                    v-model="form.status"
                    class="h-9 w-full rounded-md border px-3"
                >
                    <option value="active">啟用</option>
                    <option value="paused">停用</option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">更新</Button>
                <Button variant="outline" as-child
                    ><Link href="/courses">返回</Link></Button
                >
            </div>
        </form>
    </div>
</template>
