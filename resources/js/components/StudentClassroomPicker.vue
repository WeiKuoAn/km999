<script setup lang="ts">
import { computed } from 'vue';
import { classroomSwatchStyle } from '@/lib/classroomColor';

export type ClassroomOption = {
    id: number;
    name: string;
    color: string | null;
    course_label: string;
    course_prices?: Array<{ level: string | null; tuition: number }>;
};

const props = defineProps<{
    options: ClassroomOption[];
}>();

const selectedIds = defineModel<number[]>({ required: true });

const sortedOptions = computed(() =>
    [...props.options].sort((a, b) => a.name.localeCompare(b.name, 'zh-Hant-TW')),
);

const isChecked = (id: number) => selectedIds.value.includes(id);

const selectedOptions = computed(() => sortedOptions.value.filter((c) => isChecked(c.id)));
const unselectedOptions = computed(() => sortedOptions.value.filter((c) => !isChecked(c.id)));

const toggle = (id: number) => {
    if (isChecked(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};
</script>

<template>
    <div v-if="sortedOptions.length === 0" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
        目前沒有可選的班級。請先在班級管理建立班級。
    </div>
    <div v-else class="max-h-[32rem] space-y-4 overflow-y-auto rounded-lg border p-3">
        <div class="space-y-2">
            <p class="text-xs font-semibold text-muted-foreground">已選（{{ selectedOptions.length }}）</p>
            <p v-if="selectedOptions.length === 0" class="px-2 py-1 text-xs text-muted-foreground">尚未選擇任何班級。</p>
            <label
                v-for="c in selectedOptions"
                :key="c.id"
                class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 hover:bg-muted/50"
            >
                <input
                    type="checkbox"
                    class="mt-1 size-4 shrink-0 rounded border"
                    :checked="true"
                    @change="toggle(c.id)"
                />
                <span class="min-w-0 flex-1">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60"
                            :style="classroomSwatchStyle(c.color)"
                        />
                        {{ c.name }}
                    </span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">{{ c.course_label }}</span>
                </span>
            </label>
        </div>

        <div class="space-y-2 border-t pt-3">
            <p class="text-xs font-semibold text-muted-foreground">未選（{{ unselectedOptions.length }}）</p>
            <p v-if="unselectedOptions.length === 0" class="px-2 py-1 text-xs text-muted-foreground">已全部選取。</p>
            <label
                v-for="c in unselectedOptions"
                :key="c.id"
                class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 hover:bg-muted/50"
            >
                <input
                    type="checkbox"
                    class="mt-1 size-4 shrink-0 rounded border"
                    :checked="false"
                    @change="toggle(c.id)"
                />
                <span class="min-w-0 flex-1">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60"
                            :style="classroomSwatchStyle(c.color)"
                        />
                        {{ c.name }}
                    </span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">{{ c.course_label }}</span>
                </span>
            </label>
        </div>
    </div>
</template>
