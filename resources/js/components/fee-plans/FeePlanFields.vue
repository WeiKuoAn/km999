<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- form 是父頁的 Inertia useForm 物件，欄位由這裡直接編輯 */
import type { InertiaForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Copy, Plus, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import CourseCheckboxGroup from '@/components/CourseCheckboxGroup.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    FeePlanCourseOption,
    FeePlanFormData,
    FeePlanPeriodCoursePrice,
    FeePlanPeriodRow,
} from '@/lib/feePlanForm';
import {
    emptyCoursePeriodRow,
    emptyPeriodCoursePrice,
    emptyPeriodCoursePrices,
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
    if (props.form.per_course_periods) {
        return;
    }
    const ids = props.form.course_ids.map(Number);
    for (const period of props.form.periods) {
        const next: Record<number, FeePlanPeriodCoursePrice> = {};
        for (const id of ids) {
            next[id] = period.course_prices?.[id] ?? emptyPeriodCoursePrice();
        }
        period.course_prices = next;
        period.course_id = null;
    }
};

const cloneCoursePrices = (
    source: Record<number, FeePlanPeriodCoursePrice> | undefined,
): Record<number, FeePlanPeriodCoursePrice> => {
    const ids = props.form.course_ids.map(Number);
    const next: Record<number, FeePlanPeriodCoursePrice> = {};
    for (const id of ids) {
        const row = source?.[id];
        next[id] = row
            ? {
                  total_price: row.total_price,
                  double_price: row.double_price,
                  triple_price: row.triple_price,
              }
            : emptyPeriodCoursePrice();
    }
    return next;
};

/** 依來源期別長度，推下一期起迄（跨年循環） */
const nextPeriodMonths = (
    period: FeePlanPeriodRow,
): { start_month: number | ''; end_month: number | '' } => {
    const months = periodMonthsPreview(period);
    const end = Number(period.end_month);
    if (!months || !end) {
        return { start_month: '', end_month: '' };
    }
    const startMonth = (end % 12) + 1;
    let endMonth = startMonth;
    for (let i = 1; i < months; i++) {
        endMonth = (endMonth % 12) + 1;
    }
    return { start_month: startMonth, end_month: endMonth };
};

const emptySharedPeriod = (): FeePlanPeriodRow => ({
    course_id: null,
    start_month: '',
    end_month: '',
    course_prices: emptyPeriodCoursePrices(props.form.course_ids.map(Number)),
});

const addPeriod = () => {
    if (props.form.per_course_periods) {
        return;
    }
    props.form.periods.push(emptySharedPeriod());
};

const addCoursePeriod = (courseId: number) => {
    props.form.periods.push(emptyCoursePeriodRow(courseId));
};

/** 複製本期金額，並自動帶下一期起迄月 */
const copyPeriod = (index: number) => {
    const source = props.form.periods[index];
    if (!source) {
        return;
    }
    const months = nextPeriodMonths(source);
    if (props.form.per_course_periods) {
        const courseId = Number(source.course_id);
        const row =
            source.course_prices?.[courseId] ?? emptyPeriodCoursePrice();
        props.form.periods.splice(index + 1, 0, {
            course_id: courseId,
            start_month: months.start_month,
            end_month: months.end_month,
            total_price: source.total_price ?? row.total_price,
            double_price: source.double_price ?? row.double_price,
            triple_price: source.triple_price ?? row.triple_price,
            course_prices: {
                [courseId]: {
                    total_price: source.total_price ?? row.total_price,
                    double_price: source.double_price ?? row.double_price,
                    triple_price: source.triple_price ?? row.triple_price,
                },
            },
        });
        return;
    }
    props.form.periods.splice(index + 1, 0, {
        course_id: null,
        start_month: months.start_month,
        end_month: months.end_month,
        course_prices: cloneCoursePrices(source.course_prices),
    });
};

const periodsForCourse = (courseId: number): Array<{ period: FeePlanPeriodRow; index: number }> =>
    props.form.periods
        .map((period, index) => ({ period, index }))
        .filter(({ period }) => Number(period.course_id) === courseId);

const coursePeriodPrice = (period: FeePlanPeriodRow): FeePlanPeriodCoursePrice => {
    const courseId = Number(period.course_id);
    if (!period.course_prices?.[courseId]) {
        period.course_prices = {
            ...(period.course_prices ?? {}),
            [courseId]: {
                total_price: period.total_price ?? '',
                double_price: period.double_price ?? '',
                triple_price: period.triple_price ?? '',
            },
        };
    }
    return period.course_prices[courseId];
};

