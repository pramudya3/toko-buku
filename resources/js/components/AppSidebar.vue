<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    FolderTree,
    LayoutGrid,
    Package,
    ShoppingCart,
    Tags,
    Truck,
    Users,
    Wallet,
    Warehouse,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/admin';
import { index as booksIndex } from '@/routes/admin/books';
import { index as cashFlowIndex } from '@/routes/admin/cash-flow';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as customersIndex } from '@/routes/admin/customers';
import { index as dropshipIndex } from '@/routes/admin/dropship';
import { index as inventoryIndex } from '@/routes/admin/inventory';
import { index as ordersIndex } from '@/routes/admin/orders';
import { index as promotionsIndex } from '@/routes/admin/promotions';
import { index as tierDiscountsIndex } from '@/routes/admin/tier-discounts';
import type { NavGroup, NavItem } from '@/types';

const page = usePage();
const pendingOrdersCount = computed<number>(() => page.props.pendingOrdersCount ?? 0);

const navGroups = computed<NavGroup[]>(() => [
    {
        label: 'Utama',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ],
    },
    {
        label: 'Katalog',
        items: [
            {
                title: 'Buku',
                href: booksIndex(),
                icon: BookOpen,
            },
            {
                title: 'Kategori',
                href: categoriesIndex(),
                icon: FolderTree,
            },
        ],
    },
    {
        label: 'Transaksi',
        items: [
            {
                title: 'Pesanan',
                href: ordersIndex(),
                icon: ShoppingCart,
                badge: pendingOrdersCount.value,
            },
            {
                title: 'Dropship',
                href: dropshipIndex(),
                icon: Truck,
            },
        ],
    },
    {
        label: 'Promosi & Harga',
        items: [
            {
                title: 'Promosi',
                href: promotionsIndex(),
                icon: Tags,
            },
            {
                title: 'Tier Discount',
                href: tierDiscountsIndex(),
                icon: Tags,
            },
        ],
    },
    {
        label: 'Operasional',
        items: [
            {
                title: 'Pelanggan',
                href: customersIndex(),
                icon: Users,
            },
            {
                title: 'Inventori',
                href: inventoryIndex(),
                icon: Warehouse,
            },
            {
                title: 'Keuangan',
                href: cashFlowIndex(),
                icon: Wallet,
            },
        ],
    },
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Storefront',
        href: '/',
        icon: Package,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
