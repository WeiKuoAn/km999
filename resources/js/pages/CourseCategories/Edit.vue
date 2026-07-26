<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    category: {
        id: number;
        name: string;
        sort_order: number;
    };
}>();

const form = useForm({
    name: props.category.name,
    sort_order: props.category.sort_order,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '課程類別管理', href: '/course-categories' },
            { title: '編輯', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="編輯課程類別" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">編輯課程類別</h1>
        <form
            @submit.prevent="form.put(`/course-categories/${props.category.id}`)"
            class="space-y-4 rounded-xl border p-4"
        >
            <div class="grid gap-2">
                <Label for="name">名稱</Label>
                <Input id="name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid w-full gap-2">
                <Label for="sort_order">排序（數字越小越前面）</Label>
                <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" max="255" />
                <InputError :message="form.errors.sort_order" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">更新</Button>
                <Button variant="outline" as-child><Link href="/course-categories">返回</Link></Button>
            </div>
        </form>
    </div>
</template>
