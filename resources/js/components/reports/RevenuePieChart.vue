<script setup lang="ts">
import { computed } from 'vue';

type Slice = { label: string; value: number };

const props = defineProps<{
    title: string;
    items: Slice[];
}>();

const COLORS = [
    'hsl(152 45% 36%)',
    'hsl(172 40% 42%)',
    'hsl(198 45% 45%)',
    'hsl(38 70% 48%)',
    'hsl(18 65% 48%)',
    'hsl(280 30% 48%)',
    'hsl(210 25% 55%)',
    'hsl(90 30% 42%)',
];

const total = computed(() => props.items.reduce((sum, item) => sum + item.value, 0));

const slices = computed(() => {
    const pieTotal = total.value || 1;
    let offset = 0;

    return props.items.map((item, index) => {
        const pct = (item.value / pieTotal) * 100;
        const start = offset;
        offset += pct;

        return {
            ...item,
            pct,
            start,
            color: COLORS[index % COLORS.length],
        };
    });
});

const conic = computed(() => {
    if (slices.value.length === 0 || total.value === 0) {
        return 'conic-gradient(hsl(0 0% 90%) 0% 100%)';
    }

    const parts = slices.value.map(
        (slice) => `${slice.color} ${slice.start}% ${slice.start + slice.pct}%`,
    );

    return `conic-gradient(${parts.join(', ')})`;
});

const formatMoney = (n: number) => n.toLocaleString('zh-TW');
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 bg-card p-4">
        <h2 class="text-base font-semibold">{{ title }}</h2>
        <div
            v-if="items.length === 0 || total === 0"
            class="mt-6 py-8 text-center text-sm text-muted-foreground"
        >
            尚無資料
        </div>
        <div v-else class="mt-4 flex flex-wrap items-center gap-6">
            <div
                class="size-40 shrink-0 rounded-full"
                :style="{ background: conic }"
                role="img"
                :aria-label="title"
            />
            <ul class="min-w-0 flex-1 space-y-2 text-sm">
                <li
                    v-for="slice in slices"
                    :key="slice.label"
                    class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5"
                >
                    <span
                        class="mt-1 inline-block size-3 shrink-0 rounded-sm"
                        :style="{ background: slice.color }"
                    />
                    <span class="font-medium">{{ slice.label }}</span>
                    <span class="tabular-nums text-muted-foreground">
                        {{ formatMoney(slice.value) }}（{{
                            slice.pct.toFixed(0)
                        }}%）
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
