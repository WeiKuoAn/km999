<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';

type Row = {
    code: string;
    name: string;
    grade: string;
    subjects: string;
    confirmed: boolean;
};

const props = defineProps<{
    period: string;
    rows: Row[];
}>();

const local = ref(props.rows.map((r) => ({ ...r })));

const confirmedCount = computed(() => local.value.filter((r) => r.confirmed).length);

const toggle = (code: string) => {
    const row = local.value.find((r) => r.code === code);
    if (row) row.confirmed = !row.confirmed;
};

const confirmAll = () => {
    local.value.forEach((r) => {
        r.confirmed = true;
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '確認名單', href: '/flow-preview/roster' },
        ],
    },
});
</script>

<template>
    <Head title="確認名單" />

    <div class="page-shell">
        <FlowNav
            :prev="{ href: '/flow-preview/short-courses', label: '短期班' }"
            :next="{ href: '/flow-preview/revenue', label: '營收報表' }"
        />

        <PageHeader
            :title="`確認名單｜${period}`"
            description="7／8 月由主任／主管老師鎖定。未確認者不進入下期正式帳（示意規則）。"
        />

        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-sidebar-border/70 px-4 py-3">
            <p class="text-sm">
                已確認
                <span class="font-semibold text-primary">{{ confirmedCount }}</span>
                ／ {{ local.length }}
            </p>
            <div class="flex gap-2">
                <Button type="button" variant="outline" size="sm" @click="confirmAll">全部確認</Button>
                <Button type="button" size="sm">主管鎖定本月名單</Button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="px-3 py-2 text-left">確認</th>
                        <th class="px-3 py-2 text-left">學號</th>
                        <th class="px-3 py-2 text-left">姓名</th>
                        <th class="px-3 py-2 text-left">年級</th>
                        <th class="px-3 py-2 text-left">科目</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in local" :key="r.code" class="border-b">
                        <td class="px-3 py-2">
                            <input
                                type="checkbox"
                                class="size-4 accent-[var(--brand-green)]"
                                :checked="r.confirmed"
                                @change="toggle(r.code)"
                            />
                        </td>
                        <td class="px-3 py-2 font-mono">{{ r.code }}</td>
                        <td class="px-3 py-2">{{ r.name }}</td>
                        <td class="px-3 py-2">{{ r.grade }}</td>
                        <td class="px-3 py-2">{{ r.subjects }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
