<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Ev = { type: string; title: string; start: string; end: string };

const props = defineProps<{ events: Ev[] }>();

const typeLabels: Record<string, string> = {
    national_holiday: '國定連假',
    long_break: '自訂連假',
    exam_review: '段考複習',
    enrollment_season: '招生季',
    short_course_deadline: '短期班門檻',
    roster_confirm: '確認名單',
};

const filter = ref('全部');
const filters = ['全部', ...Object.values(typeLabels)];

const list = computed(() => {
    if (filter.value === '全部') return props.events;
    const key = Object.entries(typeLabels).find(([, v]) => v === filter.value)?.[0];
    return props.events.filter((e) => e.type === key);
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '行事曆連假', href: '/flow-preview/calendar' },
        ],
    },
});
</script>

<template>
    <Head title="行事曆連假" />

    <div class="page-shell mx-auto w-full max-w-4xl">
        <FlowNav
            :prev="{ href: '/flow-preview/sessions', label: '堂數' }"
            :next="{ href: '/flow-preview/short-courses', label: '短期班' }"
        />

        <PageHeader
            title="行事曆／連假"
            description="國定連假、小暑休、段考複習、招生季、5/25 短期班門檻、7／8 確認名單。影響堂數計算與開班顯示。"
        />

        <div class="flex flex-wrap gap-2">
            <button
                v-for="f in filters"
                :key="f"
                type="button"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="filter === f ? 'border-primary bg-primary text-primary-foreground' : ''"
                @click="filter = f"
            >
                {{ f }}
            </button>
        </div>

        <div class="space-y-2">
            <div
                v-for="(e, i) in list"
                :key="i"
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-sidebar-border/70 px-4 py-3"
            >
                <div>
                    <p class="text-xs font-medium text-primary">{{ typeLabels[e.type] ?? e.type }}</p>
                    <p class="font-medium">{{ e.title }}</p>
                    <p class="text-sm text-muted-foreground">{{ e.start }} → {{ e.end }}</p>
                </div>
            </div>
        </div>

        <form class="grid gap-3 rounded-xl border border-dashed border-primary/30 p-4 sm:grid-cols-2" @submit.prevent>
            <h2 class="text-base font-semibold sm:col-span-2">新增事件（示意）</h2>
            <div class="grid gap-1 sm:col-span-2">
                <Label>標題</Label>
                <Input placeholder="例如：春假連假" />
            </div>
            <div class="grid gap-1">
                <Label>起日</Label>
                <Input type="date" />
            </div>
            <div class="grid gap-1">
                <Label>迄日</Label>
                <Input type="date" />
            </div>
            <div class="sm:col-span-2">
                <Button type="button">新增</Button>
            </div>
        </form>
    </div>
</template>
