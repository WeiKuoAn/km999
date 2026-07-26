<script setup lang="ts">
import type { Component } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

defineProps<{
    href: string;
    label: string;
    icon: Component;
}>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="text-muted-foreground hover:bg-muted hover:text-foreground"
                as-child
            >
                <Link :href="href">
                    <component :is="icon" class="size-4" />
                    <span class="sr-only">{{ label }}</span>
                </Link>
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ label }}</TooltipContent>
    </Tooltip>
</template>
