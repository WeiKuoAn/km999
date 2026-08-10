<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    BookOpenCheck,
    CalendarOff,
    CalendarRange,
    ChartColumnBig,
    CircleDollarSign,
    GraduationCap,
    CalendarDays,
    LayoutDashboard,
    Layers,
    Route,
    Settings,
    Tags,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useCloseMobileSidebar } from '@/composables/useCloseMobileSidebar';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import type { NavGroup, NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);
const closeMobileSidebar = useCloseMobileSidebar();

const navGroups = computed<NavGroup[]>(() => {
    if (user.value.role === 'teacher') {
        return [
            {
                label: '快速點選',
                items: [
                    {
                        title: '總覽',
                        href: '/dashboard',
                        icon: LayoutDashboard,
                    },
                    {
                        title: '行事曆',
                        href: '/calendar',
                        icon: CalendarDays,
                    },
                    {
                        title: '營運流程預覽',
                        href: '/flow-preview',
                        icon: Route,
                    },
                ],
            },
            {
                label: '功能選單',
                items: [
                    {
                        title: '學生管理',
                        href: '/students',
                        icon: GraduationCap,
                    },
                    {
                        title: '學生收款',
                        href: '/student-payments',
                        icon: Wallet,
                    },
                    {
                        title: '繳費名單',
                        href: '/payment-lists',
                        icon: CircleDollarSign,
                    },
                ],
            },
        ];
    }

    const mainItems: NavItem[] = [
        {
            title: '學生管理',
            href: '/students',
            icon: GraduationCap,
        },
        {
            title: '學生收款',
            href: '/student-payments',
            icon: Wallet,
        },
        {
            title: '繳費名單',
            href: '/payment-lists',
            icon: CircleDollarSign,
        },
    ];

    if (user.value.role === 'super_admin' || user.value.role === 'admin') {
        mainItems.push({
            title: '學生轉檔',
            href: '/student-promotions',
            icon: ArrowRightLeft,
        });
    }

    if (user.value.role === 'super_admin') {
        mainItems.push({
            title: '每月營收報表',
            href: '/reports',
            icon: ChartColumnBig,
        });
    }

    if (user.value.role === 'super_admin' || user.value.role === 'admin') {
        mainItems.push({
            title: '用戶管理',
            href: '/users',
            icon: Users,
        });
    }

    mainItems.push({
        title: '設定管理',
        icon: Settings,
        children: [
            {
                title: '學年設定',
                href: '/academic-years',
                icon: CalendarRange,
            },
            {
                title: '假日設定',
                href: '/holidays',
                icon: CalendarOff,
            },
            {
                title: '年級編號',
                href: '/grade-levels',
                icon: Layers,
            },
            {
                title: '收費標準',
                href: '/fee-plans',
                icon: CircleDollarSign,
            },
            {
                title: '課程類別管理',
                href: '/course-categories',
                icon: Tags,
            },
            {
                title: '課程管理',
                href: '/courses',
                icon: BookOpenCheck,
            },
        ],
    });

    return [
        {
            label: '快速點選',
            items: [
                {
                    title: '總覽',
                    href: '/dashboard',
                    icon: LayoutDashboard,
                },
                {
                    title: '行事曆',
                    href: '/calendar',
                    icon: CalendarDays,
                },
                {
                    title: '營運流程預覽',
                    href: '/flow-preview',
                    icon: Route,
                },
            ],
        },
        {
            label: '功能選單',
            items: mainItems,
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="border-b border-sidebar-border/70">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard" @click="closeMobileSidebar">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <SidebarRail class="max-lg:hidden" />
</template>
