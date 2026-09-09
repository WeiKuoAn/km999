<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type HolidayRow = {
    id: number;
    date: string;
    name: string;
    is_custom: boolean;
};

type ExceptionRow = {
    id: number;
    type: 'closure' | 'makeup';
    date_from: string;
    date_to: string;
    name: string;
    grade_level_id: number | null;
    grade_name: string | null;
    course_ids: number[];
    course_labels: string[];
    all_courses: boolean;
    start_time: string | null;
    end_time: string | null;
};

type GradeOpt = { id: number; name: string; code: number | null };
type CourseOpt = { id: number; name: string; category: string | null };

const props = defineProps<{
    holidays: HolidayRow[];
    exceptions?: ExceptionRow[];
    grades?: GradeOpt[];
    courses?: CourseOpt[];
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
const canManage =
    page.props.auth.user.role === 'super_admin' ||
    page.props.auth.user.role === 'admin';

/** school = 全校假日；exceptions = 停課／補課 */
const mainTab = ref<'exceptions' | 'school'>('exceptions');
const listFilter = ref<'all' | 'closure' | 'makeup'>('all');

const form = useForm({
    date: '',
    name: '',
});

const exceptionForm = useForm({
    type: 'closure' as 'closure' | 'makeup',
    date_from: '',
    date_to: '',
    name: '',
    grade_level_id: '' as string,
    course_ids: [] as number[],
    all_courses: true,
    start_time: '',
    end_time: '',
});

const importFile = ref<File | null>(null);
const importing = ref(false);
const importError = ref('');

watch(
    () => exceptionForm.type,
    (type) => {
        if (type === 'closure') {
            exceptionForm.start_time = '';
            exceptionForm.end_time = '';
        }
    },
);

watch(
    () => exceptionForm.all_courses,
    (all) => {
        if (all) {
            exceptionForm.course_ids = [];
        }
    },
);

const filteredExceptions = computed(() => {
    const rows = props.exceptions ?? [];
    if (listFilter.value === 'all') {
        return rows;
    }
    return rows.filter((r) => r.type === listFilter.value);
});

const courseChipLabel = (c: CourseOpt) =>
    c.category && c.category !== c.name
        ? `${c.category}`
        : c.category || c.name;

const selectedCourseSummary = computed(() => {
    if (exceptionForm.all_courses) {
        return exceptionForm.grade_level_id
            ? '該年級全部課程'
            : '全部年級・全部課程';
    }
    if (exceptionForm.course_ids.length === 0) {
        return '尚未選擇課程';
    }
    const labels = (props.courses ?? [])
        .filter((c) => exceptionForm.course_ids.includes(c.id))
        .map(courseChipLabel);
    return labels.join('、');
});

const submitManual = () => {
    form.post('/holidays', {
        preserveScroll: true,
        onSuccess: () => form.reset('date', 'name'),
    });
};

const submitException = () => {
    if (!exceptionForm.date_to) {
        exceptionForm.date_to = exceptionForm.date_from;
    }
    exceptionForm
        .transform((data) => ({
            ...data,
            grade_level_id:
                data.grade_level_id === '' ? null : Number(data.grade_level_id),
            course_ids: data.all_courses ? [] : data.course_ids,
            start_time:
                data.type === 'makeup' && data.start_time
                    ? data.start_time
                    : null,
            end_time:
                data.type === 'makeup' && data.end_time ? data.end_time : null,
        }))
        .post('/schedule-exceptions', {
            preserveScroll: true,
            onSuccess: () => {
                exceptionForm.reset(
                    'date_from',
                    'date_to',
                    'name',
                    'course_ids',
                    'start_time',
                    'end_time',
                );
                exceptionForm.all_courses = true;
                exceptionForm.grade_level_id = '';
            },
        });
};

const toggleCourse = (id: number) => {
    exceptionForm.all_courses = false;
    const idx = exceptionForm.course_ids.indexOf(id);
    if (idx >= 0) {
        exceptionForm.course_ids.splice(idx, 1);
    } else {
        exceptionForm.course_ids.push(id);
    }
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    importFile.value = input.files?.[0] ?? null;
    importError.value = '';
};

const submitImport = () => {
    if (!importFile.value) {
        importError.value = '請先選擇 CSV 檔案。';
        return;
    }
    importing.value = true;
    importError.value = '';
    const data = new FormData();
    data.append('file', importFile.value);
    router.post('/holidays/import', data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importing.value = false;
        },
        onSuccess: () => {
            importFile.value = null;
            const input = document.getElementById(
                'holiday_csv',
            ) as HTMLInputElement | null;
            if (input) {
                input.value = '';
            }
        },
        onError: (errors) => {
            importError.value =
                (errors.file as string) || '匯入失敗，請確認檔案格式。';
        },
    });
};

