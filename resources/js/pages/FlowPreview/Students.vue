<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import FlowNav from '@/components/flow/FlowNav.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    nextCode: string;
    batchPreview: string[];
    sampleStudents: Array<{
        code: string;
        name: string;
        grade: string;
        status: string;
        school: string;
    }>;
}>();

const parentPhones = ref([{ label: '父', phone: '' }, { label: '母', phone: '' }]);

const addParentPhone = () => {
    parentPhones.value.push({ label: '其他', phone: '' });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '營運流程預覽', href: '/flow-preview' },
            { title: '學生建檔', href: '/flow-preview/students' },
        ],
    },
});
</script>

<template>
    <Head title="學生建檔／學號" />

    <div class="page-shell mx-auto w-full max-w-5xl">
        <FlowNav
            :next="{ href: '/flow-preview/trial', label: '下一步：試聽' }"
        />

        <PageHeader
            title="學生建檔／學號"
            description="欄位皆選填。學號＝學年度３碼＋年級２碼＋流水３碼（例 11501001）。"
        />

        <div class="grid gap-4 lg:grid-cols-2">
            <form class="space-y-3 rounded-xl border border-sidebar-border/70 p-4" @submit.prevent>
                <h2 class="text-base font-semibold">基本資料</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <Label>姓名</Label>
                        <Input placeholder="選填" />
                    </div>
                    <div class="grid gap-1">
                        <Label>性別</Label>
                        <select class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">未填</option>
                            <option>男</option>
                            <option>女</option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <Label>學生電話</Label>
                        <Input placeholder="選填" />
                    </div>
                    <div class="grid gap-1">
                        <Label>身分證</Label>
                        <Input placeholder="選填" />
                    </div>
                </div>
                <div class="grid gap-1">
                    <Label>地址</Label>
                    <Input placeholder="選填" />
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <Label>畢業學校</Label>
                        <Input placeholder="選填" />
                    </div>
                    <div class="grid gap-1">
                        <Label>現讀學校</Label>
                        <Input placeholder="選填" />
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="grid gap-1">
                        <Label>學年度</Label>
                        <Input value="115" readonly class="bg-muted/40" />
                    </div>
                    <div class="grid gap-1">
                        <Label>年級</Label>
                        <select class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option>國一</option>
                            <option>國二</option>
                            <option>國三</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label>班別</Label>
                        <Input placeholder="如 狀元A" />
                    </div>
                </div>

                <div class="space-y-2 border-t pt-3">
                    <div class="flex items-center justify-between">
                        <Label>家長電話（可多筆）</Label>
                        <Button type="button" variant="outline" size="sm" @click="addParentPhone">新增</Button>
                    </div>
                    <div v-for="(p, i) in parentPhones" :key="i" class="grid grid-cols-[5rem_1fr] gap-2">
                        <Input v-model="p.label" />
                        <Input v-model="p.phone" placeholder="電話" />
                    </div>
                </div>
            </form>

            <div class="space-y-4">
                <div class="space-y-3 rounded-xl border border-sidebar-border/70 p-4">
                    <h2 class="text-base font-semibold">學號產生</h2>
                    <p class="text-sm text-muted-foreground">下一個單筆：<span class="font-semibold text-primary">{{ nextCode }}</span></p>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button">單筆產生</Button>
                        <Button type="button" variant="outline">批次產生 3 碼</Button>
                    </div>
                    <div class="rounded-md bg-muted/50 px-3 py-2 text-sm">
                        <p class="text-muted-foreground">批次預覽</p>
                        <p class="mt-1 font-mono">{{ batchPreview.join('、') }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th class="px-3 py-2 text-left">學號</th>
                                <th class="px-3 py-2 text-left">姓名</th>
                                <th class="px-3 py-2 text-left">年級</th>
                                <th class="px-3 py-2 text-left">狀態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in sampleStudents" :key="s.code" class="border-b">
                                <td class="px-3 py-2 font-mono">{{ s.code }}</td>
                                <td class="px-3 py-2">{{ s.name }}</td>
                                <td class="px-3 py-2">{{ s.grade }}</td>
                                <td class="px-3 py-2">{{ s.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
