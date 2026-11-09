<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/layout/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    student: {
        id: number;
        student_code: string | null;
        name: string;
        grade_name: string | null;
    };
    period: {
        from: string;
        to: string;
        period_label: string;
        pay_cycle: string;
        course_names: string[];
        expected_total: number;
        receipt_no: string | null;
        paid_date: string | null;
    };
}>();

const form = useForm({
    from: props.period.from,
    to: props.period.to,
    receipt_no: props.period.receipt_no ?? '',
    paid_date: props.period.paid_date ?? '',
});

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const payCycleLabel =
    props.period.pay_cycle === 'monthly'
        ? '月繳'
        : props.period.pay_cycle === 'annual'
          ? '年繳'
          : '季繳';

const detailHref = `/student-payments/${props.student.id}?from_year=${props.period.from.split('-')[0]}&from_month=${props.period.from.split('-')[1]}&to_year=${props.period.to.split('-')[0]}&to_month=${props.period.to.split('-')[1]}`;

const submit = () => {
    form.patch(`/student-payments/${props.student.id}/period-meta`, {
        preserveScroll: true,
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生收款', href: '/student-payments' },
            { title: '收據輸入', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="收據輸入" />
    <div class="page-shell mx-auto w-full max-w-2xl">
        <PageHeader
            :title="`收據輸入｜${student.name}`"
            description="已收款金額已鎖定。收費設定之後怎麼改，都不會改到這筆帳單；此頁只能改收據與收款日。"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="detailHref">看明細</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/student-payments">返回列表</Link>
                </Button>
            </template>
        </PageHeader>

        <div
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
        >
            若金額或科目收錯，請刪除整期後依最新收費設定重新收款（刪除後無法還原）。
        </div>

        <div class="space-y-4 rounded-xl border p-4 sm:p-5">
            <div class="grid gap-1 text-sm">
                <p>
                    <span class="text-muted-foreground">學生</span>
                    {{ student.student_code ?? '—' }} {{ student.name }}
                    <span v-if="student.grade_name" class="text-muted-foreground">
                        （{{ student.grade_name }}）
                    </span>
                </p>
                <p>
                    <span class="text-muted-foreground">帳期</span>
                    {{ period.period_label }}｜{{ payCycleLabel }}
                </p>
                <p>
                    <span class="text-muted-foreground">科目</span>
                    {{ period.course_names.join('、') || '—' }}
                </p>
                <p class="text-base font-semibold tabular-nums">
                    <span class="text-sm font-normal text-muted-foreground"
                        >總金額（鎖定）</span
                    >
                    {{ formatMoney(period.expected_total) }}
                </p>
            </div>

            <form class="grid gap-4 border-t pt-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="receipt_no">收據號碼</Label>
                    <Input
                        id="receipt_no"
                        v-model="form.receipt_no"
                        maxlength="20"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.receipt_no" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="paid_date">收款日</Label>
                    <Input
                        id="paid_date"
                        v-model="form.paid_date"
                        type="date"
                    />
                    <InputError :message="form.errors.paid_date" />
                </div>
                <InputError :message="form.errors.from" />
                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing"
                        >儲存</Button
                    >
                    <Button variant="outline" as-child>
                        <Link href="/student-payments">取消</Link>
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
