<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const props = defineProps<{
    month: string;
    recognized: number;
    collected: number;
    unpaid: number;
    monthly: Array<{ month: string; amount: number }>;
    pie: Array<{ label: string; value: number; sessions: number }>;
    note: string;
}>();

const pieTotal = computed(() => props.pie.reduce((s, p) => s + p.value, 0) || 1);

const slices = computed(() => {
    let offset = 0;
    const colors = ['var(--brand-green)', 'hsl(172 35% 55%)', 'hsl(172 20% 75%)'];
    return props.pie.map((p, i) => {
        const pct = (p.value / pieTotal.value) * 100;
        const start = offset;
        offset += pct;
        return { ...p, pct, start, color: colors[i % colors.length] };
    });
});

const conic = computed(() => {
    const parts = slices.value.map((s) => `${s.color} ${s.start}% ${s.start + s.pct}%`);
    return `conic-gradient(${parts.join(', ')})`;
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '營收報表', href: '/flow-preview/revenue' },
        ],
    },
});
</script>

<template>
    <Head title="營收報表" />

    <div class="page-shell">
        <FlowNav :prev="{ href: '/flow-preview/roster', label: '確認名單' }" />

        <PageHeader
            :title="`營收報表｜${month}`"
            :description="note"
        />

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">本月認列營收</p>
                <p class="mt-1 text-2xl font-semibold text-primary">{{ recognized.toLocaleString() }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">本月已收款</p>
                <p class="mt-1 text-2xl font-semibold">{{ collected.toLocaleString() }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">本月未繳</p>
                <p class="mt-1 text-2xl font-semibold">{{ unpaid.toLocaleString() }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <h2 class="text-base font-semibold">每月認列營收</h2>
                <table class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 text-left">月份</th>
                            <th class="py-2 text-right">金額</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in monthly" :key="m.month" class="border-b">
                            <td class="py-2">{{ m.month }}</td>
                            <td class="py-2 text-right">{{ m.amount.toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <h2 class="text-base font-semibold">年級營收／科次（圓餅）</h2>
                <div class="mt-4 flex flex-wrap items-center gap-6">
                    <div
                        class="size-40 shrink-0 rounded-full"
                        :style="{ background: conic }"
                        role="img"
                        aria-label="年級營收圓餅圖"
                    />
                    <ul class="space-y-2 text-sm">
                        <li v-for="s in slices" :key="s.label" class="flex items-center gap-2">
                            <span class="inline-block size-3 rounded-sm" :style="{ background: s.color }" />
                            <span>{{ s.label }}</span>
                            <span class="text-muted-foreground">
                                {{ s.value.toLocaleString() }}（{{ s.pct.toFixed(0) }}%）｜課次 {{ s.sessions }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
