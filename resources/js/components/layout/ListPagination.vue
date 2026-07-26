<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: Array<{ url: string | null; label: string; active: boolean }>;
}>();
</script>

<template>
    <nav v-if="links.length > 0" class="list-pagination" aria-label="分頁">
        <Link
            v-for="link in links"
            :key="link.label"
            :href="link.url || '#'"
            class="list-pagination__link"
            :class="{
                'list-pagination__link--active': link.active,
                'pointer-events-none opacity-50': !link.url,
            }"
            v-html="link.label"
        />
    </nav>
</template>
