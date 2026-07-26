<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({
    name: '',
    sort_order: 0,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '課程類別管理', href: '/course-categories' },
            { title: '新增', href: '/course-categories/create' },
        ],
    },
});
</script>

<template>
    <Head title="新增課程類別" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增課程類別</h1>
        <form @submit.prevent="form.post('/course-categories')" class="space-y-4 rounded-xl border p-4">
            <div class="grid gap-2">
                <Label for="name">名稱</Label>
                <Input id="name" v-model="form.name" placeholder="例：程式課" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid w-full gap-2">
                <Label for="sort_order">排序（數字越小越前面）</Label>
                <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" max="255" />
                <InputError :message="form.errors.sort_order" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child><Link href="/course-categories">返回</Link></Button>
            </div>
        </form>
    </div>
</template>
