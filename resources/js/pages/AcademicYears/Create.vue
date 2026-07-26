<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({
    year_code: '',
    name: '',
    is_current: false,
    sort_order: 0,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學年設定', href: '/academic-years' },
            { title: '新增', href: '/academic-years/create' },
        ],
    },
});

const submit = () => form.post('/academic-years');
</script>

<template>
    <Head title="新增學年" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增學年</h1>
        <form class="space-y-4 rounded-xl border p-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="year_code">年度碼（民國）</Label>
                <Input id="year_code" v-model="form.year_code" placeholder="例：115" required />
                <InputError :message="form.errors.year_code" />
                <p class="text-xs text-muted-foreground">會成為學號前段，如 11507001 的「115」。</p>
            </div>
            <div class="grid gap-2">
                <Label for="name">顯示名稱</Label>
                <Input id="name" v-model="form.name" placeholder="空白則自動為「115學年度」" />
                <InputError :message="form.errors.name" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_current" type="checkbox" class="size-4 accent-[var(--brand-green)]" />
                設為目前學年（新增學生時預設）
            </label>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child><Link href="/academic-years">返回</Link></Button>
            </div>
        </form>
    </div>
</template>
