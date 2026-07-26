<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

withDefaults(
    defineProps<{
        href: string;
        label?: string;
    }>(),
    { label: '編輯' },
);
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="text-primary hover:bg-primary/10 hover:text-primary"
                as-child
            >
                <Link :href="href">
                    <Pencil class="size-4" />
                    <span class="sr-only">{{ label }}</span>
                </Link>
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ label }}</TooltipContent>
    </Tooltip>
</template>
