<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileDown, LoaderCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Row = {
    student_id: number;
    student_code: string | null;
    student_name: string;
    grade_name: string | null;
    subjects_months: string;
    subjects_label: string;
    period_label: string;
    start_year: number;
    start_month: number;
    end_year: number;
    end_month: number;
    fee: number;
    note: string;
    pay_cycle?: string | null;
    pay_cycle_label?: string | null;
    fee_source?: string;
    course_ids?: number[];
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    rows: Paginated<Row>;
    total_count?: number;
    previewed?: boolean;
    grade_levels?: Array<{ id: number; name: string }>;
    period_options?: Array<{ value: string; label: string }>;
    filters: {
        q: string;
        grade_level_id?: string;
        period_start?: string;
        year?: string;
    };
}>();

const q = ref(props.filters.q ?? '');
const gradeLevelId = ref(props.filters.grade_level_id ?? '');
const periodStart = ref(props.filters.period_start ?? '');

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        gradeLevelId.value = filters.grade_level_id ?? '';
        periodStart.value = filters.period_start ?? '';
    },
);

/** 換年級時清掉月份，並向後端載入該年級對應的帳期選項（不產生名單） */
watch(gradeLevelId, (id, previous) => {
    if (id === previous) {
        return;
    }
    periodStart.value = '';
    router.get(
        '/payment-lists',
        {
            q: q.value.trim() || undefined,
            grade_level_id: id || undefined,
        },
        {
            preserveState: true,
            replace: true,
            only: [
                'period_options',
                'filters',
                'previewed',
                'rows',
                'total_count',
            ],
        },
    );
});

const canPreview = computed(
    () =>
        gradeLevelId.value !== '' &&
        periodStart.value !== '' &&
        (props.period_options?.some((o) => o.value === periodStart.value) ??
            false),
);

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const detailHref = (row: Row) => {
    const params = new URLSearchParams();
    params.set('student_id', String(row.student_id));
    for (const id of row.course_ids ?? []) {
        params.append('course_ids[]', String(id));
    }
    if (row.pay_cycle) {
        params.set('pay_cycle', row.pay_cycle);
    }
    return `/student-payments/create?${params.toString()}`;
};

const filterParams = () => ({
    q: q.value.trim() || undefined,
    grade_level_id: gradeLevelId.value || undefined,
    period_start: periodStart.value || undefined,
});

const applyFilters = () => {
    if (!canPreview.value) {
        return;
    }
    router.get('/payment-lists', filterParams(), {
        preserveState: true,
        replace: true,
    });
};

const listQuery = () => {
    const params = new URLSearchParams();
    if (q.value.trim()) params.set('q', q.value.trim());
    if (gradeLevelId.value) params.set('grade_level_id', gradeLevelId.value);
    if (periodStart.value) params.set('period_start', periodStart.value);
    return params.toString();
};

const pdfHref = () => {
    const qs = listQuery();
    return qs ? `/payment-lists/pdf?${qs}` : '/payment-lists/pdf';
};

const noticeHref = (row: Row) => {
    const params = new URLSearchParams();
    params.set('student_id', String(row.student_id));
    if (q.value.trim()) params.set('q', q.value.trim());
    if (gradeLevelId.value) params.set('grade_level_id', gradeLevelId.value);
    if (periodStart.value) params.set('period_start', periodStart.value);
    return `/payment-lists/notices.pdf?${params.toString()}`;
};

const noticesExporting = ref(false);
const noticesExportMessage = ref('');
let noticesPollTimer: ReturnType<typeof setTimeout> | null = null;

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const clearNoticesPoll = () => {
    if (noticesPollTimer !== null) {
        clearTimeout(noticesPollTimer);
        noticesPollTimer = null;
    }
};

