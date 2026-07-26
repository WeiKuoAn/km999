<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Subject = {
    id: string;
    name: string;
    group: string;
    list: number;
    q_single: number;
    q_double: number;
    material: number;
};

const props = defineProps<{
    student: { code: string; name: string; grade: string };
    subjects: Subject[];
    months: Array<{ y: number; m: number }>;
}>();

const selected = ref<string[]>(['en', 'math']);
const payCycle = ref<'monthly' | 'quarterly' | 'annual'>('quarterly');
const checkedMonths = ref(
    props.months.slice(0, 3).map((x) => `${x.y}-${x.m}`),
);
const allowance = ref(0);
const promotion = ref(true);

const coreCount = computed(
    () => selected.value.filter((id) => props.subjects.find((s) => s.id === id)?.group === 'core').length,
);

const unitPrice = (s: Subject) => {
    if (payCycle.value === 'monthly') return s.list;
    if (s.group === 'core' && coreCount.value >= 2) return s.q_double;
    return s.q_single;
};

const monthCount = computed(() => checkedMonths.value.length);

const tuitionTotal = computed(() =>
    selected.value.reduce((sum, id) => {
        const s = props.subjects.find((x) => x.id === id);
        if (!s) return sum;
        return sum + unitPrice(s) * monthCount.value;
    }, 0),
);

const materialTotal = computed(() =>
    selected.value.reduce((sum, id) => {
        const s = props.subjects.find((x) => x.id === id);
        return sum + (s?.material ?? 0);
    }, 0),
);

const grandTotal = computed(() => Math.max(0, tuitionTotal.value + materialTotal.value - Number(allowance.value || 0)));

const toggleSubject = (id: string) => {
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter((x) => x !== id);
    } else {
        selected.value = [...selected.value, id];
    }
};

const toggleMonth = (key: string) => {
    if (checkedMonths.value.includes(key)) {
        checkedMonths.value = checkedMonths.value.filter((x) => x !== key);
    } else {
        checkedMonths.value = [...checkedMonths.value, key];
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '報名計價', href: '/flow-preview/enrollment' },
        ],
    },
});
</script>

<template>
    <Head title="報名計價" />

    <div class="page-shell mx-auto w-full max-w-5xl">
        <FlowNav
            :prev="{ href: '/flow-preview/trial', label: '試聽' }"
            :next="{ href: '/flow-preview/counter', label: '下一步：櫃台' }"
        />

        <PageHeader
            :title="`報名計價｜${student.code} ${student.name}`"
            :description="`${student.grade}｜勾月份產生帳期；單／雙科依同組科數；教材另列半年收。`"
        />

        <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-4">
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <h2 class="text-base font-semibold">科目</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="s in subjects"
                            :key="s.id"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-left text-sm"
                            :class="
                                selected.includes(s.id)
                                    ? 'border-primary bg-accent'
                                    : 'hover:border-primary/40'
                            "
                            @click="toggleSubject(s.id)"
                        >
                            <div class="font-medium">{{ s.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                定價 {{ s.list }}｜季繳 {{ s.q_single }}／雙 {{ s.q_double }}｜教材 {{ s.material }}
                            </div>
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <h2 class="text-base font-semibold">繳別</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="opt in [
                                { v: 'monthly', t: '月繳（定價）' },
                                { v: 'quarterly', t: '季繳' },
                                { v: 'annual', t: '年繳（升國三）' },
                            ]"
                            :key="opt.v"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm"
                            :class="payCycle === opt.v ? 'border-primary bg-primary text-primary-foreground' : ''"
                            @click="payCycle = opt.v as typeof payCycle"
                        >
                            {{ opt.t }}
                        </button>
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm">
                        <input v-model="promotion" type="checkbox" class="size-4 accent-[var(--brand-green)]" />
                        套用招生季／小六升國一優惠（示意）
                    </label>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <h2 class="text-base font-semibold">勾月份</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="m in months"
                            :key="`${m.y}-${m.m}`"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm"
                            :class="
                                checkedMonths.includes(`${m.y}-${m.m}`)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : ''
                            "
                            @click="toggleMonth(`${m.y}-${m.m}`)"
                        >
                            {{ m.y }}/{{ m.m }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-primary/25 bg-accent/40 p-4">
                    <h2 class="text-base font-semibold text-primary">試算</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">同組核心科數</dt>
                            <dd>{{ coreCount }}（影響雙科價）</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">勾選月數</dt>
                            <dd>{{ monthCount }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">學費小計</dt>
                            <dd>{{ tuitionTotal.toLocaleString() }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">教材（半年）</dt>
                            <dd>{{ materialTotal.toLocaleString() }}</dd>
                        </div>
                        <div class="grid gap-1 border-t pt-2">
                            <Label>折讓金額（主管）</Label>
                            <Input v-model.number="allowance" type="number" min="0" />
                        </div>
                        <div class="flex justify-between gap-2 border-t pt-2 text-base font-semibold">
                            <dt>應收合計</dt>
                            <dd class="text-primary">{{ grandTotal.toLocaleString() }}</dd>
                        </div>
                    </dl>
                    <Button class="mt-4 w-full" type="button">產生帳期（示意）</Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    季繳認列＝季總÷３寫入各月；年繳攤 7～隔年5。實際寫庫尚未接上。
                </p>
            </div>
        </div>
    </div>
</template>
