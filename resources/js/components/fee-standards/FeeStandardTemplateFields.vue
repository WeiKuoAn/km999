<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- form 是父頁 Inertia useForm */
import type { InertiaForm } from '@inertiajs/vue3';
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
    type FeeStandardFormData,
    type FeeStandardFormItem,
} from '@/lib/feeStandardForm';

defineProps<{
    form: InertiaForm<FeeStandardFormData>;
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    defaultFooter: string;
}>();

const addCheckItem = (form: InertiaForm<FeeStandardFormData>) => {
    form.items.push(emptyCheckItem(form.items.length));
};

const addTableItem = (form: InertiaForm<FeeStandardFormData>) => {
    form.items.push(emptyTableItem(form.items.length));
};

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
                        依學年／年級建立一張家長勾選用收費標準單；也可插入黃表頭收費表格。
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

            <section class="space-y-4 rounded-xl border p-4 sm:p-5">
                <div
                    class="flex flex-wrap items-start justify-between gap-2 border-b pb-3"
                >
                    <div>
                        <h2 class="text-sm font-semibold">內容區塊</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            勾選項目會印 □；表格會印黃表頭三欄（可增刪欄列），可混用。
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addCheckItem(form)"
                        >
                            <Plus class="size-4" />
                            新增勾選
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addTableItem(form)"
                        >
                            <Table2 class="size-4" />
                            新增表格
                        </Button>
                    </div>
                </div>

                <div
                    v-for="(item, i) in form.items"
                    :key="i"
                    class="space-y-2 rounded-md border bg-muted/30 p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            {{
                                item.kind === 'table'
                                    ? `表格 ${i + 1}`
                                    : `勾選 ${i + 1}`
                            }}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            :disabled="form.items.length <= 1"
                            @click="form.items.splice(i, 1)"
                        >
                            <Trash2 class="size-4" />
                            刪除
                        </Button>
                    </div>

                    <template v-if="item.kind === 'table'">
                        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                            <div class="grid gap-1.5">
                                <Label :for="`item-label-${i}`"
                                    >區塊標題</Label
                                >
                                <Input
                                    :id="`item-label-${i}`"
                                    v-model="item.label"
                                    placeholder="例：英文+數學雙科"
                                    required
                                />
                                <InputError
                                    :message="
                                        (
                                            form.errors as Record<
                                                string,
                                                string | undefined
                                            >
                                        )[`items.${i}.label`]
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`item-color-${i}`">標題顏色</Label>
                                <select
                                    :id="`item-color-${i}`"
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
                                                    v-model="
                                                        item.table_headers[ci]
                                                    "
                                                    class="h-8 bg-white/80 text-center text-xs"
                                                    :placeholder="`欄 ${ci + 1}`"
                                                />
                                                <button
                                                    type="button"
                                                    class="text-[11px] text-muted-foreground hover:text-destructive disabled:opacity-40"
                                                    :disabled="
                                                        item.table_headers
                                                            .length <= 2
                                                    "
                                                    @click="
                                                        removeTableColumn(
                                                            item,
                                                            ci,
                                                        )
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
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                )[`items.${i}.table_rows`] ||
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                )[`items.${i}.table_headers`]
                            "
                        />
                    </template>

                    <template v-else>
                        <div class="grid gap-1.5">
                            <Label :for="`item-label-${i}`">主列</Label>
                            <Input
                                :id="`item-label-${i}`"
                                v-model="item.label"
                                placeholder="例：理化科研班首期 7-10 月優惠"
                                required
                            />
                            <InputError
                                :message="
                                    (
                                        form.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    )[`items.${i}.label`]
                                "
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`item-detail-${i}`">副列（選填）</Label>
                            <textarea
                                :id="`item-detail-${i}`"
                                v-model="item.detail"
                                rows="2"
                                class="min-h-[3.5rem] w-full rounded-md border px-3 py-2 text-sm"
                                placeholder="例：訂金 500 元(已繳) + 學費 12000 元 (含教材) = 11500 元"
                            />
                        </div>
                    </template>
                </div>
                <InputError
                    :message="
                        (form.errors as Record<string, string | undefined>)
                            .items
                    "
                />
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
                    :items="form.items"
                />
            </div>
        </aside>
    </div>
</template>
