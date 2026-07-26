<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-border/80 bg-card/90 px-3 backdrop-blur-md supports-[backdrop-filter]:bg-card/75 sm:h-16 sm:gap-4 sm:px-4 lg:px-6"
    >
        <SidebarTrigger />
        <div v-if="breadcrumbs && breadcrumbs.length > 0" class="min-w-0 flex-1 overflow-x-auto">
            <Breadcrumbs :breadcrumbs="breadcrumbs" class="flex-nowrap whitespace-nowrap" />
        </div>
    </header>
</template>
