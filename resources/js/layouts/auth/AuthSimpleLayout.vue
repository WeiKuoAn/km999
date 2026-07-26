<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center bg-gradient-to-b from-slate-50 to-white p-6 md:p-10"
    >
        <div class="w-full max-w-md">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col items-center gap-3">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <p class="text-xl font-semibold tracking-wide text-primary md:text-2xl">
                            補教點名系統
                        </p>
                        <h1 class="text-2xl font-semibold">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
