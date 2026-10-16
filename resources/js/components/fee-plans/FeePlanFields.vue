<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- form 是父頁的 Inertia useForm 物件，欄位由這裡直接編輯 */
import type { InertiaForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import CourseCheckboxGroup from '@/components/CourseCheckboxGroup.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    FeePlanBillingKind,
    FeePlanBundleRow,
    FeePlanCourseOption,
    FeePlanFormData,
} from '@/lib/feePlanForm';
import { periodMonths } from '@/lib/feePlanForm';

const props = defineProps<{
    form: InertiaForm<FeePlanFormData>;
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    courses: FeePlanCourseOption[];
    requireGrade?: boolean;
}>();

const selectedCourses = computed(() =>
    props.form.course_ids
        .map((id) => props.courses.find((c) => c.id === Number(id)))
        .filter((c): c is FeePlanCourseOption => !!c),
);

const months = computed(() =>
    periodMonths(props.form.billing_kind, props.form.session_block_size),
);

const isPeriodBilling = computed(() => props.form.billing_kind !== 'month');

const periodListTotal = computed(() =>
    Math.round(Number(props.form.list_price || 0) * months.value),
);

const periodQuarterTotal = computed(() => {
    if (
        props.form.quarter_price === '' ||
        props.form.quarter_price === null ||
        props.form.quarter_price === undefined
    ) {
        return null;
    }

    return Math.round(Number(props.form.quarter_price) * months.value);
});

const onBillingKindChange = (kind: FeePlanBillingKind) => {
    props.form.billing_kind = kind;
    if (kind === 'quarter') {
        props.form.session_block_size = 3;
    } else if (
        kind === 'other' &&
        (!props.form.session_block_size || Number(props.form.session_block_size) < 2)
    ) {
        props.form.session_block_size = 4;
    }
};

// 收費組別跟著課程走（計價看的是課程上的組別）
watch(
    selectedCourses,
    (courses) => {
        props.form.pricing_group = courses[0]?.pricing_group ?? 'other';
    },
    { immediate: true },
);

const planCourseIds = computed(
    () => new Set(props.form.course_ids.map(Number)),
);

const otherCourseIds = computed(
    () =>
        new Set(
            props.courses
                .filter((c) => !planCourseIds.value.has(c.id) && c.status === 'active')
                .map((c) => c.id),
        ),
);

/** 本收費設定的課排前面，其他課（跨收費設定的組合，如國英雙科）排後面 */
const bundleCourseOptions = computed(() => {
    const inBundles = new Set(props.form.bundles.flatMap((b) => b.course_ids));

    return [
        ...selectedCourses.value,
        ...props.courses.filter(
            (c) =>
                !planCourseIds.value.has(c.id) &&
                (c.status === 'active' || inBundles.has(c.id)),
        ),
    ];
});

const addBundle = () => {
    props.form.bundles.push({
        id: null,
        name: '',
        course_ids: [...planCourseIds.value],
        min_courses: 2,
        price: '',
    });
};

const toggleBundleCourse = (bundle: FeePlanBundleRow, courseId: number) => {
    bundle.course_ids = bundle.course_ids.includes(courseId)
        ? bundle.course_ids.filter((id) => id !== courseId)
        : [...bundle.course_ids, courseId];
};

const bundleError = (index: number, field: string) =>
    (props.form.errors as Record<string, string | undefined>)[
        `bundles.${index}.${field}`
    ];

if (props.form.material_unit === 'subject') {
    props.form.material_unit = 'term';
}
</script>

