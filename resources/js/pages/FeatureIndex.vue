<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="page-shell flex h-full flex-1 flex-col">
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ description }}</p>
            <p class="mt-4 text-sm text-muted-foreground">
                這個模組的功能頁面我會在下一步幫你接上 CRUD 與權限資料篩選。
            </p>
        </div>
    </div>
</template>
