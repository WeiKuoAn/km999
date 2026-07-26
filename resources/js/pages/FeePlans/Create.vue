<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import CourseCheckboxGroup from '@/components/CourseCheckboxGroup.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = { id: number; name: string };
type PricingGroupOption = { value: string; label: string };
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
    academicYears: Array<{ id: number; year_code: string; name: string }>;
    gradeLevels: Option[];
    pricingGroups: PricingGroupOption[];
    courses: CourseOption[];
    defaultAcademicYearId: number | null;
}>();

const form = useForm({
    academic_year_id: props.defaultAcademicYearId
        ? String(props.defaultAcademicYearId)
        : '',
    grade_level_id: '',
    course_ids: [] as number[],
    group_name: '',
    pricing_group: 'core',
    unit: 'month' as 'month' | 'session_block',
    session_block_size: 4 as number | '',
    list_price: 0,
    quarter_price: '' as number | '',
    quarter_single_price: '' as number | '',
    quarter_double_price: '' as number | '',
    material_fee: 0,
    material_unit: 'term' as 'term' | 'subject' | 'class_day',
    sort_order: 0,
    is_active: true,
    use_single_double: true,
});

const isSessionBlock = computed(() => form.unit === 'session_block');

watch(
    () => form.unit,
    (unit) => {
        if (unit === 'session_block') {
            form.use_single_double = false;
            form.quarter_single_price = '';
            form.quarter_double_price = '';

            if (!form.session_block_size) {
                form.session_block_size = 4;
            }
        } else {
            form.session_block_size = '';
        }
    },
);

watch(
    () => form.use_single_double,
    (use) => {
        if (use) {
            form.quarter_price = '';
        } else {
            form.quarter_single_price = '';
            form.quarter_double_price = '';
        }
    },
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '收費標準', href: '/fee-plans' },
            { title: '新增', href: '/fee-plans/create' },
        ],
    },
});

const emptyToNull = (v: number | string | undefined) =>
    v === '' || v === undefined || Number.isNaN(Number(v)) ? null : Number(v);

const submit = () =>
    form
        .transform((data) => ({
            academic_year_id:
                data.academic_year_id === ''
                    ? null
                    : Number(data.academic_year_id),
            grade_level_id: Number(data.grade_level_id),
            course_ids: data.course_ids.map(Number),
            group_name: data.group_name,
            pricing_group: data.pricing_group,
            unit: data.unit,
            session_block_size:
                data.unit === 'session_block'
                    ? Number(data.session_block_size || 4)
                    : null,
            list_price: Number(data.list_price || 0),
            quarter_price: data.use_single_double
                ? null
                : emptyToNull(data.quarter_price),
            quarter_single_price: data.use_single_double
                ? emptyToNull(data.quarter_single_price)
                : null,
            quarter_double_price: data.use_single_double
                ? emptyToNull(data.quarter_double_price)
                : null,
            material_fee: Number(data.material_fee || 0),
            material_unit: data.material_unit,
            sort_order: Number(data.sort_order || 0),
            is_active: data.is_active,
        }))
        .post('/fee-plans');
</script>

<template>
    <Head title="新增收費標準" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增收費標準</h1>
        <form class="space-y-4 rounded-xl border p-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="academic_year_id">學年</Label>
                    <select
                        id="academic_year_id"
                        v-model="form.academic_year_id"
                        class="h-9 rounded-md border px-3"
                    >
                        <option value="">不指定</option>
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
                <div class="grid gap-2">
                    <Label for="grade_level_id">年級</Label>
                    <select
                        id="grade_level_id"
                        v-model="form.grade_level_id"
                        class="h-9 rounded-md border px-3"
                        required
                    >
                        <option value="">請選擇</option>
                        <option
                            v-for="g in gradeLevels"
                            :key="g.id"
                            :value="String(g.id)"
                        >
                            {{ g.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.grade_level_id" />
                </div>
            </div>

            <CourseCheckboxGroup
                v-model="form.course_ids"
                :courses="courses"
                :error="form.errors.course_ids"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="group_name">科目組名稱</Label>
                    <Input
                        id="group_name"
                        v-model="form.group_name"
                        placeholder="例：英／數"
                        required
                    />
                    <InputError :message="form.errors.group_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="pricing_group">收費組別</Label>
                    <select
                        id="pricing_group"
                        v-model="form.pricing_group"
                        class="h-9 rounded-md border px-3"
                    >
                        <option
                            v-for="g in pricingGroups"
                            :key="g.value"
                            :value="g.value"
                        >
                            {{ g.label }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        課程需選擇相同組別，報名時才會對應此價目。
                    </p>
                    <InputError :message="form.errors.pricing_group" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="unit">計費單位</Label>
                    <select
                        id="unit"
                        v-model="form.unit"
                        class="h-9 rounded-md border px-3"
                    >
                        <option value="month">按月</option>
                        <option value="session_block">堂塊（如 4 堂）</option>
                    </select>
                </div>
                <div v-if="isSessionBlock" class="grid gap-2">
                    <Label for="session_block_size">堂數</Label>
                    <Input
                        id="session_block_size"
                        v-model.number="form.session_block_size"
                        type="number"
                        min="1"
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="list_price">定價</Label>
                <Input
                    id="list_price"
                    v-model.number="form.list_price"
                    type="number"
                    min="0"
                    required
                />
                <InputError :message="form.errors.list_price" />
            </div>

            <div class="space-y-3 rounded-lg border p-3">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.use_single_double"
                        type="checkbox"
                        class="size-4 accent-[var(--brand-green)]"
                    />
                    季繳區分單科／雙科價
                </label>
                <div
                    v-if="form.use_single_double"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <Label for="quarter_single_price">季繳單科價</Label>
                        <Input
                            id="quarter_single_price"
                            v-model.number="form.quarter_single_price"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quarter_double_price">季繳雙科價</Label>
                        <Input
                            id="quarter_double_price"
                            v-model.number="form.quarter_double_price"
                            type="number"
                            min="0"
                        />
                    </div>
                </div>
                <div v-else class="grid gap-2">
                    <Label for="quarter_price">季繳價</Label>
                    <Input
                        id="quarter_price"
                        v-model.number="form.quarter_price"
                        type="number"
                        min="0"
                    />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="material_fee">{{
                        form.material_unit === 'class_day'
                            ? '耗材費（每日）'
                            : '教材／耗材費'
                    }}</Label>
                    <Input
                        id="material_fee"
                        v-model.number="form.material_fee"
                        type="number"
                        min="0"
                    />
                    <p
                        v-if="form.material_unit === 'class_day'"
                        class="text-xs text-muted-foreground"
                    >
                        例：一天
                        200。系統會依課程上課日計算該月堂數（月中入班只算剩餘日）。
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="material_unit">教材／耗材單位</Label>
                    <select
                        id="material_unit"
                        v-model="form.material_unit"
                        class="h-9 rounded-md border px-3"
                    >
                        <option value="term">學期一次</option>
                        <option value="subject">每科一次</option>
                        <option value="class_day">每日／每堂（耗材）</option>
                    </select>
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

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child
                    ><Link href="/fee-plans">返回</Link></Button
                >
            </div>
        </form>
    </div>
</template>