const convertToPerCoursePeriods = () => {
    const shared = props.form.periods.filter((p) => p.course_id == null);
    const source =
        shared.length > 0
            ? shared
            : [
                  {
                      start_month: 7 as number | '',
                      end_month: 8 as number | '',
                      course_prices: emptyPeriodCoursePrices(
                          props.form.course_ids.map(Number),
                      ),
                  },
              ];
    const next: FeePlanPeriodRow[] = [];
    for (const courseId of props.form.course_ids.map(Number)) {
        for (const period of source) {
            const row =
                period.course_prices?.[courseId] ?? emptyPeriodCoursePrice();
            next.push({
                course_id: courseId,
                start_month: period.start_month,
                end_month: period.end_month,
                total_price: row.total_price,
                double_price: row.double_price,
                triple_price: row.triple_price,
                course_prices: {
                    [courseId]: { ...row },
                },
            });
        }
    }
    props.form.periods = next.length > 0 ? next : [];
};

const convertToSharedPeriods = () => {
    const ids = props.form.course_ids.map(Number);
    const firstId = ids[0];
    const template = firstId
        ? props.form.periods.filter((p) => Number(p.course_id) === firstId)
        : [];
    const ranges =
        template.length > 0
            ? template
            : [
                  {
                      start_month: 7 as number | '',
                      end_month: 8 as number | '',
                      course_id: null,
                      course_prices: {},
                  } satisfies FeePlanPeriodRow,
              ];

    props.form.periods = ranges.map((range) => {
        const course_prices = emptyPeriodCoursePrices(ids);
        for (const courseId of ids) {
            const match = props.form.periods.find(
                (p) =>
                    Number(p.course_id) === courseId &&
                    Number(p.start_month) === Number(range.start_month) &&
                    Number(p.end_month) === Number(range.end_month),
            );
            if (match) {
                const row =
                    match.course_prices?.[courseId] ?? emptyPeriodCoursePrice();
                course_prices[courseId] = {
                    total_price: match.total_price ?? row.total_price,
                    double_price: match.double_price ?? row.double_price,
                    triple_price: match.triple_price ?? row.triple_price,
                };
            }
        }
        return {
            course_id: null,
            start_month: range.start_month,
            end_month: range.end_month,
            course_prices,
        };
    });
};

const onPerCourseToggle = () => {
    if (props.form.per_course_periods) {
        convertToPerCoursePeriods();
    } else {
        convertToSharedPeriods();
    }
};

// 一律自訂期別
props.form.billing_kind = 'custom';
if (props.form.periods.length === 0) {
    if (props.form.per_course_periods && props.form.course_ids.length > 0) {
        convertToPerCoursePeriods();
    } else {
        addPeriod();
        props.form.periods[0].start_month = 7;
        props.form.periods[0].end_month = 8;
    }
}

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
    (ids, prevIds) => {
        if (props.form.per_course_periods) {
            const next = new Set(ids.map(Number));
            const prev = new Set((prevIds ?? []).map(Number));
            // 移除已取消科目的期別
            props.form.periods = props.form.periods.filter(
                (p) => p.course_id == null || next.has(Number(p.course_id)),
            );
            // 新勾選的科：帶入預設一期（7–8）
            for (const id of next) {
                if (prev.has(id)) {
                    continue;
                }
                if (props.form.periods.some((p) => Number(p.course_id) === id)) {
                    continue;
                }
                const row = emptyCoursePeriodRow(id);
                row.start_month = 7;
                row.end_month = 8;
                props.form.periods.push(row);
            }
            return;
        }
        ensurePeriodCoursePrices();
    },
    { immediate: true },
);

