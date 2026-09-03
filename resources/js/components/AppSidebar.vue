<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    BadgePercent,
    Banknote,
    BookOpen,
    Boxes,
    Building2,
    CalendarDays,
    CalendarClock,
    ChartColumn,
    ChartPie,
    ClipboardList,
    Factory,
    FileSpreadsheet,
    FolderTree,
    HandCoins,
    HelpCircle,
    Handshake,
    History,
    // KeyRound, // API Key — disembunyikan sementara (lihat grup Pengaturan)
    Landmark,
    Layers,
    LayoutGrid,
    MessageCircle,
    NotebookPen,
    PackageX,
    Scale,
    ScrollText,
    Search,
    ShieldCheck,
    ShoppingCart,
    Store,
    Tags,
    TicketPercent,
    Truck,
    Undo2,
    Users,
    Wallet,
    Warehouse,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppCommandPalette from '@/components/AppCommandPalette.vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/admin';
import { index as aktivitasIndex } from '@/routes/admin/aktivitas';
import { index as articleCategoriesIndex } from '@/routes/admin/article-categories';
import { index as articlesIndex } from '@/routes/admin/articles';
import { index as booksIndex } from '@/routes/admin/books';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as customersIndex } from '@/routes/admin/customers';
import { index as dailyRecapIndex } from '@/routes/admin/daily-recap';
import { index as dropshipIndex } from '@/routes/admin/dropship';
import { index as inventoryIndex } from '@/routes/admin/inventory';
import { index as inventoryAdjustmentsIndex } from '@/routes/admin/inventory-adjustments';
import { index as inventoryReportsIndex } from '@/routes/admin/inventory-reports';
import { index as kasIndex } from '@/routes/admin/kas';
import { laporan as kasLaporan } from '@/routes/admin/kas';
import { index as kasKategoriIndex } from '@/routes/admin/kas/categories';
import { laporan as konsinyasiLaporan } from '@/routes/admin/konsinyasi';
import { index as konsinyasiIndex } from '@/routes/admin/konsinyasi';
import {
    index as ordersIndex,
    preorder as preorderIndex,
} from '@/routes/admin/orders';
import { index as promotionsIndex } from '@/routes/admin/promotions';
import { index as purchasesIndex } from '@/routes/admin/purchases';
import { index as receivablesIndex } from '@/routes/admin/receivables';
import { index as salesReportsIndex } from '@/routes/admin/sales-reports';
import { index as salesReturnsIndex } from '@/routes/admin/sales-returns';
// import { apiKey as apiKeyRoute } from '@/routes/admin/settings'; // API Key — disembunyikan sementara
import {
    ekspedisi as ekspedisiRoute,
    lembaga as lembagaRoute,
    pembayaran as pembayaranRoute,
    rekening as rekeningRoute,
    sumberPenjualan as sumberPenjualanRoute,
    waTemplate as waTemplateRoute,
} from '@/routes/admin/settings';
import { alasanRetur as alasanReturRoute } from '@/routes/admin/settings';
import { index as stockRequestsIndex } from '@/routes/admin/stock-requests';
import { index as supplierDebtsIndex } from '@/routes/admin/supplier-debts';
import { index as supplierReportsIndex } from '@/routes/admin/supplier-reports';
import { index as supplierReturnsIndex } from '@/routes/admin/supplier-returns';
import { index as suppliersIndex } from '@/routes/admin/suppliers';
import { index as tierDiscountsIndex } from '@/routes/admin/tier-discounts';
import { index as usersIndex } from '@/routes/admin/users';
import { index as vouchersIndex } from '@/routes/admin/vouchers';
import { index as warehousesIndex } from '@/routes/admin/warehouses';
import type { NavGroup, NavItem } from '@/types';

const page = usePage();
const pendingOrdersCount = computed<number>(() =>
    Number(page.props.pendingOrdersCount ?? 0),
);
const preorderPendingCount = computed<number>(() =>
    Number(page.props.preorderPendingCount ?? 0),
);
const paletteOpen = ref(false);
const isMac = ref(false);

if (typeof navigator !== 'undefined') {
    isMac.value =
        /Mac|iPhone|iPad|iPod/.test(navigator.platform) ||
        /Mac/.test(navigator.userAgent);
}

// Item mandiri di atas — akses cepat ke menu yang paling sering dipakai.
const mainItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Pelanggan',
        href: customersIndex(),
        icon: Users,
    },
]);

// Item mandiri di paling bawah — konfigurasi toko.
const footerItems = computed<NavItem[]>(() => [
    {
        title: 'Panduan',
        href: '/admin/panduan',
        icon: HelpCircle,
    },
    {
        title: 'Log Aktivitas',
        href: aktivitasIndex(),
        icon: ScrollText,
    },
]);

