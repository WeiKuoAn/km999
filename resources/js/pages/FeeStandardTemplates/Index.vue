<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, FileDown, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Template = {
    id: number;
    title: string;
    grade_name: string | null;
    academic_year_name: string | null;
    deadline_label: string | null;
    items_count: number;
    is_active: boolean;
    sort_order: number;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type StudentOption = {
    id: number;
    name: string;
    student_code: string;
};

const props = defineProps<{
    templates: Paginated<Template>;
    filters: { grade: string };
    gradeOptions: string[];
    canManage: boolean;
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: '收費標準單', href: '/fee-standard-templates' }],
    },
});

const activeGrade = computed(() => props.filters.grade || '全部');

const filterGrade = (grade: string) => {
    router.get(
        '/fee-standard-templates',
        grade === '全部' ? {} : { grade },
        { preserveState: true, replace: true },
    );
};

const destroyTemplate = (id: number) => {
    if (!window.confirm('確定要刪除此收費標準模板嗎？')) {
        return;
    }
    router.delete(`/fee-standard-templates/${id}`);
};

const duplicateTemplate = (id: number) => {
    if (!window.confirm('要複製這份模板嗎？會導向編輯頁。')) {
        return;
    }
    router.post(`/fee-standard-templates/${id}/duplicate`);
};

const exportOpen = ref(false);
const exportTemplate = ref<Template | null>(null);
const exportScope = ref<'grade' | 'students'>('grade');
const students = ref<StudentOption[]>([]);
const selectedIds = ref<number[]>([]);
const studentsLoading = ref(false);
const exportBusy = ref(false);
const exportMessage = ref('');
const includeExported = ref(false);
const exportedCount = ref(0);

const loadExportStudents = async () => {
    const template = exportTemplate.value;
    if (!template) {
        return;
    }
    studentsLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (includeExported.value) {
            params.set('include_exported', '1');
        }
        const qs = params.toString();
        const res = await fetch(
            `/fee-standard-templates/${template.id}/students${qs ? `?${qs}` : ''}`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
        if (!res.ok) {
            throw new Error('無法載入學生');
        }
        const data = (await res.json()) as {
            students: StudentOption[];
            exported_count?: number;
        };
        students.value = data.students ?? [];
        exportedCount.value = data.exported_count ?? 0;
        selectedIds.value = students.value.map((s) => s.id);
    } catch {
        exportMessage.value = '無法載入學生名單。';
    } finally {
        studentsLoading.value = false;
    }
};

const openExport = async (template: Template) => {
    exportTemplate.value = template;
    exportScope.value = 'grade';
    selectedIds.value = [];
    students.value = [];
    exportMessage.value = '';
    includeExported.value = false;
    exportedCount.value = 0;
    exportOpen.value = true;
    await loadExportStudents();
};

const closeExport = () => {
    exportOpen.value = false;
    exportTemplate.value = null;
};

const onIncludeExportedChange = async () => {
    await loadExportStudents();
};

const toggleStudent = (id: number) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};

const selectAllStudents = () => {
    selectedIds.value = students.value.map((s) => s.id);
};

const clearStudents = () => {
    selectedIds.value = [];
};

const csrfToken = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const pollExport = async (id: string) => {
    for (let i = 0; i < 90; i++) {
        await new Promise((r) => setTimeout(r, 1000));
        const res = await fetch(`/fee-standard-templates/exports/${id}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error('查詢匯出狀態失敗');
        }
        const data = (await res.json()) as {
            status: string;
            message?: string;
        };
        exportMessage.value = data.message ?? data.status;
        if (data.status === 'ready') {
            window.location.href = `/fee-standard-templates/exports/${id}/download`;
            return;
        }
        if (data.status === 'failed') {
            throw new Error(data.message ?? '產生失敗');
        }
    }
    throw new Error('產生逾時，請稍後再試');
};

const runExport = async () => {
    const template = exportTemplate.value;
    if (!template) {
        return;
    }
    if (exportScope.value === 'students' && selectedIds.value.length === 0) {
        exportMessage.value = '請至少選擇一位學生。';
        return;
    }

    exportBusy.value = true;
    exportMessage.value = '正在產生 PDF…';
    try {
        const params = new URLSearchParams();
        params.set('scope', exportScope.value);
        if (exportScope.value === 'students') {
            for (const id of selectedIds.value) {
                params.append('student_ids[]', String(id));
            }
        }
        if (includeExported.value) {
            params.set('include_exported', '1');
        }
        const count =
            exportScope.value === 'grade'
                ? students.value.length
                : selectedIds.value.length;
        if (count > 80) {
            params.set('async', '1');
            const res = await fetch(
                `/fee-standard-templates/${template.id}/pdf?${params}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                },
            );
            if (!res.ok) {
                const err = await res.json().catch(() => null);
                throw new Error(
                    (err as { message?: string } | null)?.message ??
                        '無法開始匯出',
                );
            }
            const data = (await res.json()) as { id: string };
            await pollExport(data.id);
            closeExport();
            return;
        }

        window.open(
            `/fee-standard-templates/${template.id}/pdf?${params.toString()}`,
            '_blank',
        );
        closeExport();
    } catch (e) {
        exportMessage.value =
            e instanceof Error ? e.message : '匯出失敗，請稍後再試';
    } finally {
        exportBusy.value = false;
    }
};
</script>

