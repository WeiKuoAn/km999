<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    deadline: string;
    courses: Array<{
        name: string;
        grade: string;
        start: string;
        end: string;
        fee: number;
        visible: boolean;
    }>;
}>();

const batchCount = ref(3);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '短期／特殊班', href: '/flow-preview/short-courses' },
        ],
    },
});
</script>

<template>
    <Head title="短期／特殊班" />

    <div class="page-shell mx-auto w-full max-w-4xl">
        <FlowNav
            :prev="{ href: '/flow-preview/calendar', label: '行事曆' }"
            :next="{ href: '/flow-preview/roster', label: '確認名單' }"
        />

        <PageHeader
            title="短期班／特殊班"
            description="短期班一律一週、以月費額收費。７～９月梯次須在 5/25 前顯示於清單。可單筆或批次產生。"
        />

        <div class="rounded-xl border border-primary/25 bg-accent/40 px-4 py-3 text-sm">
            暑期顯示門檻日：
            <span class="font-semibold text-primary">{{ deadline }}</span>
            （今日若晚於此日，7–9 月班仍應已在清單中）
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="px-3 py-2 text-left">班名</th>
                        <th class="px-3 py-2 text-left">年級</th>
                        <th class="px-3 py-2 text-left">一週起迄</th>
                        <th class="px-3 py-2 text-right">月費額</th>
                        <th class="px-3 py-2 text-left">已顯示</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in courses" :key="c.name" class="border-b">
                        <td class="px-3 py-2 font-medium">{{ c.name }}</td>
                        <td class="px-3 py-2">{{ c.grade }}</td>
                        <td class="px-3 py-2">{{ c.start }} ~ {{ c.end }}</td>
                        <td class="px-3 py-2 text-right">{{ c.fee.toLocaleString() }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded bg-primary/15 px-2 py-0.5 text-xs text-primary">
                                {{ c.visible ? '是' : '否' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <form class="space-y-3 rounded-xl border border-sidebar-border/70 p-4" @submit.prevent>
                <h2 class="text-base font-semibold">單筆開班</h2>
                <div class="grid gap-1">
                    <Label>班名</Label>
                    <Input placeholder="例如：國一英文加強週" />
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <Label>起日</Label>
                        <Input type="date" />
                    </div>
                    <div class="grid gap-1">
                        <Label>迄日（＋6）</Label>
                        <Input type="date" />
                    </div>
                </div>
                <div class="grid gap-1">
                    <Label>收費（月費額）</Label>
                    <Input type="number" placeholder="3600" />
                </div>
                <Button type="button">建立</Button>
            </form>

            <form class="space-y-3 rounded-xl border border-sidebar-border/70 p-4" @submit.prevent>
                <h2 class="text-base font-semibold">批次產生</h2>
                <div class="grid gap-1">
                    <Label>連續週數</Label>
                    <Input v-model.number="batchCount" type="number" min="1" max="12" />
                </div>
                <div class="grid gap-1">
                    <Label>首週起日</Label>
                    <Input type="date" value="2026-07-06" />
                </div>
                <p class="text-xs text-muted-foreground">將產生 {{ batchCount }} 個一週班（示意）。</p>
                <Button type="button" variant="outline">批次產生</Button>
            </form>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 text-sm text-muted-foreground">
            特殊班（如理化科研）另走「４堂一塊／１２堂季」價目，不在此短期一週流程內，但可在收費標準與報名計價選取。
        </div>
    </div>
</template>
