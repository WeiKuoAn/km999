<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useCloseMobileSidebar } from '@/composables/useCloseMobileSidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup, NavItem } from '@/types';

defineProps<{
    groups: NavGroup[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const closeMobileSidebar = useCloseMobileSidebar();

function isChildGroupActive(item: NavItem): boolean {
    return item.children?.some((c) => c.href !== undefined && isCurrentUrl(c.href)) ?? false;
}
</script>

<template>
    <template v-for="group in groups" :key="group.label">
        <SidebarGroup class="px-2 py-0">
            <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="`${group.label}-${item.title}`">
                    <Collapsible
                        v-if="item.children?.length"
                        :default-open="isChildGroupActive(item)"
                        class="w-full"
                    >
                        <SidebarMenuButton :tooltip="item.title" class="w-full" as-child>
                            <CollapsibleTrigger
                                class="group/trigger flex w-full items-center gap-2 outline-hidden [&[data-state=open]>svg:last-child]:rotate-90"
                            >
                                <component :is="item.icon" />
                                <span class="truncate">{{ item.title }}</span>
                                <ChevronRight class="ml-auto size-4 shrink-0 transition-transform duration-200" />
                            </CollapsibleTrigger>
                        </SidebarMenuButton>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="sub in item.children" :key="sub.title">
                                    <SidebarMenuSubButton
                                        as-child
                                        size="sm"
                                        :is-active="sub.href !== undefined && isCurrentUrl(sub.href)"
                                    >
                                        <Link :href="sub.href!" @click="closeMobileSidebar">
                                            <component :is="sub.icon" />
                                            <span>{{ sub.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </Collapsible>

                    <SidebarMenuButton
                        v-else
                        as-child
                        :is-active="item.href !== undefined && isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href!" @click="closeMobileSidebar">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </template>
</template>
