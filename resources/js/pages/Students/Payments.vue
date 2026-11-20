<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { Button } from '@/components/ui/button';

type Period = {
    id: number;
    billing_year: number;
    billing_month: number;
    classroom_name: string;
    expected_amount: number;
    paid_amount: number;
    paid_date: string | null;
    status: string;
    settled_by_name: string;
    note: string | null;
};

type CourseGroup = {
    course_id: number | null;
    course_name: string;
    course_category_name: string;
    expected_total: number;
    paid_total: number;
    periods: Period[];
};

type GradeGroup = {
    grade_key: string;
    grade_name: string;
    grade_sort: number;
    expected_total: number;
    paid_total: number;
    courses: CourseGroup[];
};

const props = defineProps<{
    student: { id: number; name: string; grade_name?: string | null };
    groups: GradeGroup[];
}>();

const formatMoney = (n: number) => n.toLocaleString('zh-TW');

const totals = computed(() => {
    let paid = 0;
    for (const grade of props.groups) {
        paid += grade.paid_total;
    }
    return { paid };
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '學生管理', href: '/students' },
            { title: '繳費明細', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`繳費明細 - ${student.name}`" />
    <div class="page-shell">
        <PageHeader
            :title="`繳費明細（${student.name}）`"
            description="依「年級 → 科目 → 帳期」整理。已繳紀錄會永久保留，即使之後停修該課程也不會消失。"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/courses-schedule`">
                        <CalendarDays class="size-4" />
                        課程與行事曆
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/student-payments/${student.id}`"
                        >學生收款明細</Link
                    >
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/students/${student.id}/edit`">返回編輯</Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="groups.length > 0"
            class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground"
        >
            <p>
                合計已收
                <span class="ml-1 font-medium tabular-nums text-foreground">
                    ${{ formatMoney(totals.paid) }}
                </span>
            </p>
            <p v-if="student.grade_name">
                目前年級
                <span class="ml-1 font-medium text-foreground">{{
                    student.grade_name
                }}</span>
            </p>
        </div>

        <div
            v-if="groups.length === 0"
            class="rounded-xl border border-sidebar-border/70 px-4 py-12 text-center text-sm text-muted-foreground"
        >
            目前沒有繳費紀錄。
        </div>

        <div v-else class="space-y-6">
            <section
                v-for="grade in groups"
                :key="grade.grade_key"
                class="overflow-hidden rounded-xl border border-sidebar-border/70"
            >
                <header
                    class="flex flex-wrap items-baseline justify-between gap-2 border-b bg-muted/40 px-4 py-3"
                >
                    <h2 class="text-base font-semibold tracking-tight">
                        {{ grade.grade_name }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        已收
                        <span class="tabular-nums text-foreground"
                            >${{ formatMoney(grade.paid_total) }}</span
                        >
                    </p>
                </header>

                <div class="divide-y">
                    <div
                        v-for="course in grade.courses"
                        :key="`${grade.grade_key}-${course.course_id ?? course.course_name}`"
                        class="px-4 py-3"
                    >
                        <div
                            class="mb-2 flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <h3 class="text-sm font-medium">
                                <span
                                    v-if="course.course_category_name"
                                    class="text-muted-foreground"
                                >
                                    {{ course.course_category_name }} /
                                </span>
                                {{ course.course_name }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                已收
                                <span class="tabular-nums text-foreground"
                                    >${{
                                        formatMoney(course.paid_total)
                                    }}</span
                                >
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full table-fixed text-sm">
                                <colgroup>
                                    <col class="w-[18%]" />
                                    <col class="w-[18%]" />
                                    <col class="w-[28%]" />
                                    <col class="w-[36%]" />
                                </colgroup>
                                <thead>
                                    <tr
                                        class="border-b text-xs text-muted-foreground"
                                    >
                                        <th
                                            class="py-1.5 pr-4 text-left font-normal"
                                        >
                                            帳期
                                        </th>
                                        <th
                                            class="py-1.5 pr-4 text-right font-normal"
                                        >
                                            已收
                                        </th>
                                        <th
                                            class="py-1.5 pr-4 text-left font-normal"
                                        >
                                            收款日
                                        </th>
                                        <th class="py-1.5 text-left font-normal">
                                            收款人
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="period in course.periods"
                                        :key="period.id"
                                        class="border-b border-border/60 last:border-0"
                                    >
                                        <td
                                            class="py-1.5 pr-4 whitespace-nowrap tabular-nums"
                                        >
                                            {{ period.billing_year }}/{{
                                                period.billing_month
                                            }}
                                        </td>
                                        <td
                                            class="py-1.5 pr-4 text-right tabular-nums"
                                        >
                                            {{
                                                formatMoney(period.paid_amount)
                                            }}
                                        </td>
                                        <td
                                            class="py-1.5 pr-4 whitespace-nowrap"
                                        >
                                            {{ period.paid_date ?? '—' }}
                                        </td>
                                        <td class="py-1.5 whitespace-nowrap">
                                            {{
                                                period.settled_by_name || '—'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
