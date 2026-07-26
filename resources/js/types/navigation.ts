import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    /** 有 `children` 時可省略（僅作為摺疊父層） */
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** 子選單（例如「設定管理」底下的課程相關連結） */
    children?: NavItem[];
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
