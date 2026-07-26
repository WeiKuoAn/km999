<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed } from 'vue';
import { classroomSwatchStyle } from '@/lib/classroomColor';

const ALL = '__all__';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: Array<{ id: number; name: string; color: string | null }>;
        placeholder?: string;
        id?: string;
        disabled?: boolean;
        /** 例如：h-9 min-w-0 flex-1 */
        triggerClass?: string;
    }>(),
    {
        placeholder: '全部班級',
        disabled: false,
        triggerClass: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const rootModel = computed({
    get: () => (props.modelValue === '' ? ALL : props.modelValue),
    set: (v: unknown) => {
        const s = String(v ?? '');
        emit('update:modelValue', s === ALL ? '' : s);
    },
});

const triggerSwatchStyle = computed(() => {
    if (!props.modelValue) {
        return { backgroundColor: '#e2e8f0' };
    }
    const c = props.options.find((x) => String(x.id) === props.modelValue);

    return classroomSwatchStyle(c?.color ?? null);
});
</script>

<template>
    <Select v-model="rootModel">
        <SelectTrigger
            :id="id"
            :disabled="disabled"
            :class="[
                'h-9 w-full min-w-0 flex-1 justify-between gap-2 border bg-background px-3 shadow-xs',
                triggerClass,
            ]"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <span
                    class="h-3 w-3 shrink-0 rounded-full border border-border shadow-sm"
                    aria-hidden="true"
                    :style="triggerSwatchStyle"
                />
                <SelectValue :placeholder="placeholder" class="truncate text-left" />
            </div>
        </SelectTrigger>
        <SelectContent position="popper">
            <SelectItem :value="ALL">
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full border border-border bg-muted" aria-hidden="true" />
                    {{ placeholder }}
                </span>
            </SelectItem>
            <SelectItem v-for="c in options" :key="c.id" :value="String(c.id)">
                <span class="flex items-center gap-2">
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/60 shadow-sm"
                        aria-hidden="true"
                        :style="classroomSwatchStyle(c.color)"
                    />
                    {{ c.name }}
                </span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