const pollNoticesExport = async (id: string) => {
    try {
        const res = await fetch(`/payment-lists/notices/exports/${id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error('查詢匯出狀態失敗');
        }
        const data = (await res.json()) as {
            status: string;
            message?: string | null;
            download_url?: string | null;
        };
        noticesExportMessage.value = data.message ?? '處理中…';

        if (data.status === 'ready' && data.download_url) {
            noticesExporting.value = false;
            noticesExportMessage.value = '完成，正在下載…';
            window.location.href = data.download_url;
            return;
        }
        if (data.status === 'failed') {
            noticesExporting.value = false;
            noticesExportMessage.value = data.message || '產生失敗';
            return;
        }

        noticesPollTimer = setTimeout(() => {
            void pollNoticesExport(id);
        }, 1500);
    } catch (e) {
        noticesExporting.value = false;
        noticesExportMessage.value =
            e instanceof Error ? e.message : '匯出失敗';
    }
};

const startNoticesExport = async () => {
    if (noticesExporting.value || !canPreview.value) {
        return;
    }
    clearNoticesPoll();
    noticesExporting.value = true;
    noticesExportMessage.value = '已排入背景產生…';

    try {
        const res = await fetch('/payment-lists/notices/exports', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                q: q.value.trim() || null,
                grade_level_id: Number(gradeLevelId.value),
                period_start: periodStart.value,
            }),
        });
        if (!res.ok) {
            const err = (await res.json().catch(() => null)) as {
                message?: string;
            } | null;
            throw new Error(err?.message || '無法開始匯出');
        }
        const data = (await res.json()) as { id: string; message?: string };
        noticesExportMessage.value = data.message || '背景產生中…';
        await pollNoticesExport(data.id);
    } catch (e) {
        noticesExporting.value = false;
        noticesExportMessage.value =
            e instanceof Error ? e.message : '匯出失敗';
    }
};

onBeforeUnmount(() => {
    clearNoticesPoll();
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: '繳費名單', href: '/payment-lists' }],
    },
});
</script>

<template>
    <Head title="繳費名單" />
    <div class="page-shell">
        <PageHeader
            title="繳費名單"
            description="先選年級帶出對應帳期；選好起始月會自動產生名單。"
        >
            <template #actions>
                <Button variant="outline" as-child :disabled="!canPreview || !previewed">
                    <a
                        v-if="canPreview && previewed"
                        :href="pdfHref()"
                        target="_blank"
                        rel="noopener"
                    >
                        <FileDown class="size-4" />
                        匯出 PDF
                    </a>
                    <span v-else class="inline-flex items-center gap-2">
                        <FileDown class="size-4" />
                        匯出 PDF
                    </span>
                </Button>
                <Button
                    variant="outline"
                    type="button"
                    :disabled="noticesExporting || !canPreview || !previewed"
                    @click="startNoticesExport"
                >
                    <LoaderCircle
                        v-if="noticesExporting"
                        class="size-4 animate-spin"
                    />
                    <FileDown v-else class="size-4" />
                    {{ noticesExporting ? '產生中…' : '匯出繳費條 PDF' }}
                </Button>
                <Button as-child>
                    <Link href="/student-payments/create">新增收款</Link>
                </Button>
            </template>
        </PageHeader>

        <p
            v-if="noticesExportMessage"
            class="mb-3 text-sm"
            :class="
                noticesExporting
                    ? 'text-muted-foreground'
                    : noticesExportMessage.includes('失敗')
                      ? 'text-destructive'
                      : 'text-emerald-700'
            "
        >
            {{ noticesExportMessage }}
        </p>

        <form
            class="mb-4 flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:flex-row sm:flex-wrap sm:items-end"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-2 sm:w-40">
                <Label for="grade_level_id">年級</Label>
                <select
                    id="grade_level_id"
                    v-model="gradeLevelId"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    required
                >
                    <option value="">請選擇</option>
                    <option
                        v-for="grade in grade_levels ?? []"
                        :key="grade.id"
                        :value="String(grade.id)"
                    >
                        {{ grade.name }}
                    </option>
                </select>
            </div>
            <div class="grid gap-2 sm:min-w-[14rem] sm:flex-1">
                <Label for="period_start">帳期起始月</Label>
                <select
                    id="period_start"
                    v-model="periodStart"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                    :disabled="!gradeLevelId"
                    required
                    @change="applyFilters"
                >
                    <option value="">
                        {{
                            !gradeLevelId
                                ? '請先選年級'
                                : (period_options?.length ?? 0) === 0
                                  ? '此年級尚無收費期別'
                                  : '請選擇'
                        }}
                    </option>
                    <option
                        v-for="option in period_options ?? []"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div class="grid flex-1 gap-2 sm:min-w-[10rem]">
                <Label for="q">搜尋學生（選填）</Label>
                <Input id="q" v-model="q" placeholder="學號或姓名" />
            </div>
            <Button type="submit" :disabled="!canPreview">預覽名單</Button>
        </form>

        <p
            v-if="previewed && typeof total_count === 'number'"
            class="mb-3 text-sm text-muted-foreground"
        >
            共 {{ total_count }} 位待繳
        </p>

        <template v-if="!previewed">
            <p
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                請先選擇年級（會帶出對應帳期），再選起始月後按「預覽名單」。
            </p>
        </template>

        <template v-else>
            <div class="mobile-card-list">
                <MobileRecordCard
                    v-for="row in rows.data"
                    :key="`${row.student_id}-${row.start_year}-${row.start_month}`"
                    :title="row.student_name"
                    :subtitle="row.subjects_months"
                >
                    <MobileRecordField label="學號">{{
                        row.student_code ?? '—'
                    }}</MobileRecordField>
                    <MobileRecordField label="費用">{{
                        formatMoney(row.fee)
                    }}</MobileRecordField>
                    <MobileRecordField label="繳別">{{
                        row.pay_cycle_label ?? '—'
                    }}</MobileRecordField>
                    <MobileRecordField v-if="row.note" label="備註">{{
                        row.note
                    }}</MobileRecordField>
                    <template #actions>
                        <div class="mobile-card-actions">
                            <Button variant="outline" size="sm" as-child>
                                <a
                                    :href="noticeHref(row)"
                                    target="_blank"
                                    rel="noopener"
                                    >繳費條</a
                                >
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="detailHref(row)">確認收款</Link>
                            </Button>
                        </div>
                    </template>
                </MobileRecordCard>
                <p
                    v-if="rows.data.length === 0"
                    class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
                >
                    此年級／帳期目前沒有待繳名單（可能下一期已繳清，或尚無已繳紀錄可推算）。
                </p>
            </div>

            <div
                class="desktop-table-wrap rounded-xl border border-sidebar-border/70"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/30">
                            <th class="px-3 py-2 text-left">學生姓名</th>
                            <th class="px-3 py-2 text-left">科目月份</th>
                            <th class="px-3 py-2 text-right">費用</th>
                            <th class="px-3 py-2 text-left">備註</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">
                                操作
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="`${row.student_id}-${row.start_year}-${row.start_month}`"
                            class="border-b"
                        >
                            <td class="px-3 py-2.5">
                                <div class="font-medium">
                                    {{ row.student_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ row.student_code ?? '—' }}
                                    <span v-if="row.grade_name">
                                        · {{ row.grade_name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div>{{ row.subjects_label }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ row.period_label }}
                                    <span v-if="row.pay_cycle_label">
                                        · {{ row.pay_cycle_label }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="px-3 py-2.5 text-right tabular-nums font-medium"
                            >
                                {{ formatMoney(row.fee) }}
                            </td>
                            <td class="px-3 py-2.5 text-muted-foreground">
                                {{ row.note || '—' }}
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-nowrap items-center gap-2">
                                    <Button variant="outline" size="sm" as-child>
                                        <a
                                            :href="noticeHref(row)"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <FileDown class="size-3.5" />
                                            繳費條 PDF
                                        </a>
                                    </Button>
                                    <Button size="sm" as-child>
                                        <Link :href="detailHref(row)"
                                            >確認收款</Link
                                        >
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.data.length === 0">
                            <td
                                colspan="5"
                                class="px-3 py-10 text-center text-muted-foreground"
                            >
                                此年級／帳期目前沒有待繳名單。
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ListPagination :links="rows.links" class="mt-4" />
        </template>
    </div>
</template>
