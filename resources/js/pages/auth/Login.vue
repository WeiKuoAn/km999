<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { registerUrl } from '@/lib/registerRoute';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: '登入系統',
        description: '請輸入電子郵件與密碼後登入',
    },
});

defineProps<{
    status?: string;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="登入" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="space-y-5">
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">電子郵件</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">密碼</Label>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="請輸入密碼"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>記住我</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 h-10 w-full text-base"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                登入
            </Button>
        </div>

        <div class="pt-1 text-center text-sm text-muted-foreground" v-if="canRegister">
            還沒有帳號？
            <TextLink :href="registerUrl()" :tabindex="5">立即註冊</TextLink>
        </div>
    </Form>
</template>