const destroyHoliday = (id: number) => {
    if (!window.confirm('確定要刪除此假日嗎？')) {
        return;
    }
    router.delete(`/holidays/${id}`, { preserveScroll: true });
};

const destroyException = (row: ExceptionRow) => {
    const label = row.type === 'makeup' ? '補課' : '停課';
    if (!window.confirm(`確定要刪除此${label}設定嗎？`)) {
        return;
    }
    router.delete(`/schedule-exceptions/${row.id}`, { preserveScroll: true });
};

const scopeLabel = (row: ExceptionRow) => {
    const grade = row.grade_name ?? '全部年級';
    const courses = row.all_courses
        ? '全部課程'
        : row.course_labels.length > 0
          ? row.course_labels.join('、')
          : '全部課程';
    return `${grade}｜${courses}`;
};

const dateLabel = (row: ExceptionRow) =>
    row.date_from === row.date_to
        ? row.date_from
        : `${row.date_from}〜${row.date_to}`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: '假日／停課補課', href: '/holidays' }],
    },
});
</script>

<template>
    <Head title="假日／停課補課" />
    <div class="page-shell mx-auto w-full max-w-5xl">
        <PageHeader
            title="假日／停課補課"
            description="停課與補課可指定年級、多科；全校假日用官方 CSV 或手動新增。收款堂次會自動套用。"
        />

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>

        <!-- 主分頁：常用在前 -->
        <div
            class="mb-4 flex gap-1 border-b border-sidebar-border/70"
            role="tablist"
        >
            <button
                type="button"
                role="tab"
                class="relative -mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors"
                :class="
                    mainTab === 'exceptions'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :aria-selected="mainTab === 'exceptions'"
                @click="mainTab = 'exceptions'"
            >
                停課／補課
                <span
                    v-if="(exceptions ?? []).length"
                    class="ml-1.5 tabular-nums text-xs text-muted-foreground"
                >
                    {{ (exceptions ?? []).length }}
                </span>
            </button>
            <button
                type="button"
                role="tab"
                class="relative -mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors"
                :class="
                    mainTab === 'school'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :aria-selected="mainTab === 'school'"
                @click="mainTab = 'school'"
            >
                全校假日
                <span
                    v-if="holidays.length"
                    class="ml-1.5 tabular-nums text-xs text-muted-foreground"
                >
                    {{ holidays.length }}
                </span>
            </button>
        </div>

        <!-- ===== 停課／補課 ===== -->
        <div v-show="mainTab === 'exceptions'" class="space-y-4">
            <form
                v-if="canManage"
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-5"
                @submit.prevent="submitException"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">新增一筆</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            颱風、畢旅可一次勾多科；補課時段選填。
                        </p>
                    </div>
                    <div
                        class="inline-flex rounded-lg border border-sidebar-border/70 p-0.5"
                    >
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="
                                exceptionForm.type === 'closure'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="exceptionForm.type = 'closure'"
                        >
                            停課
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="
                                exceptionForm.type === 'makeup'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="exceptionForm.type = 'makeup'"
                        >
                            補課
                        </button>
                    </div>
                </div>

                <div
                    class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1.4fr]"
                >
                    <div class="grid gap-1">
                        <Label for="ex_from">開始</Label>
                        <Input
                            id="ex_from"
                            v-model="exceptionForm.date_from"
                            type="date"
                            required
                        />
                        <InputError :message="exceptionForm.errors.date_from" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="ex_to"
                            >結束
                            <span class="font-normal text-muted-foreground"
                                >（單日可空）</span
                            ></Label
                        >
                        <Input
                            id="ex_to"
                            v-model="exceptionForm.date_to"
                            type="date"
                        />
                        <InputError :message="exceptionForm.errors.date_to" />
                    </div>
                    <div class="grid gap-1 sm:col-span-2 lg:col-span-1">
                        <Label for="ex_name">原因</Label>
                        <Input
                            id="ex_name"
                            v-model="exceptionForm.name"
                            :placeholder="
                                exceptionForm.type === 'makeup'
                                    ? '例：段考後補課'
                                    : '例：颱風假、畢業旅行'
                            "
                            required
                        />
                        <InputError :message="exceptionForm.errors.name" />
                    </div>
                </div>

                <div
                    class="mt-3 grid gap-3"
                    :class="
                        exceptionForm.type === 'makeup'
                            ? 'sm:grid-cols-[1fr_1fr_1fr]'
                            : 'sm:grid-cols-2'
                    "
                >
                    <div class="grid gap-1">
                        <Label for="ex_grade">年級</Label>
                        <select
                            id="ex_grade"
                            v-model="exceptionForm.grade_level_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">全部年級</option>
                            <option
                                v-for="g in grades ?? []"
                                :key="g.id"
                                :value="String(g.id)"
                            >
                                {{ g.name }}
                            </option>
                        </select>
                        <InputError
                            :message="exceptionForm.errors.grade_level_id"
                        />
                    </div>
                    <template v-if="exceptionForm.type === 'makeup'">
                        <div class="grid gap-1">
                            <Label for="ex_start"
                                >開始時間
                                <span class="font-normal text-muted-foreground"
                                    >選填</span
                                ></Label
                            >
                            <Input
                                id="ex_start"
                                v-model="exceptionForm.start_time"
                                type="time"
                            />
                            <InputError
                                :message="exceptionForm.errors.start_time"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label for="ex_end"
                                >結束時間
                                <span class="font-normal text-muted-foreground"
                                    >選填</span
                                ></Label
                            >
                            <Input
                                id="ex_end"
                                v-model="exceptionForm.end_time"
                                type="time"
                            />
                            <InputError
                                :message="exceptionForm.errors.end_time"
                            />
                        </div>
                    </template>
                </div>

                <div class="mt-4">
                    <div
                        class="mb-2 flex flex-wrap items-baseline justify-between gap-2"
                    >
                        <Label>適用課程</Label>
                        <p class="text-xs text-muted-foreground">
                            {{ selectedCourseSummary }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm"
                            :class="
                                exceptionForm.all_courses
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border hover:border-primary/40'
                            "
                            @click="exceptionForm.all_courses = true"
                        >
                            全部課程
                        </button>
                        <button
                            v-for="c in courses ?? []"
                            :key="c.id"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm"
                            :class="
                                !exceptionForm.all_courses &&
                                exceptionForm.course_ids.includes(c.id)
                                    ? 'border-primary bg-primary/10 text-foreground'
                                    : 'border-border text-muted-foreground hover:border-primary/40 hover:text-foreground'
                            "
                            :title="c.name"
                            @click="toggleCourse(c.id)"
                        >
                            {{ courseChipLabel(c) }}
                        </button>
                    </div>
                    <InputError
                        class="mt-1"
                        :message="exceptionForm.errors.course_ids"
                    />
                </div>

                <div class="mt-4 flex justify-end border-t border-sidebar-border/50 pt-4">
                    <Button type="submit" :disabled="exceptionForm.processing">
                        {{
                            exceptionForm.type === 'makeup'
                                ? '新增補課'
                                : '新增停課'
                        }}
                    </Button>
                </div>
            </form>

            <div class="rounded-xl border border-sidebar-border/70 bg-card">
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-sidebar-border/60 px-4 py-3"
                >
                    <h2 class="text-base font-semibold">已設定清單</h2>
                    <div class="flex gap-1 text-sm">
                        <button
                            v-for="opt in [
                                { v: 'all' as const, t: '全部' },
                                { v: 'closure' as const, t: '停課' },
                                { v: 'makeup' as const, t: '補課' },
                            ]"
                            :key="opt.v"
                            type="button"
                            class="rounded-md px-2.5 py-1"
                            :class="
                                listFilter === opt.v
                                    ? 'bg-muted font-medium text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="listFilter = opt.v"
                        >
                            {{ opt.t }}
                        </button>
                    </div>
                </div>

                <p
                    v-if="filteredExceptions.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    {{
                        listFilter === 'all'
                            ? '尚無停課或補課設定。'
                            : listFilter === 'closure'
                              ? '尚無停課設定。'
                              : '尚無補課設定。'
                    }}
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/30 text-muted-foreground">
                                <th class="px-4 py-2 text-left font-normal">
                                    類型
                                </th>
                                <th class="px-4 py-2 text-left font-normal">
                                    日期
                                </th>
                                <th class="px-4 py-2 text-left font-normal">
                                    原因
                                </th>
                                <th class="px-4 py-2 text-left font-normal">
                                    範圍
                                </th>
                                <th class="px-4 py-2 text-left font-normal">
                                    時段
                                </th>
                                <th
                                    v-if="canManage"
                                    class="w-[64px] px-4 py-2 text-left font-normal"
                                />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in filteredExceptions"
                                :key="row.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-2.5">
                                    <span
                                        class="text-xs font-medium"
                                        :class="
                                            row.type === 'makeup'
                                                ? 'text-sky-700'
                                                : 'text-rose-700'
                                        "
                                    >
                                        {{
                                            row.type === 'makeup'
                                                ? '補課'
                                                : '停課'
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 tabular-nums whitespace-nowrap">
                                    {{ dateLabel(row) }}
                                </td>
                                <td class="px-4 py-2.5">{{ row.name }}</td>
                                <td class="max-w-[14rem] px-4 py-2.5 text-muted-foreground">
                                    {{ scopeLabel(row) }}
                                </td>
                                <td class="px-4 py-2.5 text-muted-foreground whitespace-nowrap">
                                    <template
                                        v-if="
                                            row.type === 'makeup' &&
                                            (row.start_time || row.end_time)
                                        "
                                    >
                                        {{ row.start_time || '—' }}–{{
                                            row.end_time || '—'
                                        }}
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td v-if="canManage" class="px-4 py-2.5">
                                    <TableDeleteIconButton
                                        @click="destroyException(row)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== 全校假日 ===== -->
        <div v-show="mainTab === 'school'" class="space-y-4">
            <div
                v-if="canManage"
                class="grid gap-4 lg:grid-cols-[1.1fr_1fr]"
            >
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-5"
                >
                    <h2 class="text-base font-semibold">匯入官方假日</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        政府「Google 行事曆專用」CSV；會略過一般週末例假日。
                    </p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1 grid gap-1">
                            <Label for="holiday_csv">CSV 檔案</Label>
                            <Input
                                id="holiday_csv"
                                type="file"
                                accept=".csv,text/csv"
                                @change="onFileChange"
                            />
                        </div>
                        <Button
                            type="button"
                            class="shrink-0"
                            :disabled="importing || !importFile"
                            @click="submitImport"
                        >
                            {{ importing ? '匯入中…' : '開始匯入' }}
                        </Button>
                    </div>
                    <p v-if="importError" class="mt-2 text-xs text-destructive">
                        {{ importError }}
                    </p>
                </div>

                <form
                    class="rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-5"
                    @submit.prevent="submitManual"
                >
                    <h2 class="text-base font-semibold">手動加一天</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        全校適用。若只要某年級／某科停課，請改到「停課／補課」。
                    </p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1.2fr_auto] sm:items-end">
                        <div class="grid gap-1">
                            <Label for="date">日期</Label>
                            <Input
                                id="date"
                                v-model="form.date"
                                type="date"
                                required
                            />
                            <InputError :message="form.errors.date" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="name">名稱</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="例：暑假連假"
                                required
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <Button
                            type="submit"
                            class="sm:mb-0"
                            :disabled="form.processing"
                        >
                            新增
                        </Button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-card">
                <div
                    class="border-b border-sidebar-border/60 px-4 py-3"
                >
                    <h2 class="text-base font-semibold">假日清單</h2>
                </div>
                <p
                    v-if="holidays.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    尚無全校假日。可先匯入官方 CSV 或手動新增。
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/30 text-muted-foreground">
                                <th class="px-4 py-2 text-left font-normal">
                                    日期
                                </th>
                                <th class="px-4 py-2 text-left font-normal">
                                    名稱
                                </th>
                                <th class="px-4 py-2 text-left font-normal">
                                    來源
                                </th>
                                <th
                                    v-if="canManage"
                                    class="w-[64px] px-4 py-2 text-left font-normal"
                                />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="h in holidays"
                                :key="h.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-2.5 tabular-nums">
                                    {{ h.date }}
                                </td>
                                <td class="px-4 py-2.5">{{ h.name }}</td>
                                <td class="px-4 py-2.5 text-muted-foreground">
                                    {{ h.is_custom ? '自訂' : '官方' }}
                                </td>
                                <td v-if="canManage" class="px-4 py-2.5">
                                    <TableDeleteIconButton
                                        @click="destroyHoliday(h.id)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
