<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, Download, Receipt, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import StudentStatusFilterSelect from '@/components/StudentStatusFilterSelect.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import TableIconLink from '@/components/table/TableIconLink.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { studentStatusLabel, studentStatusPillClass } from '@/lib/studentStatus';

type Student = {
    id: number;
    student_code: string | null;
    name: string;
    grade_name: string | null;
    phone: string | null;
    parent_name: string | null;
    parent_phone: string | null;
    status: 'active' | 'paused' | 'graduated';
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    students: Paginated<Student>;
    filters: {
        name: string;
        status: string;
    };
    importMeta: {
        currentAcademicYear: { year_code: string; name: string } | null;
        gradeNames: string[];
    };
}>();

const page = usePage();
const canCreateEdit =
    page.props.auth.user.role === 'super_admin' ||
    page.props.auth.user.role === 'admin' ||
    page.props.auth.user.role === 'teacher';
const canDelete =
    page.props.auth.user.role === 'super_admin' ||
    page.props.auth.user.role === 'admin';

const flash = computed(
    () =>
        page.props.flash as {
            success?: string;
            import_errors?: string[];
            open_import?: boolean;
        } | undefined,
);
const successMessage = computed(() => flash.value?.success);
const importErrors = computed(() => flash.value?.import_errors ?? []);

const importOpen = ref(false);
const importForm = useForm({
    file: null as File | null,
});
const fileInput = ref<HTMLInputElement | null>(null);
const fileName = ref('');

watch(
    () => flash.value?.open_import,
    (open) => {
        if (open) {
            importOpen.value = true;
        }
    },
    { immediate: true },
);

const openImport = () => {
    importOpen.value = true;
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    importForm.file = file;
    fileName.value = file?.name ?? '';
};

const submitImport = () => {
    if (!importForm.file) {
        return;
    }

    importForm.post('/students/import', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importForm.file = null;
            fileName.value = '';

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
        onSuccess: (visit) => {
            const nextFlash = visit.props.flash as
                | { open_import?: boolean }
                | undefined;
            importOpen.value = !!nextFlash?.open_import;
        },
    });
};

const filterForm = useForm({
    name: props.filters.name ?? '',
    status: props.filters.status ?? '',
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: '學生管理', href: '/students' }],
    },
});

const destroyStudent = (id: number) => {
    if (!window.confirm('確定要刪除此學生嗎？')) {
        return;
    }

    router.delete(`/students/${id}`);
};