const navGroups = computed<NavGroup[]>(() => [
    {
        label: 'Konten',
        items: [
            {
                title: 'Artikel',
                href: articlesIndex(),
                icon: NotebookPen,
            },
            {
                title: 'Kategori',
                href: articleCategoriesIndex(),
                icon: Layers,
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
            {
                title: 'Promosi',
                href: promotionsIndex(),
                icon: Tags,
            },
            {
                title: 'Tier Discount',
                href: tierDiscountsIndex(),
                icon: BadgePercent,
            },
            {
                title: 'Voucher',
                href: vouchersIndex(),
                icon: TicketPercent,
            },
            {
                title: 'Pengajuan Stok',
                href: stockRequestsIndex(),
                icon: ClipboardList,
            },
        ],
    },
    {
        label: 'Penjualan',
        items: [
            {
                title: 'Pesanan',
                href: ordersIndex(),
                icon: ShoppingCart,
                badge: pendingOrdersCount.value,
            },
            {
                title: 'Pre-Order',
                href: preorderIndex(),
                icon: CalendarClock,
                badge: preorderPendingCount.value,
            },
            {
                title: 'Dropship',
                href: dropshipIndex(),
                icon: Truck,
            },
            {
                title: 'Retur Penjualan',
                href: salesReturnsIndex(),
                icon: PackageX,
            },
            {
                title: 'Piutang',
                href: receivablesIndex(),
                icon: HandCoins,
            },
            {
                title: 'Konsinyasi',
                href: konsinyasiIndex(),
                icon: Handshake,
            },
            {
                title: 'Laporan Konsinyasi',
                href: konsinyasiLaporan(),
                icon: FileSpreadsheet,
            },
            {
                title: 'Laporan Penjualan',
                href: salesReportsIndex(),
                icon: ChartColumn,
            },
            {
                title: 'Laporan Harian',
                href: dailyRecapIndex(),
                icon: CalendarDays,
            },
        ],
    },
    {
        label: 'Pembelian',
        items: [
            {
                title: 'Supplier',
                href: suppliersIndex(),
                icon: Factory,
            },
            {
                title: 'Barang Masuk',
                href: purchasesIndex(),
                icon: ArrowDownToLine,
            },
            {
                title: 'Retur Supplier',
                href: supplierReturnsIndex(),
                icon: Undo2,
            },
            {
                title: 'Hutang Supplier',
                href: supplierDebtsIndex(),
                icon: Banknote,
            },
            {
                title: 'Laporan Supplier',
                href: supplierReportsIndex(),
                icon: FileSpreadsheet,
            },
        ],
    },
    {
        label: 'Kas',
        items: [
            {
                title: 'Pencatatan Kas',
                href: kasIndex(),
                icon: NotebookPen,
            },
            {
                title: 'Laporan Kas',
                href: kasLaporan(),
                icon: ChartPie,
            },
            {
                title: 'Kategori Kas',
                href: kasKategoriIndex(),
                icon: FolderTree,
            },
        ],
    },
    {
        label: 'Stok',
        items: [
            {
                title: 'Inventori',
                href: inventoryIndex(),
                icon: Boxes,
            },
            {
                title: 'Gudang',
                href: warehousesIndex(),
                icon: Warehouse,
            },
            {
                title: 'Laporan Mutasi',
                href: inventoryReportsIndex(),
                icon: History,
            },
            {
                title: 'Stok Adjustment',
                href: inventoryAdjustmentsIndex(),
                icon: Scale,
            },
        ],
    },
    {
        label: 'Pengaturan',
        items: [
            {
                title: 'Lembaga',
                href: lembagaRoute(),
                icon: Building2,
            },
            {
                title: 'Rekening Bank',
                href: rekeningRoute(),
                icon: Landmark,
            },
            {
                title: 'Ekspedisi',
                href: ekspedisiRoute(),
                icon: Truck,
            },
            {
                title: 'Metode Pembayaran',
                href: pembayaranRoute(),
                icon: Wallet,
            },
            {
                title: 'Sumber Penjualan',
                href: sumberPenjualanRoute(),
                icon: Store,
            },
            {
                title: 'WA Template',
                href: waTemplateRoute(),
                icon: MessageCircle,
            },
            {
                title: 'Alasan',
                href: alasanReturRoute(),
                icon: Undo2,
            },
            {
                title: 'Staf',
                href: usersIndex(),
                icon: ShieldCheck,
            },
            // API Key — disembunyikan sementara; aktifkan kembali dengan menghapus komentar di bawah
            // {
            //     title: 'API Key',
            //     href: apiKeyRoute(),
            //     icon: KeyRound,
            // },
        ],
    },
]);
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

        <div class="px-2 py-2 group-data-[collapsible=icon]:hidden">
            <Button
                variant="outline"
                class="w-full justify-start gap-2 bg-muted/50 px-3 font-normal text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                @click="paletteOpen = true"
            >
                <Search class="size-4 shrink-0" />
                <span class="flex-1 text-left text-sm">Cari menu</span>
                <span
                    class="hidden items-center gap-1 group-data-[collapsible=icon]:hidden sm:inline-flex"
                >
                    <kbd
                        class="rounded border bg-background px-1.5 py-0.5 font-mono text-xs"
                        >{{ isMac ? '⌘' : 'Ctrl' }}</kbd
                    >
                    <span class="text-xs text-muted-foreground">+</span>
                    <kbd
                        class="rounded border bg-background px-1.5 py-0.5 font-mono text-xs"
                        >K</kbd
                    >
                </span>
            </Button>
        </div>

        <SidebarContent>
            <NavMain
                :items="mainItems"
                :groups="navGroups"
                :footer-items="footerItems"
            />
        </SidebarContent>
    </Sidebar>
    <AppCommandPalette
        v-model:open="paletteOpen"
        :groups="navGroups"
        :items="mainItems"
        :footer-items="footerItems"
    />
    <slot />
</template>
