<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const props = defineProps<{
    plans: Array<{
        grade: string;
        group: string;
        list: string;
        quarter: string;
        material: string;
        unit: string;
    }>;
}>();

const grade = ref('全部');
const grades = ['全部', '國一', '國二', '國三'];

const filtered = computed(() =>
    grade.value === '全部' ? props.plans : props.plans.filter((p) => p.grade === grade.value),
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '收費標準', href: '/flow-preview/fee-plans' },
        ],
    },
});
</script>

<template>
    <Head title="收費標準" />

    <div class="page-shell">
        <FlowNav
            :prev="{ href: '/flow-preview/counter', label: '櫃台' }"
            :next="{ href: '/flow-preview/sessions', label: '堂數／加課' }"
        />

        <PageHeader
            title="收費標準"
            description="依年級 × 科目組維護定價、季繳、教材費。課程需掛上對應「收費組別」才能在報名時引用。真實資料請至設定管理維護。"
        />

        <div class="mb-2">
            <a href="/fee-plans" class="text-sm text-primary underline-offset-4 hover:underline">前往收費標準設定 →</a>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="g in grades"
                :key="g"
                type="button"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="grade === g ? 'border-primary bg-primary text-primary-foreground' : ''"
                @click="grade = g"
            >
                {{ g }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="px-3 py-2 text-left">年級</th>
                        <th class="px-3 py-2 text-left">科目組</th>
                        <th class="px-3 py-2 text-left">定價</th>
                        <th class="px-3 py-2 text-left">季繳優惠</th>
                        <th class="px-3 py-2 text-left">教材費</th>
                        <th class="px-3 py-2 text-left">單位</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in filtered" :key="i" class="border-b">
                        <td class="px-3 py-2">{{ p.grade }}</td>
                        <td class="px-3 py-2 font-medium">{{ p.group }}</td>
                        <td class="px-3 py-2">{{ p.list }}</td>
                        <td class="px-3 py-2">{{ p.quarter }}</td>
                        <td class="px-3 py-2">{{ p.material }}</td>
                        <td class="px-3 py-2">{{ p.unit }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
