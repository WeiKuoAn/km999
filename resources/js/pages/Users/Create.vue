<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'admin' as 'super_admin' | 'admin' | 'teacher',
    is_active: true,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '用戶管理', href: '/users' },
            { title: '新增', href: '/users/create' },
        ],
    },
});
</script>

<template>
    <Head title="新增用戶" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增用戶</h1>
        <form @submit.prevent="form.post('/users')" class="space-y-4 rounded-xl border p-4">
            <div class="grid gap-2">
                <Label for="name">姓名</Label>
                <Input id="name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="email">電子郵件</Label>
                <Input id="email" type="email" v-model="form.email" />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="password">密碼</Label>
                <Input id="password" type="password" v-model="form.password" />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2 md:grid-cols-2 md:gap-4">
                <div class="grid gap-2">
                    <Label for="role">角色</Label>
                    <select id="role" v-model="form.role" class="h-9 rounded-md border px-3">
                        <option value="super_admin">超級管理員</option>
                        <option value="admin">管理員</option>
                        <option value="teacher">老師</option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>
                <div class="grid gap-2">
                    <Label for="is_active">狀態</Label>
                    <select id="is_active" v-model="form.is_active" class="h-9 rounded-md border px-3">
                        <option :value="true">啟用</option>
                        <option :value="false">停用</option>
                    </select>
                    <InputError :message="form.errors.is_active" />
                </div>
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child><Link href="/users">返回</Link></Button>
            </div>
        </form>
    </div>
</template>