<template>
    <div
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900"
    >
        收費設定是幫「課程」定價。新開的班請先到
        <Link href="/courses" class="font-medium underline underline-offset-4"
            >課程管理</Link
        >
        建立課程（設定類別、上課時間），再回來這裡勾選；單科價和雙科／多科優惠都在這頁設定。
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="grade_level_id">年級</Label>
            <select
                id="grade_level_id"
                v-model="form.grade_level_id"
                class="h-9 rounded-md border px-3"
                required
            >
                <option v-if="requireGrade" value="">請選擇</option>
                <option value="all">全年級</option>
                <option v-for="g in gradeLevels" :key="g.id" :value="String(g.id)">
                    {{ g.name }}
                </option>
            </select>
            <p class="text-xs text-muted-foreground">
                不同年級價格不同時，每個年級各建一筆。
            </p>
            <InputError :message="form.errors.grade_level_id" />
        </div>
        <div class="grid gap-2">
            <Label for="academic_year_id">學年</Label>
            <select
                id="academic_year_id"
                v-model="form.academic_year_id"
                class="h-9 rounded-md border px-3"
            >
                <option value="">不指定（每年都適用）</option>
                <option v-for="y in academicYears" :key="y.id" :value="String(y.id)">
                    {{ y.name }}（{{ y.year_code }}）
                </option>
            </select>
            <InputError :message="form.errors.academic_year_id" />
        </div>
    </div>

    <CourseCheckboxGroup
        v-model="form.course_ids"
        :courses="courses"
        :error="form.errors.course_ids"
    />

    <div class="grid gap-2">
        <Label for="group_name">名稱</Label>
        <Input
            id="group_name"
            v-model="form.group_name"
            placeholder="例：高瞻英文"
            required
        />
        <p class="text-xs text-muted-foreground">
            顯示在收費設定列表與收款畫面。
        </p>
        <InputError :message="form.errors.group_name" />
    </div>

    <div class="grid gap-2">
        <Label for="billing_kind">收費方式</Label>
        <select
            id="billing_kind"
            class="h-9 rounded-md border px-3"
            :value="form.billing_kind"
            @change="
                onBillingKindChange(
                    ($event.target as HTMLSelectElement)
                        .value as FeePlanBillingKind,
                )
            "
        >
            <option value="month">按月</option>
            <option value="quarter">按季（一次繳 3 個月）</option>
            <option value="other">其他（自訂月份）</option>
        </select>
        <p class="text-xs text-muted-foreground">
            <template v-if="form.billing_kind === 'month'">
                每月收一次。請填一個月的原價與優惠價。
            </template>
            <template v-else-if="form.billing_kind === 'quarter'">
                一次繳 3 個月。請填「一個月」的原價與優惠價，下方會自動顯示 ×3。
            </template>
            <template v-else>
                一次繳自訂月數。請填「一個月」的原價與優惠價，下方會自動顯示 ×月數。
            </template>
        </p>
    </div>

    <div v-if="form.billing_kind === 'other'" class="grid max-w-xs gap-2">
        <Label for="session_block_size">一期幾個月</Label>
        <Input
            id="session_block_size"
            v-model.number="form.session_block_size"
            type="number"
            min="2"
            max="12"
            required
        />
        <InputError :message="form.errors.session_block_size" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="list_price">{{
                isPeriodBilling ? '一個月原價' : '原價'
            }}</Label>
            <Input
                id="list_price"
                v-model.number="form.list_price"
                type="number"
                min="0"
                required
            />
            <InputError :message="form.errors.list_price" />
        </div>
        <div class="grid gap-2">
            <Label for="quarter_price">{{
                isPeriodBilling ? '一個月優惠價' : '優惠價'
            }}</Label>
            <Input
                id="quarter_price"
                v-model.number="form.quarter_price"
                type="number"
                min="0"
            />
            <InputError :message="form.errors.quarter_price" />
        </div>
    </div>

    <div
        v-if="isPeriodBilling"
        class="grid gap-4 rounded-lg border bg-muted/30 p-3 sm:grid-cols-2"
    >
        <div>
            <p class="text-xs text-muted-foreground">
                一期總額（原價 × {{ months }}）
            </p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums">
                ${{ periodListTotal.toLocaleString('zh-TW') }}
            </p>
        </div>
        <div>
            <p class="text-xs text-muted-foreground">
                一期總額（優惠價 × {{ months }}）
            </p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums">
                <template v-if="periodQuarterTotal !== null">
                    ${{ periodQuarterTotal.toLocaleString('zh-TW') }}
                </template>
                <span v-else class="text-sm font-normal text-muted-foreground"
                    >同原價一期總額</span
                >
            </p>
        </div>
    </div>
    <div class="space-y-3 rounded-lg border p-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm font-medium">雙科／多科優惠</p>
                <p class="text-xs text-muted-foreground">
                    上面是單科價。學生同時報了優惠裡的課，會改收這裡的優惠價。沒有優惠就不用加。
                </p>
            </div>
            <Button type="button" variant="outline" size="sm" @click="addBundle">
                <Plus class="size-4" />
                新增優惠
            </Button>
        </div>

        <div
            v-for="(bundle, i) in form.bundles"
            :key="i"
            class="space-y-3 rounded-md border bg-muted/30 p-3"
        >
            <div class="grid gap-1.5">
                <span class="text-xs font-medium text-muted-foreground"
                    >一起報的課</span
                >
                <div class="flex flex-wrap gap-1.5">
                    <label
                        v-for="course in bundleCourseOptions"
                        :key="course.id"
                        class="flex cursor-pointer items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-sm"
                        :class="
                            bundle.course_ids.includes(course.id)
                                ? 'border-primary text-primary'
                                : 'text-muted-foreground'
                        "
                    >
                        <input
                            type="checkbox"
                            class="size-3.5 accent-[var(--brand-green)]"
                            :checked="bundle.course_ids.includes(course.id)"
                            @change="toggleBundleCourse(bundle, course.id)"
                        />
                        {{ course.name }}
                    </label>
                </div>
                <p
                    v-if="otherCourseIds.size"
                    class="text-xs text-muted-foreground"
                >
                    也可以勾其他收費設定的課，例如國英雙科勾國文課＋英文課。
                </p>
                <InputError :message="bundleError(i, 'course_ids')" />
            </div>

            <div class="grid gap-3 sm:grid-cols-[auto_auto_1fr_auto] sm:items-end">
                <div class="grid gap-1.5">
                    <Label :for="`bundle-min-${i}`">至少報幾科</Label>
                    <Input
                        :id="`bundle-min-${i}`"
                        v-model.number="bundle.min_courses"
                        type="number"
                        min="2"
                        class="w-24"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`bundle-price-${i}`">優惠價（每科每月）</Label>
                    <Input
                        :id="`bundle-price-${i}`"
                        v-model.number="bundle.price"
                        type="number"
                        min="0"
                        class="w-36"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`bundle-name-${i}`">名稱（選填）</Label>
                    <Input
                        :id="`bundle-name-${i}`"
                        v-model="bundle.name"
                        placeholder="留白自動命名，例：英數 雙科"
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive"
                    @click="form.bundles.splice(i, 1)"
                >
                    <Trash2 class="size-4" />
                    刪除
                </Button>
            </div>
            <InputError :message="bundleError(i, 'min_courses')" />
            <InputError :message="bundleError(i, 'price')" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="material_unit">教材費收法</Label>
            <select
                id="material_unit"
                v-model="form.material_unit"
                class="h-9 rounded-md border px-3"
            >
                <option value="term">每年（上、下半年各收一半）</option>
                <option value="class_day">每個上課日（耗材）</option>
            </select>
        </div>
        <div class="grid gap-2">
            <Label for="material_fee">{{
                form.material_unit === 'class_day' ? '每日耗材費' : '教材年費'
            }}</Label>
            <Input
                id="material_fee"
                v-model.number="form.material_fee"
                type="number"
                min="0"
            />
            <p class="text-xs text-muted-foreground">
                {{
                    form.material_unit === 'class_day'
                        ? '依實際上課日數計算，例：一天 200。'
                        : '例：1,200 → 1 月、7 月各收 600。沒有教材填 0。'
                }}
            </p>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input
            v-model="form.is_active"
            type="checkbox"
            class="size-4 accent-[var(--brand-green)]"
        />
        啟用
    </label>
</template>
