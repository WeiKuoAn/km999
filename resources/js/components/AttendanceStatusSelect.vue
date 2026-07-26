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
    attendanceStatusBadgeClass,
    ROLL_CALL_FILTER_STATUSES,
    rollCallStatusLabel,
} from '@/lib/attendanceStatus';

const ALL = '__all__';

const props = withDefaults(
    defineProps<{
        /** 點名／篩選：`''` 表示全部（需 `includeAll`）、其餘為 `present` | `excused` | `makeup` */
        modelValue: string;
        includeAll?: boolean;
        disabled?: boolean;
        allLabel?: string;
        id?: string;
        triggerClass?: string;
    }>(),
    {
        includeAll: false,
        disabled: false,
        allLabel: '全部',
        triggerClass: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const rootModel = computed({
    get: () => (props.includeAll && props.modelValue === '' ? ALL : props.modelValue),
    set: (v: unknown) => {
        const s = String(v ?? '');
        if (props.includeAll && s === ALL) {
            emit('update:modelValue', '');
            return;
        }
        if ((ROLL_CALL_FILTER_STATUSES as readonly string[]).includes(s)) {
            emit('update:modelValue', s);
        }
    },
});

function pillClass(status: string) {
    return `inline-flex max-w-full shrink-0 truncate rounded-full px-2.5 py-0.5 text-xs font-medium ${attendanceStatusBadgeClass(status)}`;
}
</script>

<template>
    <Select v-model="rootModel" :disabled="disabled">
        <SelectTrigger
            :id="id"
            :disabled="disabled"
            :class="[
                'h-9 w-full min-w-0 justify-between gap-2 border border-input bg-background px-3 text-foreground shadow-xs',
                triggerClass,
            ]"
        >
            <SelectValue :placeholder="includeAll ? allLabel : ''">
                <span
                    v-if="modelValue && modelValue !== ALL"
                    :class="pillClass(modelValue)"
                >
                    {{ rollCallStatusLabel(modelValue) }}
                </span>
                <span v-else-if="includeAll" class="truncate text-muted-foreground">{{ allLabel }}</span>
            </SelectValue>
        </SelectTrigger>
        <SelectContent position="popper">
            <SelectItem v-if="includeAll" :value="ALL">
                <span class="text-muted-foreground">{{ allLabel }}</span>
            </SelectItem>
            <SelectItem v-for="st in ROLL_CALL_FILTER_STATUSES" :key="st" :value="st">
                <span :class="pillClass(st)">{{ rollCallStatusLabel(st) }}</span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
