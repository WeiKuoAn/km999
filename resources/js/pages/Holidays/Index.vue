<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type HolidayRow = {
    id: number;
    date: string;
    name: string;
    is_custom: boolean;
};

const props = defineProps<{
    holidays: HolidayRow[];
}>();

const page = usePage();
const successMessage = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const canManage = page.props.auth.user.role === 'super_admin' || page.props.auth.user.role === 'admin';

const form = useForm({
    date: '',
    name: '',
});

const importFile = ref<File | null>(null);
const importing = ref(false);
const importError = ref('');

const submitManual = () => {
    form.post('/holidays', {
        preserveScroll: true,
        onSuccess: () => form.reset('date', 'name'),
    });
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    importFile.value = input.files?.[0] ?? null;
    importError.value = '';
};

const submitImport = () => {
    if (!importFile.value) {
        importError.value = '請先選擇 CSV 檔案。';
        return;
    }
    importing.value = true;
    importError.value = '';
    const data = new FormData();
    data.append('file', importFile.value);
    router.post('/holidays/import', data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importing.value = false;
        },
        onSuccess: () => {
            importFile.value = null;
            const input = document.getElementById('holiday_csv') as HTMLInputElement | null;
            if (input) {
                input.value = '';
            }
        },
        onError: (errors) => {
            importError.value = (errors.file as string) || '匯入失敗，請確認檔案格式。';
        },
    });
};

const destroyHoliday = (id: number) => {
    if (!window.confirm('確定要刪除此假日嗎？')) {
        return;
    }
    router.delete(`/holidays/${id}`, { preserveScroll: true });
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: '假日設定', href: '/holidays' }],
    },
});
</script>

<template>
    <Head title="假日設定" />
    <div class="page-shell mx-auto w-full max-w-4xl">
        <PageHeader
            title="假日設定"
            description="上傳官方辦公日曆表 CSV（略過例假日），或手動新增暑休／寒休等自訂連假。收款預選堂次會自動排除這些日期。"
        />

        <div
            v-if="successMessage"
            class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
        >
            {{ successMessage }}
        </div>

        <div v-if="canManage" class="mb-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <CalendarOff class="size-4 text-primary" />
                    上傳官方 CSV
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    使用政府資料開放平台「Google 行事曆專用」CSV；匯入時會略過「例假日」（一般週末），只存國定假日／補假。
                </p>
                <div class="mt-3 grid gap-2">
                    <Label for="holiday_csv">CSV 檔案</Label>
                    <Input id="holiday_csv" type="file" accept=".csv,text/csv" @change="onFileChange" />
                    <p v-if="importError" class="text-xs text-destructive">{{ importError }}</p>
                    <Button type="button" :disabled="importing || !importFile" @click="submitImport">
                        {{ importing ? '匯入中…' : '開始匯入' }}
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4">
                <h2 class="text-base font-semibold">手動新增假日</h2>
                <p class="mt-1 text-xs text-muted-foreground">暑休、寒休、段考停課等自訂連假。</p>
                <form class="mt-3 grid gap-3" @submit.prevent="submitManual">
                    <div class="grid gap-2">
                        <Label for="date">日期</Label>
                        <Input id="date" v-model="form.date" type="date" required />
                        <InputError :message="form.errors.date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">名稱</Label>
                        <Input id="name" v-model="form.name" placeholder="例：暑假連假" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <Button type="submit" :disabled="form.processing">新增</Button>
                </form>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 bg-card p-4">
            <h2 class="text-base font-semibold">假日清單</h2>
            <p v-if="holidays.length === 0" class="mt-3 text-sm text-muted-foreground">
                尚無假日資料。請先上傳官方 CSV 或手動新增。
            </p>
            <div v-else class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 text-left">日期</th>
                            <th class="py-2 text-left">名稱</th>
                            <th class="py-2 text-left">來源</th>
                            <th v-if="canManage" class="w-[72px] py-2 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="h in holidays" :key="h.id" class="border-b">
                            <td class="py-2 tabular-nums">{{ h.date }}</td>
                            <td class="py-2">{{ h.name }}</td>
                            <td class="py-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="
                                        h.is_custom
                                            ? 'bg-amber-50 text-amber-800'
                                            : 'bg-slate-100 text-slate-700'
                                    "
                                >
                                    {{ h.is_custom ? '自訂' : '官方' }}
                                </span>
                            </td>
                            <td v-if="canManage" class="py-2">
                                <TableDeleteIconButton @click="destroyHoliday(h.id)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
