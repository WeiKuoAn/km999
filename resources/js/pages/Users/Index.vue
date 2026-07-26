<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ListPagination from '@/components/layout/ListPagination.vue';
import MobileRecordCard from '@/components/layout/MobileRecordCard.vue';
import MobileRecordField from '@/components/layout/MobileRecordField.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import TableDeleteIconButton from '@/components/table/TableDeleteIconButton.vue';
import TableEditIconLink from '@/components/table/TableEditIconLink.vue';

type User = {
    id: number;
    name: string;
    email: string;
    role: 'super_admin' | 'admin' | 'teacher';
    is_active: boolean;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{ users: Paginated<User> }>();
const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string | undefined>)?.delete);

defineOptions({
    layout: {
        breadcrumbs: [{ title: '用戶管理', href: '/users' }],
    },
});

const roleText = (role: User['role']) =>
    ({
        super_admin: '超級管理員',
        admin: '管理員',
        teacher: '老師',
    })[role] ?? role;

const destroyUser = (id: number) => {
    if (!window.confirm('確定要刪除此帳號嗎？')) return;
    router.delete(`/users/${id}`);
};
</script>

<template>
    <Head title="用戶管理" />
    <div class="page-shell">
        <PageHeader title="用戶管理">
            <template #actions>
                <Button as-child><Link href="/users/create">新增用戶</Link></Button>
            </template>
        </PageHeader>

        <InputError v-if="deleteError" :message="deleteError" />

        <div class="mobile-card-list">
            <MobileRecordCard
                v-for="u in props.users.data"
                :key="u.id"
                :title="u.name"
                :subtitle="u.email"
            >
                <template #badge>
                    <span
                        class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="u.is_active ? 'bg-emerald-600 text-white' : 'bg-muted text-muted-foreground'"
                    >
                        {{ u.is_active ? '啟用' : '停用' }}
                    </span>
                </template>
                <MobileRecordField label="角色">{{ roleText(u.role) }}</MobileRecordField>
                <template #actions>
                    <div class="mobile-card-actions">
                        <TableEditIconLink :href="`/users/${u.id}/edit`" />
                        <TableDeleteIconButton @click="destroyUser(u.id)" />
                    </div>
                </template>
            </MobileRecordCard>
            <p
                v-if="props.users.data.length === 0"
                class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground"
            >
                尚無用戶資料。
            </p>
        </div>

        <div class="desktop-table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 text-left">姓名</th>
                        <th class="py-2 text-left">電子郵件</th>
                        <th class="py-2 text-left">角色</th>
                        <th class="py-2 text-left">狀態</th>
                        <th class="py-2 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in props.users.data" :key="u.id" class="border-b">
                        <td class="py-2">{{ u.name }}</td>
                        <td class="py-2">{{ u.email }}</td>
                        <td class="py-2">{{ roleText(u.role) }}</td>
                        <td class="py-2">{{ u.is_active ? '啟用' : '停用' }}</td>
                        <td class="py-2">
                            <div class="flex items-center gap-0.5">
                                <TableEditIconLink :href="`/users/${u.id}/edit`" />
                                <TableDeleteIconButton @click="destroyUser(u.id)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :links="users.links" />
    </div>
</template>
