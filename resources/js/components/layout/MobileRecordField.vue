<script setup lang="ts">
defineProps<{
    label: string;
}>();
</script>

<template>
    <div class="grid grid-cols-[4.5rem_1fr] gap-x-3 gap-y-0.5 sm:grid-cols-[5rem_1fr]">
        <dt class="text-muted-foreground">{{ label }}</dt>
        <dd class="min-w-0 break-words font-medium text-foreground">
            <slot />
        </dd>
    </div>
</template>
