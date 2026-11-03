<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- form 是父頁 Inertia useForm */
import type { InertiaForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import FeeStandardPreview from '@/components/fee-standards/FeeStandardPreview.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type FeeStandardFormData = {
    academic_year_id: string;
    grade_level_id: string;
    title: string;
    deadline_label: string;
    footer_note: string;
    is_active: boolean;
    sort_order: number;
    items: Array<{
        label: string;
        detail: string;
        sort_order: number;
    }>;
};

defineProps<{
    form: InertiaForm<FeeStandardFormData>;
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    defaultFooter: string;
}>();

const addItem = (form: InertiaForm<FeeStandardFormData>) => {
    form.items.push({
        label: '',
        detail: '',
        sort_order: form.items.length,
    });
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
                        依學年／年級建立一張家長勾選用收費標準單。
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
                        <h2 class="text-sm font-semibold">勾選項目</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            主列為項目名稱；副列可寫金額公式（純文字）。PDF
                            會印空白 □ 給家長勾選。
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addItem(form)"
                    >
                        <Plus class="size-4" />
                        新增項目
                    </Button>
                </div>

                <div
                    v-for="(item, i) in form.items"
                    :key="i"
                    class="space-y-2 rounded-md border bg-muted/30 p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            項目 {{ i + 1 }}
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
                </div>
                <InputError
                    :message="
                        (form.errors as Record<string, string | undefined>)
                            .items
                    "
                />
            </section>
        </div>

        <!-- 右：即時預覽（寬螢幕黏在上方） -->
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
