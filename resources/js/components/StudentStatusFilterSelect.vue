<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed } from 'vue';
import {
    STUDENT_STATUSES,
    studentStatusLabel,
    studentStatusPillClass,
} from '@/lib/studentStatus';

const ALL = '__all__';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        disabled?: boolean;
        id?: string;
        allLabel?: string;
        triggerClass?: string;
    }>(),
    {
        disabled: false,
        allLabel: '全部狀態',
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
        if (s === ALL) {
            emit('update:modelValue', '');
            return;
        }
        if ((STUDENT_STATUSES as readonly string[]).includes(s)) {
            emit('update:modelValue', s);
        }
    },
});
</script>

<template>
    <Select v-model="rootModel" :disabled="disabled">
        <SelectTrigger
            :id="id"
            :disabled="disabled"
            :class="[
                'h-10 w-full min-w-0 justify-between gap-2 border border-input bg-background px-3 text-foreground shadow-xs',
                triggerClass,
            ]"
        >
            <SelectValue :placeholder="allLabel">
                <span
                    v-if="modelValue && modelValue !== ALL"
                    :class="studentStatusPillClass(modelValue)"
                >
                    {{ studentStatusLabel(modelValue) }}
                </span>
                <span v-else class="truncate text-muted-foreground">{{ allLabel }}</span>
            </SelectValue>
        </SelectTrigger>
        <SelectContent position="popper">
            <SelectItem :value="ALL">
                <span class="text-muted-foreground">{{ allLabel }}</span>
            </SelectItem>
            <SelectItem v-for="st in STUDENT_STATUSES" :key="st" :value="st">
                <span :class="studentStatusPillClass(st)">{{ studentStatusLabel(st) }}</span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