watch(planCourseCount, (count) => {
    if (count < 2) {
        props.form.double_monthly = '';
        props.form.triple_monthly = '';
        for (const period of props.form.periods) {
            period.double_price = '';
            period.triple_price = '';
            for (const row of Object.values(period.course_prices ?? {})) {
                row.double_price = '';
                row.triple_price = '';
            }
        }
    } else if (count < 3) {
        props.form.triple_monthly = '';
        for (const period of props.form.periods) {
            period.triple_price = '';
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
        class="rounded-lg border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-sm leading-relaxed text-amber-950"
    >
        先選年級（或全年級統一價）與課目，再填收費方式。國一／二／三價格或期別不同時請分年級建；新開的班請先到
        <Link href="/courses" class="font-medium underline underline-offset-4"
            >課程管理</Link
        >
        建立。
    </div>

    <div
        class="grid items-start gap-5 xl:grid-cols-[minmax(18rem,24rem)_minmax(0,1fr)]"
    >
        <!-- 左：基本資料 -->
        <section class="space-y-4 rounded-xl border p-4 sm:p-5">
            <div class="border-b pb-3">
                <h2 class="text-sm font-semibold">① 基本資料</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    年級、學年、名稱與適用課目
                </p>
            </div>

            <div class="grid gap-3">
                <div class="grid gap-1.5">
                    <Label for="grade_level_id">年級</Label>
                    <select
                        id="grade_level_id"
                        v-model="form.grade_level_id"
                        class="h-9 w-full rounded-md border px-3"
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
                        統一價可選全年級；價格或期別不同請分年級建。
                    </p>
                    <InputError :message="form.errors.grade_level_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="academic_year_id">學年</Label>
                    <select
                        id="academic_year_id"
                        v-model="form.academic_year_id"
                        class="h-9 w-full rounded-md border px-3"
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
                <div class="grid gap-1.5">
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
            </div>

            <div class="border-t pt-4">
                <CourseCheckboxGroup
                    v-model="form.course_ids"
                    :courses="coursesForGrade"
                    :error="form.errors.course_ids"
                    embedded
                />
            </div>
        </section>

        <!-- 右：期別＋教材（寬螢幕捲動時黏在畫面上方） -->
        <div
            class="space-y-5 xl:sticky xl:top-20 xl:self-start xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto"
        >
            <section class="space-y-4 rounded-xl border p-4 sm:p-5">
                <div class="border-b pb-3">
                    <h2 class="text-sm font-semibold">② 期別與金額（自訂期別）</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        自己訂每期幾月（如 7–9、10–12），各科填「每月」單價；一期應收＝月價×月數（例：1800×3＝5400）；期中加入依剩餘月折算。
                    </p>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input
                        v-model="form.per_course_periods"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-[var(--brand-green)]"
                        @change="onPerCourseToggle"
                    />
                    <span>
                        <span class="font-medium">每科不同期</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">
                            未勾選：各科共用起迄月，再分科填月價。勾選後可讓英／數與理化等各自設期別，雙／多科優惠仍依同一收費設定報科數計算。
                        </span>
                    </span>
                </label>

                <!-- 共用期別 -->
                <div v-if="!form.per_course_periods" class="space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium">期別設定（每年循環）</p>
                            <p class="text-xs text-muted-foreground">
                                月份不可重疊。請依勾選科目填每月價；雙／多科欄留白＝用該科單科月價。
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addPeriod"
                        >
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
                                    <option
                                        v-for="m in monthOptions"
                                        :key="m"
                                        :value="m"
                                    >
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
                                    <option
                                        v-for="m in monthOptions"
                                        :key="m"
                                        :value="m"
                                    >
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
                            <div class="ml-auto flex items-center gap-1 pb-0.5">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="copyPeriod(i)"
                                >
                                    <Copy class="size-4" />
                                    複製
                                </Button>
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
                        </div>

                        <div
                            v-if="selectedCourses.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            請先在左側勾選適用科目，再為各科填寫本期金額。
                        </div>
                        <div v-else class="overflow-x-auto rounded-md border">
                            <table class="w-full min-w-[22rem] text-sm">
                                <thead
                                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-3 py-2 font-medium">科目</th>
                                        <th class="px-3 py-2 font-medium">
                                            單科／月（元）
                                        </th>
                                        <th
                                            v-if="showDoubleTier"
                                            class="px-3 py-2 font-medium"
                                        >
                                            雙科／該科月價
                                        </th>
                                        <th
                                            v-if="showTripleTier"
                                            class="px-3 py-2 font-medium"
                                        >
                                            多科／該科月價
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="course in selectedCourses"
                                        :key="`${i}-${course.id}`"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-2 font-medium">
                                            {{ course.name }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <Input
                                                v-if="
                                                    period.course_prices[course.id]
                                                "
                                                :id="`period-${i}-course-${course.id}-single`"
                                                v-model.number="
                                                    period.course_prices[course.id]
                                                        .total_price
                                                "
                                                type="number"
                                                min="1"
                                                class="h-9"
                                                required
                                            />
                                            <InputError
                                                :message="
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string | undefined
                                                        >
                                                    )[
                                                        `periods.${i}.course_prices.${course.id}.total_price`
                                                    ]
                                                "
                                            />
                                        </td>
                                        <td
                                            v-if="showDoubleTier"
                                            class="px-3 py-2"
                                        >
                                            <Input
                                                v-if="
                                                    period.course_prices[course.id]
                                                "
                                                :id="`period-${i}-course-${course.id}-double`"
                                                v-model.number="
                                                    period.course_prices[course.id]
                                                        .double_price
                                                "
                                                type="number"
                                                min="1"
                                                class="h-9"
                                                placeholder="留白＝單科價"
                                            />
                                        </td>
                                        <td
                                            v-if="showTripleTier"
                                            class="px-3 py-2"
                                        >
                                            <Input
                                                v-if="
                                                    period.course_prices[course.id]
                                                "
                                                :id="`period-${i}-course-${course.id}-triple`"
                                                v-model.number="
                                                    period.course_prices[course.id]
                                                        .triple_price
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
                                (
                                    form.errors as Record<string, string | undefined>
                                )[`periods.${i}.start_month`]
                            "
                        />
                    </div>
                </div>

                <!-- 分科期別 -->
                <div v-else class="space-y-4">
                    <p class="text-xs text-muted-foreground">
                        各科月份可不同，但同科內不可重疊。雙／多科欄留白＝用該科單科月價。
                    </p>

                    <div
                        v-if="selectedCourses.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        請先在左側勾選適用科目，再為各科設定期別。
                    </div>

                    <div
                        v-for="course in selectedCourses"
                        :key="course.id"
                        class="space-y-3 rounded-md border p-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-medium">{{ course.name }}</p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addCoursePeriod(course.id)"
                            >
                                <Plus class="size-4" />
                                新增一期
                            </Button>
                        </div>

                        <div
                            v-for="{ period, index } in periodsForCourse(course.id)"
                            :key="index"
                            class="space-y-2 rounded-md border bg-muted/30 p-3"
                        >
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="grid gap-1.5">
                                    <Label :for="`pc-start-${index}`">起始月</Label>
                                    <select
                                        :id="`pc-start-${index}`"
                                        v-model.number="period.start_month"
                                        class="h-9 rounded-md border px-2"
                                        required
                                    >
                                        <option value="">選擇</option>
                                        <option
                                            v-for="m in monthOptions"
                                            :key="m"
                                            :value="m"
                                        >
                                            {{ m }} 月
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label :for="`pc-end-${index}`">結束月</Label>
                                    <select
                                        :id="`pc-end-${index}`"
                                        v-model.number="period.end_month"
                                        class="h-9 rounded-md border px-2"
                                        required
                                    >
                                        <option value="">選擇</option>
                                        <option
                                            v-for="m in monthOptions"
                                            :key="m"
                                            :value="m"
                                        >
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
                                <div class="ml-auto flex items-center gap-1 pb-0.5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="copyPeriod(index)"
                                    >
                                        <Copy class="size-4" />
                                        複製
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="form.periods.splice(index, 1)"
                                    >
                                        <Trash2 class="size-4" />
                                        刪除
                                    </Button>
                                </div>
                            </div>

                            <div
                                class="grid gap-2 sm:grid-cols-2"
                                :class="showTripleTier ? 'lg:grid-cols-3' : ''"
                            >
                                <div class="grid gap-1.5">
                                    <Label :for="`pc-single-${index}`"
                                        >單科／月（元）</Label
                                    >
                                    <Input
                                        :id="`pc-single-${index}`"
                                        v-model.number="
                                            coursePeriodPrice(period).total_price
                                        "
                                        type="number"
                                        min="1"
                                        class="h-9"
                                        required
                                    />
                                    <InputError
                                        :message="
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string | undefined
                                                >
                                            )[`periods.${index}.total_price`]
                                        "
                                    />
                                </div>
                                <div v-if="showDoubleTier" class="grid gap-1.5">
                                    <Label :for="`pc-double-${index}`"
                                        >雙科／該科月價</Label
                                    >
                                    <Input
                                        :id="`pc-double-${index}`"
                                        v-model.number="
                                            coursePeriodPrice(period).double_price
                                        "
                                        type="number"
                                        min="1"
                                        class="h-9"
                                        placeholder="留白＝單科價"
                                    />
                                </div>
                                <div v-if="showTripleTier" class="grid gap-1.5">
                                    <Label :for="`pc-triple-${index}`"
                                        >多科／該科月價</Label
                                    >
                                    <Input
                                        :id="`pc-triple-${index}`"
                                        v-model.number="
                                            coursePeriodPrice(period).triple_price
                                        "
                                        type="number"
                                        min="1"
                                        class="h-9"
                                        placeholder="留白＝單科價"
                                    />
                                </div>
                            </div>
                            <InputError
                                :message="
                                    (
                                        form.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    )[`periods.${index}.start_month`]
                                "
                            />
                        </div>
                    </div>
                </div>

                <InputError
                    :message="
                        (form.errors as Record<string, string | undefined>).periods
                    "
                />
            </section>

            <section class="space-y-4 rounded-xl border p-4 sm:p-5">
                <div class="border-b pb-3">
                    <h2 class="text-sm font-semibold">③ 教材費與啟用</h2>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
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
                    <div class="grid gap-1.5">
                        <Label for="material_fee">{{
                            form.material_unit === 'class_day'
                                ? '每日耗材費'
                                : '教材年費'
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
        </div>
    </div>
</template>
