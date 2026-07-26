<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    prev?: { href: string; label: string } | null;
    next?: { href: string; label: string } | null;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-sidebar-border/70 bg-accent/40 px-3 py-2">
        <div>
            <Button v-if="prev" variant="outline" size="sm" as-child>
                <Link :href="prev.href" class="inline-flex items-center gap-1">
                    <ChevronLeft class="size-4" />
                    {{ prev.label }}
                </Link>
            </Button>
            <Button v-else variant="ghost" size="sm" as-child>
                <Link href="/flow-preview">流程總覽</Link>
            </Button>
        </div>
        <p class="text-xs text-muted-foreground">流程預覽｜假資料｜尚未寫入資料庫</p>
        <div>
            <Button v-if="next" size="sm" as-child>
                <Link :href="next.href" class="inline-flex items-center gap-1">
                    {{ next.label }}
                    <ChevronRight class="size-4" />
                </Link>
            </Button>
        </div>
    </div>
</template>
