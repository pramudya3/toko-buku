import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    badge?: number;
};

/** Item menu yang membuka sub menu (collapsible parent) — tanpa href sendiri. */
export type NavSection = {
    title: string;
    icon?: LucideIcon;
    items: NavItem[];
};

export type NavGroup = {
    label: string;
    items: Array<NavItem | NavSection>;
};
