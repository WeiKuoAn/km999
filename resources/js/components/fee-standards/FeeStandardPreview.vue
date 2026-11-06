<script setup lang="ts">
import { computed } from 'vue';
import type { FeeStandardFormItem } from '@/lib/feeStandardForm';

const props = defineProps<{
    title: string;
    deadlineLabel: string;
    footerNote: string;
    defaultFooter: string;
    items: FeeStandardFormItem[];
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

const displayChecks = computed(() =>
    props.items.filter(
        (item) =>
            item.kind !== 'table' &&
            (item.label.trim() !== '' || item.detail.trim() !== ''),
    ),
);

const displayTables = computed(() =>
    props.items.filter(
        (item) =>
            item.kind === 'table' &&
            (item.label.trim() !== '' ||
                item.table_headers.some((h) => h.trim() !== '') ||
                item.table_rows.some((row) =>
                    row.some((cell) => (cell ?? '').trim() !== ''),
                )),
    ),
);

const isEmpty = computed(
    () => displayChecks.value.length === 0 && displayTables.value.length === 0,
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

        <div v-if="isEmpty" class="mb-6 text-sm text-neutral-500">
            請在左側分別新增勾選項目或收費表格，預覽會即時顯示。
        </div>

        <div
            v-for="(item, i) in displayChecks"
            :key="`check-${i}`"
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

        <div
            v-for="(item, i) in displayTables"
            :key="`table-${i}`"
            class="mb-5"
        >
            <p
                v-if="item.label.trim()"
                class="mb-2 text-[15px] tracking-wide sm:text-base"
                :style="{
                    color: item.heading_color || '#1d4ed8',
                }"
            >
                {{ item.label.trim() }}
            </p>
            <table
                class="w-full border-collapse text-center text-[12px] sm:text-[13px]"
            >
                <thead>
                    <tr class="bg-[#ffe566]">
                        <th
                            v-for="(header, ci) in item.table_headers"
                            :key="`ph-${i}-${ci}`"
                            class="border border-neutral-700 px-2 py-1.5 font-medium"
                        >
                            {{ header.trim() || `欄${ci + 1}` }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, ri) in item.table_rows"
                        :key="`pr-${i}-${ri}`"
                    >
                        <td
                            v-for="(_h, ci) in item.table_headers"
                            :key="`pc-${i}-${ri}-${ci}`"
                            class="border border-neutral-700 px-2 py-1.5"
                        >
                            {{ (row[ci] ?? '').trim() || '　' }}
                        </td>
                    </tr>
                </tbody>
            </table>
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
