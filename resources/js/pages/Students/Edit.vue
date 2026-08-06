<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, Plus, Receipt, Trash2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TaiwanAddressPicker from '@/components/TaiwanAddressPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    emptyToNull,
    normalizeParentPhones,
    type ParentPhone,
} from '@/lib/studentForm';

const props = defineProps<{
    student: {
        id: number;
        student_code: string | null;
        name: string;
        phone: string | null;
        parent_phones: ParentPhone[];
        graduate_school: string | null;
        current_school: string | null;
        class_name: string | null;
        id_number: string | null;
        address_city: string | null;
        address_district: string | null;
        address_zip: string | null;
        address_detail: string | null;
        gender: string | null;
        status: 'active' | 'paused';
        note: string | null;
        academic_year_id: number | null;
        grade_level_id: number | null;
    };
    academicYears: Array<{ id: number; year_code: string; name: string; is_current: boolean }>;
    gradeLevels: Array<{ id: number; name: string; code: number; code_padded: string }>;
}>();

const form = useForm({
    name: props.student.name,
    phone: props.student.phone ?? '',
    parent_phones: props.student.parent_phones.map((p) => ({ ...p })),
    graduate_school: props.student.graduate_school ?? '',
    current_school: props.student.current_school ?? '',
    class_name: props.student.class_name ?? '',
    id_number: props.student.id_number ?? '',
    address_city: props.student.address_city ?? '',
    address_district: props.student.address_district ?? '',
    address_zip: props.student.address_zip ?? '',
    address_detail: props.student.address_detail ?? '',
    gender: props.student.gender ?? '',
    status: props.student.status,
    note: props.student.note ?? '',
    academic_year_id: props.student.academic_year_id ? String(props.student.academic_year_id) : '',
    grade_level_id: props.student.grade_level_id ? String(props.student.grade_level_id) : '',
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生管理', href: '/students' },
            { title: '編輯', href: '#' },
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
        .put(`/students/${props.student.id}`, { preserveScroll: true });
</script>

<template>
    <Head title="編輯學生" />
    <div class="page-shell mx-auto w-full max-w-3xl">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition ease-in duration-200"
            leave-to-class="opacity-0"
        >
            <div
                v-if="form.recentlySuccessful"
                class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
            >
                已更新成功！
            </div>
        </Transition>
        <PageHeader title="編輯學生" :description="student.name">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/courses-schedule`">
                        <CalendarDays class="size-4" />
                        課程與行事曆
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/payments`">
                        <Receipt class="size-4" />
                        繳費明細
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <form class="space-y-5 rounded-xl border p-4 sm:p-5" @submit.prevent="submit">
            <div class="grid gap-2 rounded-lg border border-primary/20 bg-accent/40 p-3 sm:grid-cols-2">
                <div class="grid gap-1 sm:col-span-2">
                    <Label>學號</Label>
                    <p class="font-mono text-base font-semibold text-primary">
                        {{ student.student_code || '尚未產生（儲存學年／年級後可補發）' }}
                    </p>
                </div>
                <div class="grid gap-1">
                    <Label for="academic_year_id">學年</Label>
                    <select
                        id="academic_year_id"
                        v-model="form.academic_year_id"
                        class="h-9 rounded-md border bg-background px-3"
                        :disabled="!!student.student_code"
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
                        :disabled="!!student.student_code"
                    >
                        <option value="">請選擇</option>
                        <option v-for="g in gradeLevels" :key="g.id" :value="String(g.id)">
                            {{ g.name }}（{{ g.code_padded }}）
                        </option>
                    </select>
                    <InputError :message="form.errors.grade_level_id" />
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
                <Button type="submit" :disabled="form.processing">更新</Button>
                <Button variant="outline" as-child><Link href="/students">返回</Link></Button>
            </div>
        </form>
    </div>
</template>
