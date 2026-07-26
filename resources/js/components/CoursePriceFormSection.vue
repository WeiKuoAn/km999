<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    type CoursePriceTierInput,
    type DurationGroupInput,
    defaultSingleTier,
    durationLabel,
    normalizeDuration,
    suggestNextDuration,
} from '@/lib/coursePriceForm';

const props = defineProps<{
    hasMultipleDurations: boolean;
    singleDurationHours: number;
    singleTiers: CoursePriceTierInput[];
    durationGroups: DurationGroupInput[];
    errors?: Record<string, string | undefined>;
}>();

const emit = defineEmits<{
    'update:hasMultipleDurations': [value: boolean];
    'update:singleDurationHours': [value: number];
    'update:singleTiers': [value: CoursePriceTierInput[]];
    'update:durationGroups': [value: DurationGroupInput[]];
}>();

const multi = ref(props.hasMultipleDurations);

watch(
    () => props.hasMultipleDurations,
    (v) => {
        multi.value = v;
    },
);

const sortedGroups = computed(() =>
    [...props.durationGroups].sort((a, b) => a.duration_hours - b.duration_hours),
);

const onMultiChange = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    multi.value = checked;
    emit('update:hasMultipleDurations', checked);

    if (checked) {
        emit('update:durationGroups', [
            {
                duration_hours: normalizeDuration(props.singleDurationHours || 1),
                tiers: props.singleTiers.map((t) => ({ ...t })),
            },
            {
                duration_hours: suggestNextDuration([props.singleDurationHours || 1]),
                tiers: props.singleTiers.map((t) => ({ ...t, tuition: 0 })),
            },
        ]);
    } else {
        emit('update:singleDurationHours', 1);
        const first = props.durationGroups[0];
        if (first) {
            emit('update:singleTiers', first.tiers.map((t) => ({ ...t })));
        }
    }
};

const findGroupIndex = (durationHours: number) =>
    props.durationGroups.findIndex((g) => normalizeDuration(g.duration_hours) === normalizeDuration(durationHours));

const updateGroupDuration = (groupIndex: number, value: number) => {
    emit(
        'update:durationGroups',
        props.durationGroups.map((g, i) =>
            i === groupIndex ? { ...g, duration_hours: normalizeDuration(value) } : g,
        ),
    );
};

const addDurationGroup = () => {
    const used = props.durationGroups.map((g) => g.duration_hours);
    const next = suggestNextDuration(used);
    const template = props.durationGroups[0]?.tiers ?? [defaultSingleTier()];

    emit('update:durationGroups', [
        ...props.durationGroups,
        {
            duration_hours: next,
            tiers: template.map((t) => ({ ...t, tuition: 0 })),
        },
    ]);
};

const removeDurationGroup = (groupIndex: number) => {
    if (props.durationGroups.length <= 2) return;
    emit(
        'update:durationGroups',
        props.durationGroups.filter((_, i) => i !== groupIndex),
    );
};

const addTier = (groupIndex: number | 'single') => {
    const row = defaultSingleTier();
    row.tuition = 0;

    if (groupIndex === 'single') {
        emit('update:singleTiers', [...props.singleTiers, row]);
        return;
    }

    emit(
        'update:durationGroups',
        props.durationGroups.map((g, i) =>
            i === groupIndex ? { ...g, tiers: [...g.tiers, row] } : g,
        ),
    );
};

const removeTier = (groupIndex: number | 'single', tierIndex: number) => {
    if (groupIndex === 'single') {
        if (props.singleTiers.length <= 1) return;
        emit(
            'update:singleTiers',
            props.singleTiers.filter((_, i) => i !== tierIndex),
        );
        return;
    }

    const group = props.durationGroups[groupIndex];
    if (!group || group.tiers.length <= 1) return;

    emit(
        'update:durationGroups',
        props.durationGroups.map((g, i) =>
            i === groupIndex ? { ...g, tiers: g.tiers.filter((_, ti) => ti !== tierIndex) } : g,
        ),
    );
};

const updateTier = (
    groupIndex: number | 'single',
    tierIndex: number,
    field: 'level' | 'tuition',
    value: string | number,
) => {
    if (groupIndex === 'single') {
        emit(
            'update:singleTiers',
            props.singleTiers.map((t, i) => (i === tierIndex ? { ...t, [field]: value } : t)),
        );
        return;
    }

    emit(
        'update:durationGroups',
        props.durationGroups.map((g, gi) =>
            gi === groupIndex
                ? { ...g, tiers: g.tiers.map((t, ti) => (ti === tierIndex ? { ...t, [field]: value } : t)) }
                : g,
        ),
    );
};
</script>

