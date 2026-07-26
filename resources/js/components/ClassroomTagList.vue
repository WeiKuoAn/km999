<script setup lang="ts">
import { computed, ref } from 'vue';
import { classroomSwatchStyle } from '@/lib/classroomColor';

const props = withDefaults(
    defineProps<{
        items: Array<{ id: number; name: string; color: string | null }>;
        maxVisible?: number;
        compact?: boolean;
    }>(),
    {
        maxVisible: 4,
        compact: false,
    },
);

const expanded = ref(false);

const visibleItems = computed(() =>
    expanded.value ? props.items : props.items.slice(0, props.maxVisible),
);

const hiddenCount = computed(() =>
    expanded.value ? 0 : Math.max(0, props.items.length - props.maxVisible),
);

const toggleExpanded = () => {
    expanded.value = !expanded.value;
};
</script>

<template>
    <span v-if="items.length === 0" class="text-muted-foreground">—</span>
    <div v-else class="flex flex-wrap items-center gap-1" :class="compact ? 'max-w-none' : 'max-w-[15rem]'">
        <span
            v-for="item in visibleItems"
            :key="item.id"
            class="inline-flex max-w-full items-center gap-1 rounded-md border border-border/50 bg-muted/40 px-1.5 py-0.5 text-xs leading-tight text-foreground"
            :title="item.name"
        >
            <span
                class="h-1.5 w-1.5 shrink-0 rounded-full border border-border/40"
                :style="classroomSwatchStyle(item.color)"
            />
            <span class="truncate">{{ item.name }}</span>
        </span>
        <button
            v-if="hiddenCount > 0"
            type="button"
            class="inline-flex shrink-0 items-center rounded-md border border-dashed border-primary/40 bg-primary/5 px-1.5 py-0.5 text-xs font-medium text-primary hover:bg-primary/10"
            @click="toggleExpanded"
        >
            +{{ hiddenCount }}
        </button>
        <button
            v-else-if="expanded && items.length > maxVisible"
            type="button"
            class="inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs text-muted-foreground hover:text-foreground"
            @click="toggleExpanded"
        >
            收合
        </button>
    </div>
</template>
