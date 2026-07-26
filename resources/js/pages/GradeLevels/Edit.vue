<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    grade: {
        id: number;
        name: string;
        code: number;
        sort_order: number;
        is_active: boolean;
    };
}>();

const form = useForm({
    name: props.grade.name,
    code: props.grade.code,
    sort_order: props.grade.sort_order,
    is_active: props.grade.is_active,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '年級編號', href: '/grade-levels' },
            { title: '編輯', href: '#' },
        ],
    },
});

const submit = () => form.put(`/grade-levels/${props.grade.id}`);
</script>

<template>
    <Head title="編輯年級" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">編輯年級</h1>
        <form class="space-y-4 rounded-xl border p-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">年級名稱</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="code">編號</Label>
                <Input id="code" v-model.number="form.code" type="number" min="1" max="99" required />
                <InputError :message="form.errors.code" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="size-4 accent-[var(--brand-green)]" />
                啟用
            </label>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child><Link href="/grade-levels">返回</Link></Button>
            </div>
        </form>
    </div>
</template>
