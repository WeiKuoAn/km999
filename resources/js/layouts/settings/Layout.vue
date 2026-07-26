<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';

const page = usePage();

const settingsTab = computed<'profile' | 'password'>(() => {
    const url = new URL(page.url, 'http://localhost');

    return url.searchParams.get('tab') === 'password' ? 'password' : 'profile';
});

const profileHref = editProfile();
const passwordHref = editProfile({ query: { tab: 'password' } });
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="設定" description="管理你的個人資料與帳號設定" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0" aria-label="設定">
                    <Button
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': settingsTab === 'profile' }]"
                        as-child
                    >
                        <Link :href="profileHref">個人資料</Link>
                    </Button>
                    <Button
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': settingsTab === 'password' }]"
                        as-child
                    >
                        <Link :href="passwordHref">變更密碼</Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
