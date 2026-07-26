<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    className: string;
    month: string;
    rows: Array<{
        subject: string;
        planned: number;
        holiday: number;
        makeup: number;
        final: number;
    }>;
    totalSessions: number;
    totalSubjectSessions: number;
}>();

const makeupDay = ref<2 | 3 | 4 | null>(3);

const localRows = ref(props.rows.map((r) => ({ ...r })));

const totals = computed(() => ({
    sessions: localRows.value.reduce((s, r) => s + r.final, 0),
    subjects: localRows.value.length,
}));

const adjust = (idx: number, delta: number) => {
    const row = localRows.value[idx];
    row.makeup = Math.max(0, row.makeup + delta);
    row.final = Math.max(0, row.planned - row.holiday + row.makeup);
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '堂數／加課', href: '/flow-preview/sessions' },
        ],
    },
});
</script>

<template>
    <Head title="堂數／加課" />

    <div class="page-shell">
        <FlowNav
            :prev="{ href: '/flow-preview/fee-plans', label: '收費標準' }"
            :next="{ href: '/flow-preview/calendar', label: '行事曆' }"
        />

        <PageHeader
            :title="`堂數／加課｜${className}`"
            :description="`${month}｜課表 − 連假 ± 加課 → 科別×月份×堂數`"
        />

        <div class="rounded-xl border border-sidebar-border/70 p-4">
            <Label class="text-base font-semibold">二／三／四擇一加課日</Label>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="d in [
                        { v: 2, t: '星期二' },
                        { v: 3, t: '星期三' },
                        { v: 4, t: '星期四' },
                    ]"
                    :key="d.v"
                    type="button"
                    class="rounded-md border px-3 py-1.5 text-sm"
                    :class="makeupDay === d.v ? 'border-primary bg-primary text-primary-foreground' : ''"
                    @click="makeupDay = makeupDay === d.v ? null : (d.v as 2 | 3 | 4)"
                >
                    {{ d.t }}
                </button>
                <Button type="button" variant="outline" size="sm" @click="makeupDay = null">不加課</Button>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">可加可減：下方各科「加課」欄用 +/- 調整。</p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="px-3 py-2 text-left">科目</th>
                        <th class="px-3 py-2 text-right">課表堂數</th>
                        <th class="px-3 py-2 text-right">連假扣除</th>
                        <th class="px-3 py-2 text-right">加課</th>
                        <th class="px-3 py-2 text-right">最終堂數</th>
                        <th class="px-3 py-2 text-center">調整</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in localRows" :key="row.subject" class="border-b">
                        <td class="px-3 py-2 font-medium">{{ row.subject }}</td>
                        <td class="px-3 py-2 text-right">{{ row.planned }}</td>
                        <td class="px-3 py-2 text-right">{{ row.holiday }}</td>
                        <td class="px-3 py-2 text-right">{{ row.makeup }}</td>
                        <td class="px-3 py-2 text-right font-semibold text-primary">{{ row.final }}</td>
                        <td class="px-3 py-2">
                            <div class="flex justify-center gap-1">
                                <Button type="button" size="sm" variant="outline" @click="adjust(i, 1)">+</Button>
                                <Button type="button" size="sm" variant="outline" @click="adjust(i, -1)">−</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-4 rounded-xl border border-primary/20 bg-accent/40 px-4 py-3 text-sm">
            <p>總課次：<span class="font-semibold text-primary">{{ totals.sessions }}</span></p>
            <p>總科次：<span class="font-semibold text-primary">{{ totals.subjects }}</span></p>
        </div>
    </div>
</template>
