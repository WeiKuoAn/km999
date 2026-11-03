<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    deadlineLabel: string;
    footerNote: string;
    defaultFooter: string;
    items: Array<{ label: string; detail: string }>;
    /** 預覽用假名 */
    sampleStudentName?: string;
}>();

const displayTitle = computed(() => {
    const t = props.title.trim();
    return t !== '' ? t : '（尚未填寫標題）';
});

const displayFooter = computed(() => {
    const note = props.footerNote.trim();
    return note !== '' ? note : props.defaultFooter;
});

const displayItems = computed(() =>
    props.items.filter(
        (item) => item.label.trim() !== '' || item.detail.trim() !== '',
    ),
);

const labelWithColon = (label: string): string => {
    const trimmed = label.trim();
    if (trimmed === '') {
        return '（空白項目）';
    }
    if (trimmed.endsWith('：') || trimmed.endsWith(':')) {
        return trimmed;
    }
    return `${trimmed}：`;
};
</script>

<template>
    <div
        class="rounded-sm border border-neutral-300 bg-white py-8 pr-6 pl-10 text-neutral-900 shadow-sm sm:pr-8 sm:pl-14"
        style="font-family: 'Noto Sans TC', 'Microsoft JhengHei', sans-serif"
    >
        <p
            class="mb-8 text-center text-xl font-semibold tracking-[0.18em] sm:text-2xl"
        >
            {{ displayTitle }}
        </p>

        <p class="mb-6 text-base sm:text-lg">
            貴弟子：
            <span class="mx-1 underline underline-offset-4">{{
                sampleStudentName ?? '郭睿恩'
            }}</span>
            同學
        </p>

        <div v-if="displayItems.length === 0" class="mb-6 text-sm text-neutral-500">
            請在左側新增勾選項目，預覽會即時顯示。
        </div>

        <div
            v-for="(item, i) in displayItems"
            :key="i"
            class="mb-5 flex gap-2"
        >
            <span
                class="mt-0.5 shrink-0 select-none text-lg leading-none text-neutral-800"
                aria-hidden="true"
                >□</span
            >
            <div class="min-w-0 flex-1">
                <p class="text-[15px] leading-snug sm:text-base">
                    {{ labelWithColon(item.label) }}
                </p>
                <p
                    v-if="item.detail.trim()"
                    class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-neutral-700 sm:text-[15px]"
                >
                    {{ item.detail.trim() }}
                </p>
            </div>
        </div>

        <p class="mt-8 text-base sm:text-lg">
            合計：
            <span
                class="mx-2 inline-block min-w-[7rem] border-b border-neutral-900 text-center"
                >&nbsp;</span
            >
            元
        </p>

        <p v-if="deadlineLabel.trim()" class="mt-4 text-[15px] sm:text-base">
            請於 {{ deadlineLabel.trim() }} 前繳納，以利作業
        </p>

        <p class="mt-8 text-sm leading-relaxed text-neutral-700 sm:text-[15px]">
            {{ displayFooter }}
        </p>
    </div>
</template>
