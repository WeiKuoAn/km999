<script setup lang="ts">
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        items: string[];
        maxVisible?: number;
    }>(),
    {
        maxVisible: 3,
    },
);

const expanded = ref(false);

const visibleItems = computed(() =>
    expanded.value ? props.items : props.items.slice(0, props.maxVisible),
);

const hiddenCount = computed(() =>
    expanded.value ? 0 : Math.max(0, props.items.length - props.maxVisible),
);
</script>

<template>
    <span v-if="items.length === 0" class="text-muted-foreground">—</span>
    <div v-else class="flex max-w-[13rem] flex-wrap items-center gap-1">
        <span
            v-for="(name, index) in visibleItems"
            :key="`${name}-${index}`"
            class="inline-flex max-w-full items-center rounded-md bg-muted/50 px-1.5 py-0.5 text-xs leading-tight text-foreground"
            :title="name"
        >
            <span class="truncate">{{ name }}</span>
        </span>
        <button
            v-if="hiddenCount > 0"
            type="button"
            class="inline-flex shrink-0 items-center rounded-md border border-dashed border-muted-foreground/30 px-1.5 py-0.5 text-xs text-muted-foreground hover:text-foreground"
            @click="expanded = true"
        >
            +{{ hiddenCount }}
        </button>
        <button
            v-else-if="expanded && items.length > maxVisible"
            type="button"
            class="inline-flex shrink-0 items-center px-1.5 py-0.5 text-xs text-muted-foreground hover:text-foreground"
            @click="expanded = false"
        >
            收合
        </button>
    </div>
</template>
