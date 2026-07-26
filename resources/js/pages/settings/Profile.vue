<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type Props = {
    tab: 'profile' | 'password';
    mustVerifyEmail: boolean;
    status?: string;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: '設定', href: edit() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isPasswordTab = computed(() => props.tab === 'password');
</script>

<template>
    <Head :title="isPasswordTab ? '變更密碼' : '個人資料設定'" />

    <h1 class="sr-only">{{ isPasswordTab ? '變更密碼' : '個人資料設定' }}</h1>

    <div v-if="!isPasswordTab" class="flex flex-col space-y-6">
        <Heading variant="small" title="個人資料" description="更新你的姓名與電子郵件" />

        <Form v-bind="ProfileController.update.form()" class="space-y-6" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="name">姓名</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="請輸入姓名"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">電子郵件</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="請輸入電子郵件"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    你的電子郵件尚未驗證。
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        點此重新寄送驗證信。
                    </Link>
                </p>

                <div
                    v-if="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    新的驗證連結已寄送至你的電子郵件。
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button">儲存</Button>
            </div>
        </Form>
    </div>

    <div v-else class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="變更密碼"
            description="請輸入目前密碼與新密碼；更新成功後將登出，請以新密碼重新登入。"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            :reset-on-error="['password', 'password_confirmation', 'current_password']"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">目前密碼</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="請輸入目前密碼"
                    required
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">新密碼</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="請輸入新密碼"
                    required
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">確認新密碼</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="請再次輸入新密碼"
                    required
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing" data-test="update-password-button">
                    更新密碼
                </Button>
            </div>
        </Form>
    </div>
</template>
