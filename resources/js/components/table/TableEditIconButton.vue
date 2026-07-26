<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

withDefaults(
    defineProps<{
        label?: string;
    }>(),
    { label: '編輯' },
);

defineEmits<{
    click: [];
}>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="text-primary hover:bg-primary/10 hover:text-primary"
                @click="$emit('click')"
            >
                <Pencil class="size-4" />
                <span class="sr-only">{{ label }}</span>
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ label }}</TooltipContent>
    </Tooltip>
</template>