<template>
    <Head title="收費標準單" />
    <div class="page-shell">
        <PageHeader
            title="收費標準單"
            description="依年級設定家長勾選項目，再產出全頁 PDF（空白勾選格）。"
        >
            <template v-if="canManage" #actions>
                <Button as-child>
                    <Link href="/fee-standard-templates/create">
                        <Plus class="size-4" />
                        新增模板
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="g in gradeOptions"
                :key="g"
                type="button"
                class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                :class="
                    activeGrade === g
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
                "
                @click="filterGrade(g)"
            >
                {{ g }}
            </button>
        </div>

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="row in templates.data"
                :key="row.id"
                :title="row.title"
                :subtitle="row.grade_name ?? undefined"
            >
                <template #badge>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            row.is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ row.is_active ? '啟用' : '停用' }}
                    </span>
                </template>
                <MobileRecordField label="學年">{{
                    row.academic_year_name ?? '不限'
                }}</MobileRecordField>
                <MobileRecordField label="項目數">{{
                    row.items_count
                }}</MobileRecordField>
                <MobileRecordField label="截止">{{
                    row.deadline_label || '—'
                }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openExport(row)"
                        >
                            <FileDown class="size-4" />
                            產出 PDF
                        </Button>
                        <template v-if="canManage">
                            <TableEditIconLink
                                :href="`/fee-standard-templates/${row.id}/edit`"
                            />
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="duplicateTemplate(row.id)"
                            >
                                <Copy class="size-4" />
                            </Button>
                            <TableDeleteIconButton
                                @click="destroyTemplate(row.id)"
                            />
                        </template>
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="templates.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無收費標準模板。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2.5 text-left">標題</th>
                        <th class="py-2.5 text-left">年級</th>
                        <th class="py-2.5 text-left">學年</th>
                        <th class="py-2.5 text-left">項目</th>
                        <th class="py-2.5 text-left">截止</th>
                        <th class="w-[4.5rem] py-2.5 text-left">狀態</th>
                        <th class="w-[10rem] py-2.5 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in templates.data"
                        :key="row.id"
                        class="border-b align-middle"
                    >
                        <td class="py-3 font-medium">{{ row.title }}</td>
                        <td class="py-3 text-muted-foreground">
                            {{ row.grade_name ?? '—' }}
                        </td>
                        <td class="py-3 text-muted-foreground">
                            {{ row.academic_year_name ?? '不限' }}
                        </td>
                        <td class="py-3 tabular-nums">{{ row.items_count }}</td>
                        <td class="py-3 text-muted-foreground">
                            {{ row.deadline_label || '—' }}
                        </td>
                        <td class="py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    row.is_active
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ row.is_active ? '啟用' : '停用' }}
                            </span>
                        </td>
                        <td class="py-3">
                            <div class="flex items-center gap-0.5">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openExport(row)"
                                        >
                                            <FileDown class="size-4" />
                                            <span class="sr-only">產出 PDF</span>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>產出 PDF</TooltipContent>
                                </Tooltip>
                                <template v-if="canManage">
                                    <TableEditIconLink
                                        :href="`/fee-standard-templates/${row.id}/edit`"
                                    />
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                @click="
                                                    duplicateTemplate(row.id)
                                                "
                                            >
                                                <Copy class="size-4" />
                                                <span class="sr-only">複製</span>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>複製</TooltipContent>
                                    </Tooltip>
                                    <TableDeleteIconButton
                                        @click="destroyTemplate(row.id)"
                                    />
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="templates.links" />

        <div
            v-if="exportOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            @click.self="closeExport"
        >
            <div
                class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border bg-background p-4 shadow-lg"
            >
                <h2 class="text-base font-semibold">產出收費標準 PDF</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ exportTemplate?.title }}（{{
                        exportTemplate?.grade_name
                    }}）
                </p>

                <div class="mt-4 space-y-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="exportScope"
                            type="radio"
                            value="grade"
                            class="size-4"
                        />
                        清單上的全部學生（{{ students.length }} 人）
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="exportScope"
                            type="radio"
                            value="students"
                            class="size-4"
                        />
                        指定學生
                    </label>
                    <p
                        v-if="exportedCount > 0"
                        class="text-xs text-muted-foreground"
                    >
                        已匯出 {{ exportedCount }} 人（預設不列出）
                    </p>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="includeExported"
                            type="checkbox"
                            class="size-4"
                            @change="onIncludeExportedChange"
                        />
                        顯示已匯出學生（可再匯出）
                    </label>
                </div>

                <div
                    v-if="exportScope === 'students'"
                    class="mt-3 space-y-2 rounded-md border p-3"
                >
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="studentsLoading"
                            @click="selectAllStudents"
                        >
                            全選
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="clearStudents"
                        >
                            清除
                        </Button>
                    </div>
                    <p
                        v-if="studentsLoading"
                        class="text-sm text-muted-foreground"
                    >
                        載入中…
                    </p>
                    <ul
                        v-else
                        class="max-h-56 space-y-1 overflow-y-auto text-sm"
                    >
                        <li v-for="s in students" :key="s.id">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    class="size-4"
                                    :checked="selectedIds.includes(s.id)"
                                    @change="toggleStudent(s.id)"
                                />
                                <span class="text-muted-foreground tabular-nums">{{
                                    s.student_code || '—'
                                }}</span>
                                <span>{{ s.name }}</span>
                            </label>
                        </li>
                        <li
                            v-if="students.length === 0"
                            class="text-muted-foreground"
                        >
                            {{
                                exportedCount > 0
                                    ? '尚未匯出的學生已全部匯出，可勾選上方「顯示已匯出」。'
                                    : '此年級目前沒有可見學生。'
                            }}
                        </li>
                    </ul>
                </div>

                <p
                    v-if="exportMessage"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{ exportMessage }}
                </p>

                <div class="mt-4 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="exportBusy"
                        @click="closeExport"
                    >
                        取消
                    </Button>
                    <Button
                        type="button"
                        :disabled="exportBusy || studentsLoading"
                        @click="runExport"
                    >
                        {{ exportBusy ? '產生中…' : '產出 PDF' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
