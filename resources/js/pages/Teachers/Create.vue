<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    teacherUsers: Array<{ id: number; name: string; email: string }>;
}>();

const form = useForm({
    user_id: '',
    name: '',
    phone: '',
    status: 'active',
    note: '',
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '老師管理', href: '/teachers' },
            { title: '新增', href: '/teachers/create' },
        ],
    },
});
</script>

<template>
    <Head title="新增老師" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增老師</h1>
        <form @submit.prevent="form.post('/teachers')" class="space-y-4 rounded-xl border p-4">
            <div class="grid gap-2">
                <Label for="name">姓名</Label>
                <Input id="name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2 md:grid-cols-2 md:gap-4">
                <div class="grid gap-2">
                    <Label for="phone">電話</Label>
                    <Input id="phone" v-model="form.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="status">狀態</Label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border px-3">
                        <option value="active">在職</option>
                        <option value="paused">停用</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="user_id">綁定老師帳號（可選）</Label>
                <select id="user_id" v-model="form.user_id" class="h-9 rounded-md border px-3">
                    <option value="">未綁定</option>
                    <option v-for="u in teacherUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                </select>
                <InputError :message="form.errors.user_id" />
            </div>
            <div class="grid gap-2">
                <Label for="note">備註</Label>
                <textarea id="note" v-model="form.note" class="min-h-24 rounded-md border p-3" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child><Link href="/teachers">返回</Link></Button>
            </div>
        </form>
    </div>
</template>
