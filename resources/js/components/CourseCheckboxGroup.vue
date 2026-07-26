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

const props = defineProps<{
    courses: CourseOption[];
    error?: string;
}>();

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
    <div class="space-y-3 rounded-lg border p-4">
        <div>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-medium">適用課目</h2>
                <span class="text-sm text-muted-foreground"
                    >已選 {{ model.length }} 門</span
                >
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                報名試算只會對勾選的課目套用此收費標準；同學年、同年級不可重複綁定。
            </p>
        </div>

        <p
            v-if="courses.length === 0"
            class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
        >
            尚無課程，請先至「課程管理」新增。
        </p>

        <template v-else>
            <div
                v-for="group in groups"
                :key="group.id"
                class="space-y-2 rounded-md bg-muted/30 p-3"
            >
                <label
                    class="flex cursor-pointer items-center gap-2 border-b pb-2 text-sm font-medium"
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

                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="course in group.courses"
                        :key="course.id"
                        class="flex items-start gap-2 rounded-md border bg-background p-2 text-sm"
                        :class="
                            course.status === 'paused' && !isSelected(course.id)
                                ? 'cursor-not-allowed opacity-50'
                                : 'cursor-pointer'
                        "
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 size-4 accent-[var(--brand-green)]"
                            :checked="isSelected(course.id)"
                            :disabled="
                                course.status === 'paused' &&
                                !isSelected(course.id)
                            "
                            @change="toggleCourse(course.id)"
                        />
                        <span
                            class="mt-0.5 size-3 shrink-0 rounded-full border"
                            :style="classroomSwatchStyle(course.color)"
                        />
                        <span class="min-w-0">
                            <span class="block font-medium">{{
                                course.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ course.pricing_group_label }}
                                <template v-if="course.status === 'paused'">
                                    · 已停用</template
                                >
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </template>

        <InputError :message="error" />
    </div>
</template>
