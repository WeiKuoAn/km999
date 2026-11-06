<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- form 是父頁 Inertia useForm */
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus, Table2, Trash2 } from 'lucide-vue-next';
import FeeStandardPreview from '@/components/fee-standards/FeeStandardPreview.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    FEE_STANDARD_HEADING_COLORS,
    emptyCheckItem,
    emptyTableItem,
    mergeFeeStandardItems,
    type FeeStandardFormData,
    type FeeStandardFormItem,
} from '@/lib/feeStandardForm';

const props = defineProps<{
    form: InertiaForm<FeeStandardFormData>;
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    defaultFooter: string;
}>();

const previewItems = computed(() => mergeFeeStandardItems(props.form));

const fieldError = (path: string): string | undefined =>
    (props.form.errors as Record<string, string | undefined>)[path];

/** 存檔後 items 為「勾選在前、表格在後」，對回錯誤索引 */
const checkError = (index: number, suffix: string): string | undefined =>
    fieldError(`items.${index}.${suffix}`);

const tableError = (index: number, suffix: string): string | undefined =>
    fieldError(`items.${props.form.check_items.length + index}.${suffix}`);

const addCheckItem = () => {
    props.form.check_items.push(emptyCheckItem(props.form.check_items.length));
};

const addTableItem = () => {
    props.form.tables.push(emptyTableItem(props.form.tables.length));
};

const removeCheckItem = (index: number) => {
    if (props.form.check_items.length <= 1 && props.form.tables.length === 0) {
        return;
    }
    props.form.check_items.splice(index, 1);
};

const removeTableItem = (index: number) => {
    if (props.form.tables.length <= 1 && props.form.check_items.length === 0) {
        return;
    }
    // 若沒有勾選、只剩最後一個表格，刪掉後補一個空勾選以免整單空白
    props.form.tables.splice(index, 1);
    if (props.form.check_items.length === 0 && props.form.tables.length === 0) {
        props.form.check_items.push(emptyCheckItem(0));
    }
};

const canRemoveCheck = computed(
    () =>
        props.form.check_items.length > 1 || props.form.tables.length > 0,
);

const canRemoveTable = computed(() => props.form.tables.length > 0);

const addTableRow = (item: FeeStandardFormItem) => {
    const cols = Math.max(2, item.table_headers.length || 3);
    item.table_rows.push(Array.from({ length: cols }, () => ''));
};

const removeTableRow = (item: FeeStandardFormItem, rowIndex: number) => {
    if (item.table_rows.length <= 1) {
        return;
    }
    item.table_rows.splice(rowIndex, 1);
};

const addTableColumn = (item: FeeStandardFormItem) => {
    if (item.table_headers.length >= 6) {
        return;
    }
    item.table_headers.push('');
    item.table_rows.forEach((row) => row.push(''));
};

const removeTableColumn = (item: FeeStandardFormItem, colIndex: number) => {
    if (item.table_headers.length <= 2) {
        return;
    }
    item.table_headers.splice(colIndex, 1);
    item.table_rows.forEach((row) => row.splice(colIndex, 1));
};
</script>

