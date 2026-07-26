<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string | null;
}>();
</script>

<template>
    <article class="rounded-xl border border-border/80 bg-card p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold leading-snug text-foreground">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground break-words">{{ subtitle }}</p>
            </div>
            <slot name="badge" />
        </div>
        <dl class="mt-3 space-y-2 text-sm">
            <slot />
        </dl>
        <div v-if="$slots.actions" class="mt-3 border-t border-border/60 pt-3">
            <slot name="actions" />
        </div>
    </article>
</template>
