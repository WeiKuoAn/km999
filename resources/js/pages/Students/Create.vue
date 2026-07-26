<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import TaiwanAddressPicker from '@/components/TaiwanAddressPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    defaultParentPhones,
    emptyToNull,
    normalizeParentPhones,
} from '@/lib/studentForm';

type YearOption = { id: number; year_code: string; name: string; is_current: boolean };
type GradeOption = { id: number; name: string; code: number; code_padded: string };

const props = defineProps<{
    academicYears: YearOption[];
    gradeLevels: GradeOption[];
    defaultAcademicYearId: number | null;
}>();

const form = useForm({
    name: '',
    phone: '',
    parent_phones: defaultParentPhones(),
    graduate_school: '',
    current_school: '',
    class_name: '',
    id_number: '',
    address_city: '',
    address_district: '',
    address_zip: '',
    address_detail: '',
    gender: '',
    status: 'active',
    note: '',
    academic_year_id: props.defaultAcademicYearId ? String(props.defaultAcademicYearId) : '',
    grade_level_id: '',
});

const previewCode = ref('');
const previewLoading = ref(false);

const refreshPreview = async () => {
    if (!form.academic_year_id || !form.grade_level_id) {
        previewCode.value = '';
        return;
    }
    previewLoading.value = true;
    try {
        const params = new URLSearchParams({
            academic_year_id: form.academic_year_id,
            grade_level_id: form.grade_level_id,
        });
        const res = await fetch(`/students/next-code?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            previewCode.value = '';
            return;
        }
        const data = (await res.json()) as { student_code?: string };
        previewCode.value = data.student_code ?? '';
    } catch {
        previewCode.value = '';
    } finally {
        previewLoading.value = false;
    }
};

watch(
    () => [form.academic_year_id, form.grade_level_id],
    () => {
        void refreshPreview();
    },
    { immediate: true },
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生管理', href: '/students' },
            { title: '新增', href: '/students/create' },
        ],
    },
});

const addParentPhone = () => {
    form.parent_phones.push({ title: '', phone: '' });
};

const removeParentPhone = (index: number) => {
    if (form.parent_phones.length <= 1) {
        return;
    }
    form.parent_phones.splice(index, 1);
};

const submit = () =>
    form
        .transform((data) => ({
            ...data,
            academic_year_id: data.academic_year_id === '' ? null : Number(data.academic_year_id),
            grade_level_id: data.grade_level_id === '' ? null : Number(data.grade_level_id),
            phone: emptyToNull(data.phone),
            parent_phones: normalizeParentPhones(data.parent_phones),
            graduate_school: emptyToNull(data.graduate_school),
            current_school: emptyToNull(data.current_school),
            class_name: emptyToNull(data.class_name),
            id_number: emptyToNull(data.id_number),
            address_city: emptyToNull(data.address_city),
            address_district: emptyToNull(data.address_district),
            address_zip: emptyToNull(data.address_zip),
            address_detail: emptyToNull(data.address_detail),
            gender: emptyToNull(data.gender),
            note: emptyToNull(data.note),
        }))
        .post('/students');
</script>

<template>
    <Head title="新增學生" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <h1 class="text-xl font-semibold">新增學生</h1>
        <form class="space-y-5 rounded-xl border p-4 sm:p-5" @submit.prevent="submit">
            <div class="grid gap-2 rounded-lg border border-primary/20 bg-accent/40 p-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="academic_year_id">學年</Label>
                    <select
                        id="academic_year_id"
                        v-model="form.academic_year_id"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option value="">請選擇</option>
                        <option v-for="y in academicYears" :key="y.id" :value="String(y.id)">
                            {{ y.name }}（{{ y.year_code }}）
                        </option>
                    </select>
                    <InputError :message="form.errors.academic_year_id" />
                </div>
                <div class="grid gap-1">
                    <Label for="grade_level_id">年級</Label>
                    <select
                        id="grade_level_id"
                        v-model="form.grade_level_id"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option value="">請選擇</option>
                        <option v-for="g in gradeLevels" :key="g.id" :value="String(g.id)">
                            {{ g.name }}（{{ g.code_padded }}）
                        </option>
                    </select>
                    <InputError :message="form.errors.grade_level_id" />
                </div>
                <div class="sm:col-span-2">
                    <p class="text-sm">
                        預計學號：
                        <span class="font-mono font-semibold text-primary">
                            {{ previewLoading ? '計算中…' : previewCode || '—' }}
                        </span>
                    </p>
                    <p class="text-xs text-muted-foreground">格式：年度＋年級兩碼＋流水三碼（例 11507001）</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">姓名</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="gender">性別</Label>
                    <select id="gender" v-model="form.gender" class="h-9 rounded-md border bg-background px-3">
                        <option value="">未填</option>
                        <option value="男">男</option>
                        <option value="女">女</option>
                    </select>
                    <InputError :message="form.errors.gender" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="phone">學生電話</Label>
                    <Input id="phone" v-model="form.phone" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="id_number">身分證</Label>
                    <Input id="id_number" v-model="form.id_number" />
                    <InputError :message="form.errors.id_number" />
                </div>
            </div>

            <div class="space-y-3 rounded-lg border p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Label>家長電話</Label>
                    <Button type="button" variant="outline" size="sm" @click="addParentPhone">
                        <Plus class="mr-1 size-4" />
                        新增
                    </Button>
                </div>
                <div
                    v-for="(parent, index) in form.parent_phones"
                    :key="index"
                    class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end"
                >
                    <div class="grid gap-1">
                        <Label :for="`parent_title_${index}`" class="text-xs text-muted-foreground">稱謂</Label>
                        <Input
                            :id="`parent_title_${index}`"
                            v-model="parent.title"
                            placeholder="例：爸爸"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label :for="`parent_phone_${index}`" class="text-xs text-muted-foreground">電話</Label>
                        <Input :id="`parent_phone_${index}`" v-model="parent.phone" />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="shrink-0 self-end"
                        :disabled="form.parent_phones.length <= 1"
                        @click="removeParentPhone(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <InputError :message="form.errors['parent_phones']" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="current_school">現讀學校</Label>
                    <Input id="current_school" v-model="form.current_school" />
                    <InputError :message="form.errors.current_school" />
                </div>
                <div class="grid gap-2">
                    <Label for="class_name">班級</Label>
                    <Input id="class_name" v-model="form.class_name" placeholder="例：忠班" />
                    <InputError :message="form.errors.class_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="graduate_school">畢業學校</Label>
                <Input id="graduate_school" v-model="form.graduate_school" />
                <InputError :message="form.errors.graduate_school" />
            </div>

            <div class="grid gap-2">
                <Label>地址</Label>
                <TaiwanAddressPicker
                    v-model:city="form.address_city"
                    v-model:district="form.address_district"
                    v-model:zip="form.address_zip"
                    v-model:detail="form.address_detail"
                />
                <InputError :message="form.errors.address_detail" />
            </div>

            <div class="grid gap-2">
                <Label for="note">備註</Label>
                <textarea id="note" v-model="form.note" class="min-h-24 rounded-md border p-3" />
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">儲存</Button>
                <Button variant="outline" as-child><Link href="/students">返回</Link></Button>
            </div>
        </form>
    </div>
</template>
