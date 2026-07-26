<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { normalizeClassroomHex, sanitizeClassroomHexInput } from '@/lib/classroomColor';

const props = withDefaults(
    defineProps<{
        id?: string;
        placeholder?: string;
    }>(),
    {
        id: 'color',
        placeholder: '#6366f1',
    },
);

const model = defineModel<string>({ required: true });

const pickerId = computed(() => `${props.id}-picker`);

/** 色票選擇器僅接受完整六位色碼 */
const pickerValue = computed(() => normalizeClassroomHex(model.value || null));

const onPickerInput = (event: Event) => {
    model.value = (event.target as HTMLInputElement).value.toLowerCase();
};

const onTextBlur = () => {
    model.value = sanitizeClassroomHexInput(model.value);
};
</script>

<template>
    <div class="flex items-center gap-2">
        <input
            :id="pickerId"
            type="color"
            :value="pickerValue"
            class="h-9 w-9 shrink-0 cursor-pointer rounded-md border border-border/60 p-0.5 shadow-sm"
            :title="'選擇顏色'"
            @input="onPickerInput"
        />
        <Input
            :id="props.id"
            v-model="model"
            type="text"
            class="max-w-[9rem] font-mono"
            :placeholder="props.placeholder"
            maxlength="7"
            spellcheck="false"
            autocomplete="off"
            @blur="onTextBlur"
        />
    </div>
</template>
