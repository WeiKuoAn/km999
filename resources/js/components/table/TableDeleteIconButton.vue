<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

withDefaults(
    defineProps<{
        label?: string;
    }>(),
    { label: '刪除' },
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
                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                @click="$emit('click')"
            >
                <Trash2 class="size-4" />
                <span class="sr-only">{{ label }}</span>
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ label }}</TooltipContent>
    </Tooltip>
</template>