<template>
    <div class="space-y-4 rounded-lg border p-4">
        <div class="space-y-2">
            <Label class="text-base font-medium">{{ multi ? '上課時數與學費' : '學段與學費' }}</Label>
            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    class="size-4 rounded border"
                    :checked="multi"
                    @change="onMultiChange"
                />
                <span class="font-medium">此課程有多種上課時數</span>
            </label>
        </div>

        <!-- 有多種時數：① 上課時數 -->
        <section v-if="multi" class="space-y-3 rounded-lg bg-muted/30 p-4">
            <div class="flex items-center gap-2">
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                >
                    1
                </span>
                <Label class="text-base font-medium">上課時數</Label>
            </div>
            <div class="space-y-2">
                <div
                    v-for="(group, gi) in durationGroups"
                    :key="'dur-' + gi"
                    class="flex items-end gap-2 rounded-md border bg-background p-3"
                >
                    <div class="grid flex-1 gap-1 sm:max-w-[10rem]">
                        <Label :for="'dur_' + gi" class="text-xs text-muted-foreground">小時</Label>
                        <Input
                            :id="'dur_' + gi"
                            type="number"
                            min="0.5"
                            max="24"
                            step="0.5"
                            :model-value="group.duration_hours"
                            @update:model-value="updateGroupDuration(gi, Number($event))"
                        />
                    </div>
                    <Button
                        v-if="durationGroups.length > 2"
                        type="button"
                        variant="destructive"
                        size="sm"
                        @click="removeDurationGroup(gi)"
                    >
                        移除
                    </Button>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addDurationGroup">新增時數</Button>
            </div>
        </section>

        <!-- 學段與學費 -->
        <section class="space-y-3 rounded-lg bg-muted/30 p-4">
            <div class="flex items-center gap-2">
                <span
                    v-if="multi"
                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                >
                    2
                </span>
                <Label class="text-base font-medium">學段與學費</Label>
            </div>

            <!-- 單一方案 -->
            <div v-if="!multi" class="space-y-2">
                <div class="flex justify-end">
                    <Button type="button" variant="outline" size="sm" @click="addTier('single')">新增學段</Button>
                </div>
                <div
                    v-for="(tier, index) in singleTiers"
                    :key="'single-' + index"
                    class="grid items-end gap-2 md:grid-cols-[1fr_140px_72px]"
                >
                    <div class="grid gap-1">
                        <Label :for="'single_level_' + index">學段</Label>
                        <select
                            :id="'single_level_' + index"
                            :value="tier.level"
                            class="h-9 rounded-md border px-3"
                            @change="updateTier('single', index, 'level', ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">不分學段</option>
                            <option value="大學">大學</option>
                            <option value="高中">高中</option>
                            <option value="國中">國中</option>
                            <option value="國小">國小</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label :for="'single_tuition_' + index">學費</Label>
                        <Input
                            :id="'single_tuition_' + index"
                            type="number"
                            min="0"
                            :model-value="tier.tuition"
                            @update:model-value="updateTier('single', index, 'tuition', Number($event))"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="singleTiers.length === 1"
                        @click="removeTier('single', index)"
                    >
                        移除
                    </Button>
                </div>
            </div>

            <!-- 多種時數 -->
            <div v-else class="space-y-4">
                <div
                    v-for="group in sortedGroups"
                    :key="'fees-' + group.duration_hours"
                    class="space-y-2 rounded-md border bg-background p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">{{ durationLabel(group.duration_hours) }}</span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addTier(findGroupIndex(group.duration_hours))"
                        >
                            新增學段
                        </Button>
                    </div>
                    <div
                        v-for="(tier, index) in group.tiers"
                        :key="group.duration_hours + '-' + index"
                        class="grid items-end gap-2 md:grid-cols-[1fr_140px_72px]"
                    >
                        <div class="grid gap-1">
                            <Label :for="'g' + group.duration_hours + '_level_' + index">學段</Label>
                            <select
                                :id="'g' + group.duration_hours + '_level_' + index"
                                :value="tier.level"
                                class="h-9 rounded-md border px-3"
                                @change="
                                    updateTier(findGroupIndex(group.duration_hours), index, 'level', ($event.target as HTMLSelectElement).value)
                                "
                            >
                                <option value="">不分學段</option>
                                <option value="大學">大學</option>
                                <option value="高中">高中</option>
                                <option value="國中">國中</option>
                                <option value="國小">國小</option>
                            </select>
                        </div>
                        <div class="grid gap-1">
                            <Label :for="'g' + group.duration_hours + '_tuition_' + index">學費</Label>
                            <Input
                                :id="'g' + group.duration_hours + '_tuition_' + index"
                                type="number"
                                min="0"
                                :model-value="tier.tuition"
                                @update:model-value="
                                    updateTier(findGroupIndex(group.duration_hours), index, 'tuition', Number($event))
                                "
                            />
                        </div>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            :disabled="group.tiers.length === 1"
                            @click="removeTier(findGroupIndex(group.duration_hours), index)"
                        >
                            移除
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <InputError :message="errors?.tiers" />
        <InputError :message="errors?.['tiers.0.tuition']" />
    </div>
</template>
