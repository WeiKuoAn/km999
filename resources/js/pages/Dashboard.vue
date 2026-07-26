<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import PageHeader from '@/components/layout/PageHeader.vue';

type Todo = { id: number; title: string; completed: boolean };

const props = defineProps<{
    todos: Todo[];
    todayDate: string;
    nowDisplay: string;
}>();

const newTodoTitle = useForm({ title: '' });

const addTodo = () => {
    const title = newTodoTitle.title.trim();
    if (!title) {
        return;
    }
    newTodoTitle.title = title;
    newTodoTitle.post('/todos', {
        preserveScroll: true,
        onSuccess: () => {
            newTodoTitle.reset('title');
        },
    });
};

const toggleTodo = (todo: Todo) => {
    router.patch(`/todos/${todo.id}`, { completed: !todo.completed }, { preserveScroll: true });
};

const removeTodo = (todo: Todo) => {
    if (!window.confirm(`確定要刪除待辦「${todo.title}」嗎？`)) {
        return;
    }
    router.delete(`/todos/${todo.id}`, { preserveScroll: true });
};

const openTodos = computed(() => props.todos.filter((t) => !t.completed));
const doneTodos = computed(() => props.todos.filter((t) => t.completed));

defineOptions({
    layout: {
        breadcrumbs: [{ title: '總覽', href: '/dashboard' }],
    },
});
</script>

<template>
    <Head title="總覽" />

    <div class="page-shell space-y-6">
        <PageHeader title="總覽">
            <template #below-title>
                <p class="text-sm text-muted-foreground">
                    今日 {{ todayDate }} · {{ nowDisplay }}。可在此處理待辦事項。
                </p>
            </template>
        </PageHeader>

        <section class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold">待辦事項</h2>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/calendar">
                        <CalendarDays class="mr-1.5 size-4" />
                        行事曆
                    </Link>
                </Button>
            </div>

            <form class="mt-3 flex gap-2" @submit.prevent="addTodo">
                <input
                    v-model="newTodoTitle.title"
                    type="text"
                    class="h-10 min-w-0 flex-1 rounded-md border px-3 text-sm"
                    placeholder="新增待辦…"
                    maxlength="200"
                />
                <Button type="submit" size="sm" class="shrink-0" :disabled="newTodoTitle.processing">
                    <Plus class="mr-1 size-4" />
                    新增
                </Button>
            </form>

            <ul v-if="openTodos.length > 0" class="mt-3 space-y-2">
                <li
                    v-for="todo in openTodos"
                    :key="todo.id"
                    class="flex items-center gap-2 rounded-md border border-sidebar-border/60 px-3 py-2"
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded border"
                        :checked="todo.completed"
                        @change="toggleTodo(todo)"
                    />
                    <span class="min-w-0 flex-1 text-sm">{{ todo.title }}</span>
                    <Button variant="ghost" size="icon" class="shrink-0 text-destructive" @click="removeTodo(todo)">
                        <Trash2 class="size-4" />
                    </Button>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-muted-foreground">目前沒有待辦，可在上方新增。</p>

            <details v-if="doneTodos.length > 0" class="mt-3">
                <summary class="cursor-pointer text-sm text-muted-foreground">已完成（{{ doneTodos.length }}）</summary>
                <ul class="mt-2 space-y-1">
                    <li
                        v-for="todo in doneTodos"
                        :key="todo.id"
                        class="flex items-center gap-2 rounded-md px-2 py-1 text-sm text-muted-foreground"
                    >
                        <input
                            type="checkbox"
                            class="size-4 rounded border"
                            checked
                            @change="toggleTodo(todo)"
                        />
                        <span class="min-w-0 flex-1 line-through">{{ todo.title }}</span>
                        <Button variant="ghost" size="icon" class="shrink-0" @click="removeTodo(todo)">
                            <Trash2 class="size-4" />
                        </Button>
                    </li>
                </ul>
            </details>
        </section>
    </div>
</template>
