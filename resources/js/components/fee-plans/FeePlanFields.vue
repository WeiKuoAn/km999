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
    FeePlanCourseOption,
    FeePlanFormData,
    FeePlanPeriodCoursePrice,
} from '@/lib/feePlanForm';
import {
    emptyPeriodCoursePrice,
    emptyPeriodCoursePrices,
    periodMonths,
} from '@/lib/feePlanForm';

const props = defineProps<{
    form: InertiaForm<FeePlanFormData>;
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Array<{ id: number; name: string }>;
    courses: FeePlanCourseOption[];
}>();

const selectedGradeName = computed(() => {
    if (!props.form.grade_level_id || props.form.grade_level_id === 'all') {
        return null;
    }
    return (
        props.gradeLevels.find((g) => String(g.id) === String(props.form.grade_level_id))
            ?.name ?? null
    );
});

/** 有選年級時，顯示該年級適用的課；已勾選的一律保留（避免換年級後勾不到） */
const coursesForGrade = computed(() => {
    const gradeName = selectedGradeName.value;
    const selected = new Set(props.form.course_ids.map(Number));
    if (!gradeName) {
        return props.courses;
    }

    return props.courses.filter((course) => {
        if (selected.has(course.id)) {
            return true;
        }
        if (!course.levels?.length) {
            return true;
        }
        return course.levels.includes(gradeName);
    });
});

const selectedCourses = computed(() =>
    props.form.course_ids
        .map((id) => props.courses.find((c) => c.id === Number(id)))
        .filter((c): c is FeePlanCourseOption => !!c),
);

const months = computed(() =>
    periodMonths(props.form.billing_kind, props.form.session_block_size),
);

const isPeriodBilling = computed(
    () => props.form.billing_kind !== 'month' && props.form.billing_kind !== 'custom',
);

const isCustomBilling = computed(() => props.form.billing_kind === 'custom');

const monthOptions = Array.from({ length: 12 }, (_, i) => i + 1);

const planCourseCount = computed(() => props.form.course_ids.length);
const showDoubleTier = computed(() => planCourseCount.value >= 2);
const showTripleTier = computed(() => planCourseCount.value >= 3);

const periodMonthsPreview = (period: {
    start_month: number | '';
    end_month: number | '';
}): number => {
    const start = Number(period.start_month);
    const end = Number(period.end_month);
    if (!start || !end || start < 1 || start > 12 || end < 1 || end > 12) {
        return 0;
    }
    let count = 0;
    let month = start;
    for (let i = 0; i < 12; i++) {
        count++;
        if (month === end) break;
        month = (month % 12) + 1;
    }
    return count;
};

const ensurePeriodCoursePrices = () => {
    const ids = props.form.course_ids.map(Number);
    for (const period of props.form.periods) {
        const next: Record<number, FeePlanPeriodCoursePrice> = {};
        for (const id of ids) {
            next[id] = period.course_prices?.[id] ?? emptyPeriodCoursePrice();
        }
        period.course_prices = next;
    }
};

const addPeriod = () => {
    props.form.periods.push({
        start_month: '',
        end_month: '',
        course_prices: emptyPeriodCoursePrices(props.form.course_ids.map(Number)),
    });
};


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
    if (kind === 'custom') {
        if (props.form.periods.length === 0) {
            addPeriod();
            props.form.periods[0].start_month = 7;
            props.form.periods[0].end_month = 8;
        }
        return;
    }
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

watch(
    () => [...props.form.course_ids],
    () => {
        ensurePeriodCoursePrices();
    },
    { immediate: true },
);

watch(planCourseCount, (count) => {
    if (count < 2) {
        props.form.double_monthly = '';
        props.form.triple_monthly = '';
        for (const period of props.form.periods) {
            for (const row of Object.values(period.course_prices ?? {})) {
                row.double_price = '';
                row.triple_price = '';
            }
        }
    } else if (count < 3) {
        props.form.triple_monthly = '';
        for (const period of props.form.periods) {
            for (const row of Object.values(period.course_prices ?? {})) {
                row.triple_price = '';
            }
        }
    }
});

if (props.form.material_unit === 'subject') {
    props.form.material_unit = 'term';
}
</script>

