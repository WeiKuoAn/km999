<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    steps: Array<{ key: string; title: string; href: string; desc: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: '營運流程預覽', href: '/flow-preview' }],
    },
});
</script>

<template>
    <Head title="營運流程預覽" />

    <div class="page-shell">
        <PageHeader
            title="營運流程預覽"
            description="依規劃操作順序排頁，方便核對流暢。畫面使用專案品牌色，資料為示意。"
        />

        <div class="rounded-xl border border-primary/20 bg-accent/50 p-4 text-sm">
            <p class="font-medium text-primary">建議走法</p>
            <p class="mt-1 text-muted-foreground">
                學生建檔 → 試聽 → 報名計價 → 櫃台收費；並行維護收費標準、堂數、行事曆、短期班；再做確認名單與營收報表。
            </p>
        </div>

        <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="(step, i) in steps"
                :key="step.key"
                class="rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:border-primary/40"
            >
                <div class="flex items-start justify-between gap-2">
                    <span
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-primary text-xs font-semibold text-primary-foreground"
                    >
                        {{ i + 1 }}
                    </span>
                    <Button size="sm" variant="outline" as-child>
                        <Link :href="step.href">開啟</Link>
                    </Button>
                </div>
                <h2 class="mt-3 text-base font-semibold">{{ step.title }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ step.desc }}</p>
            </li>
        </ol>
    </div>
</template>
