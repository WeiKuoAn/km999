<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';
import { computed } from 'vue';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

/** 繳費名單編輯等 iframe embed：不要整包 App 殼（側欄／麵包屑） */
const isPaymentEmbed = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }
    try {
        return new URLSearchParams(window.location.search).get('embed') === '1';
    } catch {
        return false;
    }
});

if (typeof document !== 'undefined' && isPaymentEmbed.value) {
    document.body.classList.add('payment-embed');
}
</script>

<template>
    <div v-if="isPaymentEmbed" class="min-h-0 w-full bg-background">
        <slot />
        <Toaster />
    </div>
    <AppShell v-else variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
