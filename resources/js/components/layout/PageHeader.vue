<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="page-header">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
            <slot name="below-title" />
        </div>
        <div v-if="$slots.actions" class="page-header__actions">
            <slot name="actions" />
        </div>
    </div>
</template>
