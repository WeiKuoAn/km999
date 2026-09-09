<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    discount: {
        id: number;
        name: string;
        type: 'amount' | 'percent';
        value: number;
        is_active: boolean;
        sort_order: number;
        starts_on: string | null;
        ends_on: string | null;
        notes: string | null;
    };
}>();

const form = useForm({
    name: props.discount.name,
    type: props.discount.type,
    value: props.discount.value,
    is_active: props.discount.is_active,
    sort_order: props.discount.sort_order,
    starts_on: props.discount.starts_on ?? '',
    ends_on: props.discount.ends_on ?? '',
    notes: props.discount.notes ?? '',
});

const valueHint = computed(() =>
    form.type === 'percent'
        ? '輸入 1–100，例如 10 代表打 9 折（減 10%）'
        : '輸入固定折讓金額（元）',
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '優惠管理', href: '/fee-discounts' },
            { title: '編輯', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="編輯優惠" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">編輯優惠</h1>
        <form
            class="mt-4 space-y-4 rounded-xl border p-4"
            @submit.prevent="form.put(`/fee-discounts/${props.discount.id}`)"
        >
            <div class="grid gap-2">
                <Label for="name">名稱</Label>
                <Input id="name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="type">類型</Label>
                    <select
                        id="type"
                        v-model="form.type"
                        class="h-9 rounded-md border px-3"
                    >
                        <option value="amount">固定折讓（元）</option>
                        <option value="percent">打折％數</option>
                    </select>
                    <InputError :message="form.errors.type" />
                </div>
                <div class="grid gap-2">
                    <Label for="value">{{
                        form.type === 'percent' ? '折扣％' : '折讓金額'
                    }}</Label>
                    <Input
                        id="value"
                        v-model.number="form.value"
                        type="number"
                        min="1"
                        :max="form.type === 'percent' ? 100 : undefined"
                    />
                    <p class="text-xs text-muted-foreground">{{ valueHint }}</p>
                    <InputError :message="form.errors.value" />
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="starts_on">開始日（選填）</Label>
                    <Input
                        id="starts_on"
                        v-model="form.starts_on"
                        type="date"
                    />
                    <InputError :message="form.errors.starts_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="ends_on">結束日（選填）</Label>
                    <Input id="ends_on" v-model="form.ends_on" type="date" />
                    <InputError :message="form.errors.ends_on" />
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="sort_order">排序</Label>
                    <Input
                        id="sort_order"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        max="255"
                    />
                    <InputError :message="form.errors.sort_order" />
                </div>
                <div class="flex items-end gap-2 pb-1">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" />
                        啟用
                    </label>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">備註（選填）</Label>
                <Input id="notes" v-model="form.notes" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">更新</Button>
                <Button variant="outline" as-child>
                    <Link href="/fee-discounts">返回</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
