<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';

type Item = {
    id: number;
    label: string;
    amount: number;
    status: 'paid' | 'unpaid';
    kind: string;
};

const props = defineProps<{
    student: { code: string; name: string };
    items: Item[];
}>();

const checked = ref(props.items.filter((i) => i.status === 'unpaid').map((i) => i.id));
const showReceipt = ref(false);

const selectedTotal = computed(() =>
    props.items.filter((i) => checked.value.includes(i.id)).reduce((s, i) => s + i.amount, 0),
);

const toggle = (id: number) => {
    if (checked.value.includes(id)) {
        checked.value = checked.value.filter((x) => x !== id);
    } else {
        checked.value = [...checked.value, id];
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '櫃台收費', href: '/flow-preview/counter' },
        ],
    },
});
</script>

<template>
    <Head title="櫃台收費" />

    <div class="page-shell mx-auto w-full max-w-4xl">
        <FlowNav
            :prev="{ href: '/flow-preview/enrollment', label: '報名' }"
            :next="{ href: '/flow-preview/fee-plans', label: '收費標準' }"
        />

        <PageHeader
            :title="`櫃台收費｜${student.code} ${student.name}`"
            description="本月已繳／未繳清單；收款後產出收費單（清單＋PDF／圖檔示意）。"
        />

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="px-3 py-2 text-left">收</th>
                        <th class="px-3 py-2 text-left">項目</th>
                        <th class="px-3 py-2 text-right">金額</th>
                        <th class="px-3 py-2 text-left">狀態</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b">
                        <td class="px-3 py-2">
                            <input
                                type="checkbox"
                                class="size-4 accent-[var(--brand-green)]"
                                :checked="checked.includes(item.id)"
                                :disabled="item.status === 'paid'"
                                @change="toggle(item.id)"
                            />
                        </td>
                        <td class="px-3 py-2">{{ item.label }}</td>
                        <td class="px-3 py-2 text-right">{{ item.amount.toLocaleString() }}</td>
                        <td class="px-3 py-2">
                            <span
                                class="rounded px-2 py-0.5 text-xs"
                                :class="
                                    item.status === 'paid'
                                        ? 'bg-primary/15 text-primary'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ item.status === 'paid' ? '已繳' : '未繳' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4">
            <p class="text-sm">
                本次收款合計：
                <span class="text-lg font-semibold text-primary">{{ selectedTotal.toLocaleString() }}</span>
            </p>
            <div class="flex flex-wrap gap-2">
                <Button type="button" @click="showReceipt = true">確認收款並出單</Button>
                <Button type="button" variant="outline">僅預覽清單</Button>
            </div>
        </div>

        <div v-if="showReceipt" class="rounded-xl border border-primary/30 bg-background p-4 shadow-none">
            <div class="flex items-start justify-between gap-2 border-b pb-3">
                <div>
                    <h2 class="text-lg font-semibold text-primary">高名文理補習班｜收費單</h2>
                    <p class="text-sm text-muted-foreground">{{ student.code }} {{ student.name }}</p>
                </div>
                <p class="text-xs text-muted-foreground">示意 PDF／圖檔</p>
            </div>
            <ul class="mt-3 space-y-1 text-sm">
                <li
                    v-for="item in items.filter((i) => checked.includes(i.id))"
                    :key="item.id"
                    class="flex justify-between gap-2"
                >
                    <span>{{ item.label }}</span>
                    <span>{{ item.amount.toLocaleString() }}</span>
                </li>
            </ul>
            <p class="mt-3 border-t pt-3 text-right text-base font-semibold">
                合計 {{ selectedTotal.toLocaleString() }}
            </p>
            <div class="mt-3 flex gap-2">
                <Button type="button" variant="outline" size="sm">下載 PDF（示意）</Button>
                <Button type="button" variant="outline" size="sm">存圖檔（示意）</Button>
            </div>
        </div>
    </div>
</template>