const applyFilters = () => {
    filterForm.get('/students', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.name = '';
    filterForm.status = '';
    applyFilters();
};
</script>

<template>
    <Head title="學生管理" />
    <div class="page-shell">
        <PageHeader title="學生管理">
            <template v-if="canCreateEdit" #actions>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" type="button" @click="openImport">
                        Excel 匯入
                    </Button>
                    <Button as-child>
                        <Link href="/students/create">新增學生</Link>
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>

        <form
            class="list-filter-panel rounded-xl border border-sidebar-border/70 bg-card p-4"
            @submit.prevent="applyFilters"
        >
            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_auto_auto] lg:items-end"
            >
                <div class="grid gap-1">
                    <label for="name" class="text-sm font-medium">姓名</label>
                    <input
                        id="name"
                        v-model="filterForm.name"
                        type="text"
                        class="h-10 rounded-md border px-3"
                        placeholder="輸入學生姓名"
                        @change="applyFilters"
                    />
                </div>
                <div class="grid gap-1">
                    <label for="status" class="text-sm font-medium">狀態</label>
                    <StudentStatusFilterSelect
                        id="status"
                        v-model="filterForm.status"
                        :disabled="filterForm.processing"
                        @update:model-value="applyFilters"
                    />
                </div>
                <Button
                    type="submit"
                    class="h-10 w-full"
                    :disabled="filterForm.processing"
                    >查詢</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 w-full"
                    :disabled="filterForm.processing"
                    @click="resetFilters"
                >
                    清除
                </Button>
            </div>
        </form>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="student in students.data"
                :key="student.id"
                :title="student.name"
                :subtitle="student.phone ?? undefined"
            >
                <template #badge>
                    <span :class="studentStatusPillClass(student.status)">
                        {{ studentStatusLabel(student.status) }}
                    </span>
                </template>
                <MobileRecordField label="學號">{{
                    student.student_code ?? '—'
                }}</MobileRecordField>
                <MobileRecordField label="年級">{{
                    student.grade_name ?? '—'
                }}</MobileRecordField>
                <MobileRecordField label="家長">
                    {{ student.parent_name ?? '—' }}
                    <span
                        v-if="student.parent_phone"
                        class="block text-xs font-normal text-muted-foreground"
                    >
                        {{ student.parent_phone }}
                    </span>
                </MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableIconLink
                            v-if="canCreateEdit"
                            :href="`/students/${student.id}/courses-schedule`"
                            label="課程與行事曆"
                            :icon="CalendarDays"
                        />
                        <TableIconLink
                            v-if="canCreateEdit"
                            :href="`/students/${student.id}/payments`"
                            label="繳費明細"
                            :icon="Receipt"
                        />
                        <TableEditIconLink
                            v-if="canCreateEdit"
                            :href="`/students/${student.id}/edit`"
                        />
                        <TableDeleteIconButton
                            v-if="canDelete"
                            @click="destroyStudent(student.id)"
                        />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="students.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                找不到符合條件的學生資料。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left font-semibold">學號</th>
                        <th class="py-2 text-left font-semibold">姓名</th>
                        <th class="py-2 text-left font-semibold">年級</th>
                        <th class="py-2 text-left font-semibold">學生電話</th>
                        <th class="py-2 text-left font-semibold">家長</th>
                        <th class="py-2 text-left font-semibold">家長電話</th>
                        <th class="py-2 text-left font-semibold">狀態</th>
                        <th class="py-2 text-left font-semibold">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="student in students.data"
                        :key="student.id"
                        class="border-b"
                    >
                        <td class="py-2.5 font-mono text-xs">
                            {{ student.student_code ?? '—' }}
                        </td>
                        <td class="py-2.5">{{ student.name }}</td>
                        <td class="py-2.5">{{ student.grade_name ?? '—' }}</td>
                        <td class="py-2.5">{{ student.phone ?? '-' }}</td>
                        <td class="py-2.5">{{ student.parent_name ?? '-' }}</td>
                        <td class="py-2.5">{{ student.parent_phone ?? '-' }}</td>
                        <td class="py-2.5">
                            <span :class="studentStatusPillClass(student.status)">
                                {{ studentStatusLabel(student.status) }}
                            </span>
                        </td>
                        <td class="py-2.5">
                            <div class="flex flex-wrap gap-1">
                                <TableIconLink
                                    v-if="canCreateEdit"
                                    :href="`/students/${student.id}/courses-schedule`"
                                    label="課程與行事曆"
                                    :icon="CalendarDays"
                                />
                                <TableIconLink
                                    v-if="canCreateEdit"
                                    :href="`/students/${student.id}/payments`"
                                    label="繳費明細"
                                    :icon="Receipt"
                                />
                                <TableEditIconLink
                                    v-if="canCreateEdit"
                                    :href="`/students/${student.id}/edit`"
                                />
                                <TableDeleteIconButton
                                    v-if="canDelete"
                                    @click="destroyStudent(student.id)"
                                />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="students.data.length === 0">
                        <td
                            colspan="8"
                            class="py-8 text-center text-muted-foreground"
                        >
                            找不到符合條件的學生資料。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="students.links" />

        <Dialog v-model:open="importOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Excel 匯入學生</DialogTitle>
                    <DialogDescription>
                        下載範本、填好後上傳。新學生會自動產生學號；同學年同年級同名則覆蓋更新。
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="importErrors.length"
                    class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950"
                >
                    <p class="font-medium">以下列未匯入：</p>
                    <ul
                        class="mt-1.5 max-h-72 list-inside list-disc space-y-0.5 overflow-y-auto"
                    >
                        <li v-for="(err, i) in importErrors" :key="i">
                            {{ err }}
                        </li>
                    </ul>
                </div>

                <div class="space-y-3">
                    <div>
                        <p class="text-sm font-medium">1. 下載範本</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            第一列是表頭，請勿改欄位名稱。第二列是範例，可刪除後換成真實資料。
                        </p>
                        <Button class="mt-2" variant="outline" size="sm" as-child>
                            <a href="/students/import/template">
                                <Download class="size-4" />
                                下載 Excel 範本
                            </a>
                        </Button>
                    </div>

                    <div
                        class="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground"
                    >
                        <p class="font-medium text-foreground">填寫說明</p>
                        <ul class="mt-1.5 list-inside list-disc space-y-1">
                            <li>
                                表頭有
                                <span class="text-foreground">*</span>
                                的是必填：*學年、*年級、*姓名
                            </li>
                            <li>
                                年級請填{{
                                    importMeta.gradeNames.join('／') ||
                                    '系統已設定的年級'
                                }}；學年請填學年代碼
                                <template
                                    v-if="importMeta.currentAcademicYear"
                                >
                                    （目前為
                                    {{
                                        importMeta.currentAcademicYear
                                            .year_code
                                    }}）
                                </template>
                            </li>
                            <li>
                                兄弟姊妹可填兩組：稱謂請填哥哥／姊姊／弟弟／妹妹
                            </li>
                            <li>狀態：在學／停課／已畢業（留白＝在學）</li>
                            <li>
                                同學年、同年級、同名：以本次 Excel
                                覆蓋該生資料（學號不變）；空白欄位會清空對應內容
                            </li>
                        </ul>
                    </div>

                    <form class="space-y-2" @submit.prevent="submitImport">
                        <p class="text-sm font-medium">2. 上傳檔案</p>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                            class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-foreground"
                            @change="onFileChange"
                        />
                        <p
                            v-if="fileName"
                            class="text-xs text-muted-foreground"
                        >
                            已選：{{ fileName }}
                        </p>
                        <InputError :message="importForm.errors.file" />
                    </form>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="importOpen = false"
                    >
                        取消
                    </Button>
                    <Button
                        type="button"
                        :disabled="!importForm.file || importForm.processing"
                        @click="submitImport"
                    >
                        <Upload class="size-4" />
                        {{ importForm.processing ? '匯入中…' : '開始匯入' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
