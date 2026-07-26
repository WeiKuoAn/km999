<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    students: Array<{ id: number; code: string; name: string }>;
    subjects: string[];
}>();

const studentId = ref(String(props.students[0]?.id ?? ''));
const startDate = ref('2026-07-20');
const selectedSubjects = ref<string[]>(['英文']);
const deposit = ref('1000');
const depositExpire = ref('2026-08-03');

const endDate = computed(() => {
    const d = new Date(startDate.value);
    if (Number.isNaN(d.getTime())) return '—';
    d.setDate(d.getDate() + 6);
    return d.toISOString().slice(0, 10);
});

const toggleSubject = (name: string) => {
    if (selectedSubjects.value.includes(name)) {
        selectedSubjects.value = selectedSubjects.value.filter((s) => s !== name);
    } else {
        selectedSubjects.value = [...selectedSubjects.value, name];
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '試聽一週', href: '/flow-preview/trial' },
        ],
    },
});
</script>

<template>
    <Head title="試聽一週" />

    <div class="page-shell mx-auto w-full max-w-3xl">
        <FlowNav
            :prev="{ href: '/flow-preview/students', label: '學生建檔' }"
            :next="{ href: '/flow-preview/enrollment', label: '下一步：報名' }"
        />

        <PageHeader
            title="試聽一週"
            description="試聽固定７天（起日＋６）。可不產生正式應收；可先收下訂金並設期限。"
        />

        <form class="space-y-4 rounded-xl border border-sidebar-border/70 p-4" @submit.prevent>
            <div class="grid gap-2">
                <Label>學生</Label>
                <select v-model="studentId" class="h-9 rounded-md border bg-background px-3 text-sm">
                    <option v-for="s in students" :key="s.id" :value="String(s.id)">
                        {{ s.code }} {{ s.name }}
                    </option>
                </select>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label>試聽起日</Label>
                    <Input v-model="startDate" type="date" />
                </div>
                <div class="grid gap-1">
                    <Label>試聽迄日（自動）</Label>
                    <Input :value="endDate" readonly class="bg-muted/40" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label>試聽科目</Label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="name in subjects"
                        :key="name"
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm"
                        :class="
                            selectedSubjects.includes(name)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-background hover:border-primary/40'
                        "
                        @click="toggleSubject(name)"
                    >
                        {{ name }}
                    </button>
                </div>
            </div>

            <div class="grid gap-3 rounded-lg border border-dashed border-primary/30 bg-accent/30 p-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label>訂金金額</Label>
                    <Input v-model="deposit" type="number" min="0" />
                </div>
                <div class="grid gap-1">
                    <Label>訂金有效期限</Label>
                    <Input v-model="depositExpire" type="date" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button type="button">儲存試聽</Button>
                <Button type="button" variant="outline">轉正式報名</Button>
            </div>
        </form>
    </div>
</template>