<template>
    <div
        class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]"
    >
        <div class="space-y-5">
            <section class="space-y-4 rounded-xl border p-4 sm:p-5">
                <div class="border-b pb-3">
                    <h2 class="text-sm font-semibold">基本資料</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        依學年／年級建立收費標準單；勾選項目與表格分開設定。
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="grade_level_id">年級</Label>
                        <select
                            id="grade_level_id"
                            v-model="form.grade_level_id"
                            class="h-9 w-full rounded-md border px-3"
                            required
                        >
                            <option value="">請選擇年級</option>
                            <option
                                v-for="g in gradeLevels"
                                :key="g.id"
                                :value="String(g.id)"
                            >
                                {{ g.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.grade_level_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="academic_year_id">學年</Label>
                        <select
                            id="academic_year_id"
                            v-model="form.academic_year_id"
                            class="h-9 w-full rounded-md border px-3"
                        >
                            <option value="">不指定（各學年通用）</option>
                            <option
                                v-for="y in academicYears"
                                :key="y.id"
                                :value="String(y.id)"
                            >
                                {{ y.name }}（{{ y.year_code }}）
                            </option>
                        </select>
                        <InputError :message="form.errors.academic_year_id" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="title">標題</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        placeholder="例：秋季班收費標準"
                        required
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="deadline_label">繳費截止（顯示文字）</Label>
                        <Input
                            id="deadline_label"
                            v-model="form.deadline_label"
                            placeholder="例：6/20"
                        />
                        <p class="text-xs text-muted-foreground">
                            會顯示為「請於 6/20 前繳納，以利作業」。
                        </p>
                        <InputError :message="form.errors.deadline_label" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="sort_order">排序</Label>
                        <Input
                            id="sort_order"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                        />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="footer_note">頁尾說明</Label>
                    <textarea
                        id="footer_note"
                        v-model="form.footer_note"
                        rows="2"
                        class="min-h-[4rem] w-full rounded-md border px-3 py-2 text-sm"
                        :placeholder="defaultFooter"
                    />
                    <p class="text-xs text-muted-foreground">
                        留白則使用預設電話文案。
                    </p>
                    <InputError :message="form.errors.footer_note" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-[var(--brand-green)]"
                    />
                    啟用
                </label>
            </section>

            <!-- ① 勾選項目 -->
            <section class="space-y-4 rounded-xl border p-4 sm:p-5">
                <div
                    class="flex flex-wrap items-start justify-between gap-2 border-b pb-3"
                >
                    <div>
                        <h2 class="text-sm font-semibold">勾選項目</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            主列為項目名稱；副列可寫金額公式。PDF 會印空白
                            □ 給家長勾選。
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addCheckItem"
                    >
                        <Plus class="size-4" />
                        新增勾選
                    </Button>
                </div>

                <div
                    v-if="form.check_items.length === 0"
                    class="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    目前沒有勾選項目（可只使用下方表格）。
                </div>

                <div
                    v-for="(item, i) in form.check_items"
                    :key="`check-${i}`"
                    class="space-y-2 rounded-md border bg-muted/30 p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            勾選 {{ i + 1 }}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            :disabled="!canRemoveCheck"
                            @click="removeCheckItem(i)"
                        >
                            <Trash2 class="size-4" />
                            刪除
                        </Button>
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`check-label-${i}`">主列</Label>
                        <Input
                            :id="`check-label-${i}`"
                            v-model="item.label"
                            placeholder="例：理化科研班首期 7-10 月優惠"
                            required
                        />
                        <InputError :message="checkError(i, 'label')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`check-detail-${i}`">副列（選填）</Label>
                        <textarea
                            :id="`check-detail-${i}`"
                            v-model="item.detail"
                            rows="2"
                            class="min-h-[3.5rem] w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="例：訂金 500 元(已繳) + 學費 12000 元 (含教材) = 11500 元"
                        />
                    </div>
                </div>
            </section>

            <!-- ② 收費表格 -->
            <section class="space-y-4 rounded-xl border p-4 sm:p-5">
                <div
                    class="flex flex-wrap items-start justify-between gap-2 border-b pb-3"
                >
                    <div>
                        <h2 class="text-sm font-semibold">收費表格</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            獨立於勾選項目；印出黃表頭表格（可增刪欄列）。預覽／PDF
                            會排在勾選項目之後。
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addTableItem"
                    >
                        <Table2 class="size-4" />
                        新增表格
                    </Button>
                </div>

                <div
                    v-if="form.tables.length === 0"
                    class="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    尚未新增表格。需要如「英數雙科」那類表格式收費時再新增。
                </div>

                <div
                    v-for="(item, i) in form.tables"
                    :key="`table-${i}`"
                    class="space-y-2 rounded-md border bg-muted/30 p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            表格 {{ i + 1 }}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            :disabled="!canRemoveTable"
                            @click="removeTableItem(i)"
                        >
                            <Trash2 class="size-4" />
                            刪除
                        </Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <div class="grid gap-1.5">
                            <Label :for="`table-label-${i}`">區塊標題</Label>
                            <Input
                                :id="`table-label-${i}`"
                                v-model="item.label"
                                placeholder="例：英文+數學雙科"
                                required
                            />
                            <InputError :message="tableError(i, 'label')" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`table-color-${i}`">標題顏色</Label>
                            <select
                                :id="`table-color-${i}`"
                                v-model="item.heading_color"
                                class="h-9 rounded-md border px-3"
                            >
                                <option
                                    v-for="c in FEE_STANDARD_HEADING_COLORS"
                                    :key="c.value"
                                    :value="c.value"
                                >
                                    {{ c.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-md border bg-white">
                        <table class="w-full min-w-[28rem] text-sm">
                            <thead>
                                <tr class="bg-[#ffe566]">
                                    <th
                                        v-for="(header, ci) in item.table_headers"
                                        :key="`h-${i}-${ci}`"
                                        class="border p-1.5 align-top"
                                    >
                                        <div class="space-y-1">
                                            <Input
                                                v-model="item.table_headers[ci]"
                                                class="h-8 bg-white/80 text-center text-xs"
                                                :placeholder="`欄 ${ci + 1}`"
                                            />
                                            <button
                                                type="button"
                                                class="text-[11px] text-muted-foreground hover:text-destructive disabled:opacity-40"
                                                :disabled="
                                                    item.table_headers.length <=
                                                    2
                                                "
                                                @click="
                                                    removeTableColumn(item, ci)
                                                "
                                            >
                                                刪欄
                                            </button>
                                        </div>
                                    </th>
                                    <th
                                        class="w-16 border p-1.5 text-center text-xs font-normal text-muted-foreground"
                                    >
                                        列
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, ri) in item.table_rows"
                                    :key="`r-${i}-${ri}`"
                                >
                                    <td
                                        v-for="(_cell, ci) in item.table_headers"
                                        :key="`c-${i}-${ri}-${ci}`"
                                        class="border p-1"
                                    >
                                        <Input
                                            v-model="row[ci]"
                                            class="h-8 text-xs"
                                            placeholder="內容"
                                        />
                                    </td>
                                    <td class="border p-1 text-center">
                                        <button
                                            type="button"
                                            class="text-[11px] text-muted-foreground hover:text-destructive disabled:opacity-40"
                                            :disabled="
                                                item.table_rows.length <= 1
                                            "
                                            @click="removeTableRow(item, ri)"
                                        >
                                            刪
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addTableRow(item)"
                        >
                            新增一列
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="item.table_headers.length >= 6"
                            @click="addTableColumn(item)"
                        >
                            新增一欄
                        </Button>
                    </div>
                    <InputError
                        :message="
                            tableError(i, 'table_rows') ||
                            tableError(i, 'table_headers')
                        "
                    />
                </div>

                <InputError :message="fieldError('items')" />
            </section>
        </div>

        <aside
            class="space-y-2 xl:sticky xl:top-20 xl:self-start xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto"
        >
            <div class="flex items-baseline justify-between gap-2 px-0.5">
                <h2 class="text-sm font-semibold">預覽</h2>
                <p class="text-xs text-muted-foreground">
                    約略對齊 PDF；學生姓名為示意
                </p>
            </div>
            <div class="rounded-xl border bg-muted/40 p-3 sm:p-4">
                <FeeStandardPreview
                    :title="form.title"
                    :deadline-label="form.deadline_label"
                    :footer-note="form.footer_note"
                    :default-footer="defaultFooter"
                    :items="previewItems"
                />
            </div>
        </aside>
    </div>
</template>