<template>
    <div
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900"
    >
        先選年級（或全年級統一價）與課目，再填收費方式。國一／二／三價格或期別不同時請分年級建；新開的班請先到
        <Link href="/courses" class="font-medium underline underline-offset-4"
            >課程管理</Link
        >
        建立。
    </div>

    <!-- ① 基本資料 -->
    <section class="space-y-4 rounded-xl border p-4">
        <div>
            <h2 class="text-sm font-semibold">① 基本資料</h2>
            <p class="text-xs text-muted-foreground">年級、學年、名稱與適用課目</p>
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
                    <option value="">請選擇年級</option>
                    <option value="all">全年級（各年級統一價／同期別）</option>
                    <option
                        v-for="g in gradeLevels"
                        :key="g.id"
                        :value="String(g.id)"
                    >
                        {{ g.name }}
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    統一價可選全年級；價格或期別不同時，請依年級各建一筆。
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
                    <option
                        v-for="y in academicYears"
                        :key="y.id"
                        :value="String(y.id)"
                    >
                        {{ y.name }}（{{ y.year_code }}）
                    </option>
                </select>
                <InputError :message="form.errors.academic_year_id" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="group_name">名稱</Label>
            <Input
                id="group_name"
                v-model="form.group_name"
                placeholder="例：國二英數理、國一國文生物"
                required
            />
            <p class="text-xs text-muted-foreground">
                顯示在收費設定列表與收款畫面。
            </p>
            <InputError :message="form.errors.group_name" />
        </div>

        <CourseCheckboxGroup
            v-model="form.course_ids"
            :courses="coursesForGrade"
            :error="form.errors.course_ids"
        />
    </section>

    <!-- ② 收費方式與金額 -->
    <section class="space-y-4 rounded-xl border p-4">
        <div>
            <h2 class="text-sm font-semibold">② 收費方式與金額</h2>
            <p class="text-xs text-muted-foreground">
                建議用「自訂期別」；按月／按季較少用，收在下方進階。
            </p>
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
                <option value="custom">自訂期別（推薦：各期依科填總價）</option>
                <option value="month">按月</option>
                <option value="quarter">按季（一次繳 3 個月）</option>
                <option value="other">其他（自訂月數 × 月價）</option>
            </select>
            <p class="text-xs text-muted-foreground">
                <template v-if="form.billing_kind === 'custom'">
                    每年固定循環；期中加入依剩餘月份折算；繳費名單會先算下一期預收。
                </template>
                <template v-else-if="form.billing_kind === 'month'">
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

        <div v-if="isCustomBilling" class="space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-medium">期別設定（每年循環）</p>
                    <p class="text-xs text-muted-foreground">
                        月份不可重疊。請依勾選科目分別填本期金額；雙／三科欄留白＝用該科單科總價。
                    </p>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addPeriod">
                    <Plus class="size-4" />
                    新增一期
                </Button>
            </div>

            <div
                v-for="(period, i) in form.periods"
                :key="i"
                class="space-y-3 rounded-md border bg-muted/30 p-3"
            >
                <div class="flex flex-wrap items-end gap-2">
                    <div class="grid gap-1.5">
                        <Label :for="`period-start-${i}`">起始月</Label>
                        <select
                            :id="`period-start-${i}`"
                            v-model.number="period.start_month"
                            class="h-9 rounded-md border px-2"
                            required
                        >
                            <option value="">選擇</option>
                            <option v-for="m in monthOptions" :key="m" :value="m">
                                {{ m }} 月
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`period-end-${i}`">結束月</Label>
                        <select
                            :id="`period-end-${i}`"
                            v-model.number="period.end_month"
                            class="h-9 rounded-md border px-2"
                            required
                        >
                            <option value="">選擇</option>
                            <option v-for="m in monthOptions" :key="m" :value="m">
                                {{ m }} 月
                            </option>
                        </select>
                    </div>
                    <p class="pb-2 text-xs text-muted-foreground">
                        <template v-if="periodMonthsPreview(period)">
                            共 {{ periodMonthsPreview(period) }} 個月
                        </template>
                        <template v-else>請選擇起訖月</template>
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive"
                        @click="form.periods.splice(i, 1)"
                    >
                        <Trash2 class="size-4" />
                        刪除
                    </Button>
                </div>

                <div v-if="selectedCourses.length === 0" class="text-sm text-muted-foreground">
                    請先在上方勾選適用科目，再為各科填寫本期金額。
                </div>
                <div v-else class="overflow-x-auto rounded-md border">
                    <table class="w-full min-w-[28rem] text-sm">
                        <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 font-medium">科目</th>
                                <th class="px-3 py-2 font-medium">單科總價（元）</th>
                                <th v-if="showDoubleTier" class="px-3 py-2 font-medium">
                                    雙科／該科總價
                                </th>
                                <th v-if="showTripleTier" class="px-3 py-2 font-medium">
                                    三科／該科總價
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="course in selectedCourses"
                                :key="`${i}-${course.id}`"
                                class="border-t"
                            >
                                <td class="px-3 py-2 font-medium">{{ course.name }}</td>
                                <td class="px-3 py-2">
                                    <Input
                                        v-if="period.course_prices[course.id]"
                                        :id="`period-${i}-course-${course.id}-single`"
                                        v-model.number="
                                            period.course_prices[course.id].total_price
                                        "
                                        type="number"
                                        min="1"
                                        class="h-9"
                                        required
                                    />
                                    <InputError
                                        :message="
                                            (form.errors as Record<string, string | undefined>)[
                                                `periods.${i}.course_prices.${course.id}.total_price`
                                            ]
                                        "
                                    />
                                </td>
                                <td v-if="showDoubleTier" class="px-3 py-2">
                                    <Input
                                        v-if="period.course_prices[course.id]"
                                        :id="`period-${i}-course-${course.id}-double`"
                                        v-model.number="
                                            period.course_prices[course.id].double_price
                                        "
                                        type="number"
                                        min="1"
                                        class="h-9"
                                        placeholder="留白＝單科價"
                                    />
                                </td>
                                <td v-if="showTripleTier" class="px-3 py-2">
                                    <Input
                                        v-if="period.course_prices[course.id]"
                                        :id="`period-${i}-course-${course.id}-triple`"
                                        v-model.number="
                                            period.course_prices[course.id].triple_price
                                        "
                                        type="number"
                                        min="1"
                                        class="h-9"
                                        placeholder="留白＝單科價"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError
                    :message="
                        (form.errors as Record<string, string | undefined>)[
                            `periods.${i}.start_month`
                        ]
                    "
                />
            </div>
            <InputError
                :message="
                    (form.errors as Record<string, string | undefined>).periods
                "
            />

            <div class="grid max-w-xs gap-2">
                <Label for="list_price_custom">參考原價／月（選填，繳費條顯示用）</Label>
                <Input
                    id="list_price_custom"
                    v-model.number="form.list_price"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.list_price" />
            </div>
        </div>

        <div v-else class="space-y-4">
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
                        isPeriodBilling ? '單科／每月優惠價' : '單科優惠價'
                    }}</Label>
                    <Input
                        id="quarter_price"
                        v-model.number="form.quarter_price"
                        type="number"
                        min="0"
                    />
                    <InputError :message="form.errors.quarter_price" />
                </div>
                <div v-if="showDoubleTier" class="grid gap-2">
                    <Label for="double_monthly">雙科／每月</Label>
                    <Input
                        id="double_monthly"
                        v-model.number="form.double_monthly"
                        type="number"
                        min="0"
                        placeholder="留白＝用單科優惠價"
                    />
                </div>
                <div v-if="showTripleTier" class="grid gap-2">
                    <Label for="triple_monthly">三科／每月</Label>
                    <Input
                        id="triple_monthly"
                        v-model.number="form.triple_monthly"
                        type="number"
                        min="0"
                        placeholder="留白＝用單科優惠價"
                    />
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
                        一期總額（單科優惠 × {{ months }}）
                    </p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        <template v-if="periodQuarterTotal !== null">
                            ${{ periodQuarterTotal.toLocaleString('zh-TW') }}
                        </template>
                        <span
                            v-else
                            class="text-sm font-normal text-muted-foreground"
                            >同原價一期總額</span
                        >
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ③ 教材費與啟用 -->
    <section class="space-y-4 rounded-xl border p-4">
        <div>
            <h2 class="text-sm font-semibold">③ 教材費與啟用</h2>
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
    </section>
</template>
