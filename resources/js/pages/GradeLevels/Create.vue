<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({
    name: '',
    code: 7,
    sort_order: 0,
    is_active: true,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '年級編號', href: '/grade-levels' },
            { title: '新增', href: '/grade-levels/create' },
        ],
    },
});

const submit = () => form.post('/grade-levels');
</script>

<template>
    <Head title="新增年級" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增年級</h1>
        <form class="space-y-4 rounded-xl border p-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">年級名稱</Label>
                <Input id="name" v-model="form.name" placeholder="例：國一" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="code">編號</Label>
                <Input id="code" v-model.number="form.code" type="number" min="1" max="99" required />
                <InputError :message="form.errors.code" />
                <p class="text-xs text-muted-foreground">
                    建議：國一=7、國二=8、國三=9。學號會補成兩碼（7→07）。
                </p>
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
