<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { classroomSwatchStyle } from '@/lib/classroomColor';

type CourseOption = {
    id: number;
    name: string;
    color: string | null;
    status: 'active' | 'paused';
    pricing_group: string | null;
    pricing_group_label: string;
    category_id: number;
    category_name: string;
    levels: string[];
};

const props = withDefaults(
    defineProps<{
        courses: CourseOption[];
        error?: string;
        /** 嵌在外層區塊內時去掉外框，避免雙重邊框 */
        embedded?: boolean;
    }>(),
    { embedded: false },
);

const model = defineModel<number[]>({ required: true });

const groups = computed(() => {
    const grouped = new Map<
        number,
        { id: number; name: string; courses: CourseOption[] }
    >();

    props.courses.forEach((course) => {
        const group = grouped.get(course.category_id) ?? {
            id: course.category_id,
            name: course.category_name,
            courses: [],
        };
        group.courses.push(course);
        grouped.set(course.category_id, group);
    });

    return [...grouped.values()];
});

const isSelected = (courseId: number) => model.value.includes(courseId);

const toggleCourse = (courseId: number) => {
    model.value = isSelected(courseId)
        ? model.value.filter((id) => id !== courseId)
        : [...model.value, courseId];
};

const activeIds = (courses: CourseOption[]) =>
    courses
        .filter((course) => course.status === 'active')
        .map((course) => course.id);

const isGroupSelected = (courses: CourseOption[]) => {
    const ids = activeIds(courses);

    return ids.length > 0 && ids.every((id) => model.value.includes(id));
};

const isGroupPartial = (courses: CourseOption[]) => {
    const ids = activeIds(courses);
    const count = ids.filter((id) => model.value.includes(id)).length;

    return count > 0 && count < ids.length;
};

const toggleGroup = (courses: CourseOption[]) => {
    const ids = activeIds(courses);

    if (isGroupSelected(courses)) {
        model.value = model.value.filter((id) => !ids.includes(id));

        return;
    }

    model.value = [...new Set([...model.value, ...ids])];
};
</script>

<template>
    <div :class="embedded ? 'space-y-3' : 'space-y-3 rounded-lg border p-4'">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div class="min-w-0">
                <h2 class="text-sm font-medium">適用課目</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    報名試算只會對勾選的課目套用此收費設定；同學年、同年級不可重複綁定。
                </p>
            </div>
            <span
                class="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium tabular-nums text-muted-foreground"
            >
                已選 {{ model.length }} 門
            </span>
        </div>

        <p
            v-if="courses.length === 0"
            class="rounded-md border border-dashed px-3 py-2.5 text-sm text-muted-foreground"
        >
            尚無課程，請先至「課程管理」新增。
        </p>

        <div v-else class="space-y-2.5">
            <div
                v-for="group in groups"
                :key="group.id"
                class="rounded-lg border bg-muted/20 p-2.5 sm:p-3"
            >
                <label
                    class="mb-2 flex cursor-pointer items-center gap-2 text-sm font-medium"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-[var(--brand-green)]"
                        :checked="isGroupSelected(group.courses)"
                        :indeterminate="isGroupPartial(group.courses)"
                        @change="toggleGroup(group.courses)"
                    />
                    {{ group.name }}
                    <span class="font-normal text-muted-foreground">全選</span>
                </label>

                <div
                    class="grid gap-1.5"
                    :class="embedded ? 'sm:grid-cols-1' : 'sm:grid-cols-2 lg:grid-cols-3'"
                >
                    <label
                        v-for="course in group.courses"
                        :key="course.id"
                        class="flex items-start gap-2 rounded-md border px-2.5 py-2 text-sm transition-colors"
                        :class="[
                            isSelected(course.id)
                                ? 'border-primary/40 bg-primary/5'
                                : 'border-transparent bg-background hover:border-border',
                            course.status === 'paused' && !isSelected(course.id)
                                ? 'cursor-not-allowed opacity-50'
                                : 'cursor-pointer',
                        ]"
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 size-4 shrink-0 accent-[var(--brand-green)]"
                            :checked="isSelected(course.id)"
                            :disabled="
                                course.status === 'paused' &&
                                !isSelected(course.id)
                            "
                            @change="toggleCourse(course.id)"
                        />
                        <span
                            class="mt-1 size-2.5 shrink-0 rounded-full border"
                            :style="classroomSwatchStyle(course.color)"
                        />
                        <span class="min-w-0 leading-snug">
                            <span class="block font-medium">{{
                                course.name
                            }}</span>
                            <span
                                v-if="
                                    course.levels.length ||
                                    course.status === 'paused'
                                "
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                            >
                                {{ course.levels.join('、') }}
                                <template v-if="course.status === 'paused'">
                                    {{ course.levels.length ? ' · ' : ''
                                    }}已停用</template
                                >
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <InputError :message="error" />
    </div>
</template>
