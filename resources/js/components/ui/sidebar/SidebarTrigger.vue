<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Menu, PanelLeftClose, PanelLeftOpen } from "lucide-vue-next"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="outline"
    size="icon"
    :class="cn(
      'size-10 shrink-0 rounded-lg border-border/80 bg-background shadow-sm hover:bg-muted/60 lg:size-9',
      props.class,
    )"
    @click="toggleSidebar"
  >
    <Menu v-if="isMobile" class="size-5" />
    <PanelLeftClose v-else-if="state === 'expanded'" class="size-5" />
    <PanelLeftOpen v-else class="size-5" />
    <span class="sr-only">
      {{ isMobile ? '開啟選單' : state === 'expanded' ? '收合選單' : '展開選單' }}
    </span>
  </Button>
</template>
