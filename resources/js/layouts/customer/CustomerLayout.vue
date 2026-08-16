<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    BellRing,
    BookOpen,
    CheckCheck,
    Home,
    Info,
    LayoutGrid,
    LogIn,
    LogOut,
    MapPin,
    Package,
    Search,
    ShoppingCart,
    Tag,
    UserPen,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toaster } from '@/components/ui/sonner';
import { timeAgoID } from '@/lib/date';
import { about, home, logout } from '@/routes';
import { edit as editAddress } from '@/routes/address';
import { dashboard as adminDashboard } from '@/routes/admin';
import { catalog as catalogUrl } from '@/routes/books';
import { promo as promoRoute } from '@/routes/books';
import {
    read as readNotification,
    readAll as notificationsReadAll,
} from '@/routes/notifications';
import { edit as editProfile } from '@/routes/profile';
import type { User } from '@/types';

const page = usePage();
const storeName = computed(() => page.props.storeName ?? page.props.name);
const storeLogoUrl = computed(() => page.props.storeLogoUrl ?? '');
const user = computed<User | null>(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => user.value?.is_admin === true);
const cartCount = computed<number>(() => Number(page.props.cartCount ?? 0));
const activeOrdersCount = computed<number>(() =>
    Number(page.props.activeOrdersCount ?? 0),
);

type NotificationItem = {
    id: string;
    message: string;
    read_at: string | null;
    created_at: string;
};

const notifications = computed<NotificationItem[]>(
    () => (page.props.notifications ?? []) as NotificationItem[],
);
const notificationsCount = computed<number>(() =>
    Number(page.props.notificationsCount ?? 0),
);

function markNotificationRead(notification: NotificationItem): void {
    if (notification.read_at) {
        return;
    }

    router.post(
        readNotification(notification.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                notification.read_at = new Date().toISOString();
            },
        },
    );
}

function markAllNotificationsRead(): void {
    router.post(notificationsReadAll().url, {}, { preserveScroll: true });
}

function openNotification(notification: NotificationItem): void {
    markNotificationRead(notification);
    router.visit('/pesanan-saya');
}

// Filter katalog (search & kategori) hidup di header — hanya di halaman katalog
// & promo (promo: search saja).
const isCatalogPage = computed(() => page.component === 'storefront/Catalog');
const isPromoPage = computed(() => page.component === 'storefront/Promo');
const categories = computed<Array<{ id: string; nama: string }>>(
    () => (page.props.categories ?? []) as Array<{ id: string; nama: string }>,
);
const catalogFilters = computed<{ search?: string; category_id?: string }>(
    () =>
        (page.props.filters ?? {}) as { search?: string; category_id?: string },
);

const allCategories = '__all__';
const headerSearch = ref('');
const headerCategoryId = ref(allCategories);

// Sinkron dari server (navigasi balik/maju, submit filter) tanpa memicu request.
watch(
    () => [page.component, page.props.filters],
    () => {
        if (!isCatalogPage.value && !isPromoPage.value) {
            return;
        }

        headerSearch.value = catalogFilters.value.search ?? '';

        if (isCatalogPage.value) {
            headerCategoryId.value =
                catalogFilters.value.category_id ?? allCategories;
        } else {
            headerCategoryId.value = allCategories;
        }
    },
    { immediate: true },
);

// Perubahan input → request Inertia debounce (filter tetap terlihat saat scroll).
// Target route mengikuti halaman aktif: katalog (search+kategori) atau promo (search).
let headerTimer: ReturnType<typeof setTimeout> | undefined;

watch([headerSearch, headerCategoryId], () => {
    clearTimeout(headerTimer);
    headerTimer = setTimeout(() => {
        if (isPromoPage.value) {
            router.get(
                promoRoute().url,
                { search: headerSearch.value || undefined },
                { preserveState: true, replace: true },
            );

            return;
        }

        router.get(
            catalogUrl().url,
            {
                search: headerSearch.value || undefined,
                category_id:
                    headerCategoryId.value === allCategories
                        ? undefined
                        : headerCategoryId.value,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
});

const initials = computed(() => {
    const name = user.value?.name ?? '';

    return (
        name
            .split(' ')
            .filter(Boolean)
            .map((word) => word[0])
            .slice(0, 2)
            .join('')
            .toUpperCase() || '?'
    );
});

// ── Desktop nav (header, hidden lg:flex) — Beranda & Dashboard pindah
// ke dropdown avatar; Promo mengarah ke halaman /promo. ──
type DesktopNavItem = {
    label: string;
    href?: string;
    badge?: number;
};

const desktopNavItems = computed<DesktopNavItem[]>(() => {
    const items: DesktopNavItem[] = [
        { label: 'Promo', href: promoRoute().url },
    ];

    if (user.value && !isAdmin.value) {
        items.push({
            label: 'Keranjang',
            href: '/checkout',
            badge: cartCount.value,
        });
        items.push({
            label: 'Pesanan Saya',
            href: '/pesanan-saya',
            badge: activeOrdersCount.value,
        });
    }

    // Guest: hanya Keranjang setelah Promo
    if (!user.value) {
        items.push({
            label: 'Keranjang',
            href: '/checkout',
            badge: cartCount.value,
        });
    }

    return items;
});

// ── Bottom nav (mobile only) ──
// Catatan: item "Profil" sengaja TIDAK masuk daftar ini — dropdown-nya
// (teleport ke <body>) dirender di luar v-for ber-key di template. Dropdown
// di dalam keyed fragment bisa memicu crash Vue "Cannot read properties of
// null (reading 'type')" saat nav di-render ulang.
type BottomNavItem = {
    key: string;
    label?: string;
    href?: string;
    icon: Component;
    badge?: number;
};

const bottomNavItems = computed<BottomNavItem[]>(() => {
    const promo: BottomNavItem = {
        key: 'promo',
        label: 'Promo',
        href: promoRoute().url,
        icon: Tag,
    };
    const cart: BottomNavItem = {
        key: 'keranjang',
        label: 'Keranjang',
        href: '/checkout',
        icon: ShoppingCart,
        badge: cartCount.value,
    };

    if (user.value && !isAdmin.value) {
        return [
            {
                key: 'beranda',
                label: 'Beranda',
                href: home().url,
                icon: BookOpen,
            },
            promo,
            cart,
            {
                key: 'pesanan',
                label: 'Pesanan',
                href: '/pesanan-saya',
                icon: Package,
                badge: activeOrdersCount.value,
            },
        ];
    }

    if (isAdmin.value) {
        return [
            {
                key: 'beranda',
                label: 'Beranda',
                href: home().url,
                icon: BookOpen,
            },
            promo,
            {
                key: 'dashboard',
                label: 'Dashboard',
                href: adminDashboard().url,
                icon: LayoutGrid,
            },
        ];
    }

    // Guest
    return [
        { key: 'beranda', label: 'Beranda', href: home().url, icon: BookOpen },
        promo,
        cart,
    ];
});

function isBottomNavActive(item: BottomNavItem): boolean {
    if (!item.href) {
        return false;
    }

    if (item.href === home().url) {
        // Beranda = halaman utama; katalog (/buku) adalah halaman yang sama.
        return page.url === '/' || page.url === catalogUrl().url;
    }

    return page.url.startsWith(item.href);
}

// Profil aktif saat berada di halaman Akun / Alamat (settings customer).
const isProfilActive = computed(
    () =>
        page.url.startsWith(editProfile().url) ||
        page.url.startsWith(editAddress().url),
);
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <!-- ── Top bar (sticky: filter katalog selalu terlihat) ── -->
        <header
            class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="mx-auto flex w-full max-w-6xl flex-col px-4">
                <!-- Baris 1: logo + filter (desktop) + nav + avatar — disembunyikan di mobile -->
                <div class="hidden h-14 items-center gap-2 lg:flex">
                    <!-- Logo → Beranda -->
                    <Link
                        :href="home().url"
                        class="flex shrink-0 items-center gap-2"
                        title="Beranda"
                    >
                        <img
                            v-if="storeLogoUrl"
                            :src="storeLogoUrl"
                            :alt="storeName"
                            class="h-8 w-auto object-contain"
                        />
                        <AppLogoIcon v-else class="size-5 fill-current" />
                        <span class="hidden text-sm font-semibold lg:inline">
                            {{ storeName }}
                        </span>
                    </Link>

                    <!-- Search (desktop) -->
                    <div
                        v-if="isCatalogPage || isPromoPage"
                        class="relative ml-2 hidden flex-1 lg:block"
                    >
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="headerSearch"
                            class="pr-9 pl-9"
                            :placeholder="
                                isPromoPage
                                    ? 'Cari promo / judul buku...'
                                    : 'Cari judul / penulis...'
                            "
                        />
                        <button
                            v-if="headerSearch"
                            type="button"
                            class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground"
                            title="Hapus pencarian"
                            aria-label="Hapus pencarian"
                            @click="headerSearch = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <!-- Kategori (desktop) -->
                    <div v-if="isCatalogPage" class="hidden lg:block">
                        <Select v-model="headerCategoryId">
                            <SelectTrigger class="w-44">
                                <SelectValue placeholder="Semua kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="allCategories"
                                    >Semua kategori</SelectItem
                                >
                                <SelectItem
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="String(category.id)"
                                >
                                    {{ category.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Nav desktop -->
                    <nav class="ml-2 hidden items-center gap-1 text-sm lg:flex">
                        <Link
                            v-for="item in desktopNavItems"
                            :key="item.label"
                            :href="item.href"
                            class="relative rounded-md px-3 py-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            {{ item.label }}
                            <span
                                v-if="item.badge && item.badge > 0"
                                class="ml-1.5 inline-flex size-5 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </nav>

                    <div class="ml-auto flex items-center gap-1">
                        <!-- Lonceng notifikasi (customer login) -->
                        <DropdownMenu v-if="user && !isAdmin">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-9 rounded-full text-muted-foreground"
                                    title="Notifikasi"
                                    aria-label="Notifikasi"
                                >
                                    <BellRing class="size-5" />
                                    <span
                                        v-if="notificationsCount > 0"
                                        class="absolute mt-3 ml-3.5 inline-flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-white"
                                    >
                                        {{
                                            notificationsCount > 9
                                                ? '9+'
                                                : notificationsCount
                                        }}
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-80">
                                <DropdownMenuLabel
                                    class="flex items-center justify-between font-normal"
                                >
                                    <span class="text-sm font-medium"
                                        >Notifikasi</span
                                    >
                                    <button
                                        v-if="notificationsCount > 0"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                        @click="markAllNotificationsRead"
                                    >
                                        <CheckCheck class="size-3.5" />
                                        Tandai dibaca
                                    </button>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <div
                                    v-if="notifications.length === 0"
                                    class="px-4 py-6 text-center"
                                >
                                    <Bell
                                        class="mx-auto mb-2 size-6 text-muted-foreground/60"
                                    />
                                    <p class="text-sm text-muted-foreground">
                                        Belum ada notifikasi.
                                    </p>
                                </div>
                                <div v-else class="max-h-80 overflow-y-auto">
                                    <button
                                        v-for="notification in notifications"
                                        :key="notification.id"
                                        type="button"
                                        class="flex w-full flex-col gap-0.5 border-b px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted"
                                        :class="
                                            notification.read_at
                                                ? 'opacity-60'
                                                : 'bg-primary/5'
                                        "
                                        @click="openNotification(notification)"
                                    >
                                        <span class="text-sm font-medium">
                                            {{ notification.message }}
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                timeAgoID(
                                                    notification.created_at,
                                                )
                                            }}
                                        </span>
                                    </button>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Avatar dropdown (customer & admin) -->
                        <DropdownMenu v-if="user">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    class="size-9 rounded-full p-0"
                                    title="Menu akun"
                                >
                                    <Avatar class="size-8">
                                        <AvatarFallback
                                            class="bg-primary text-primary-foreground"
                                        >
                                            {{ initials }}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="min-w-44">
                                <DropdownMenuLabel class="font-normal">
                                    <p class="truncate text-sm font-medium">
                                        {{ user.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ user.email }}
                                    </p>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem as-child>
                                    <Link :href="home()">
                                        <Home class="size-4" />
                                        Beranda
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="isAdmin" as-child>
                                    <Link :href="adminDashboard()">
                                        <LayoutGrid class="size-4" />
                                        Dashboard
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link :href="about()">
                                        <Info class="size-4" />
                                        Tentang Kami
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <div class="hidden lg:block">
                                    <DropdownMenuLabel
                                        class="text-xs text-muted-foreground"
                                    >
                                        Pengaturan
                                    </DropdownMenuLabel>
                                    <DropdownMenuItem as-child>
                                        <Link :href="editProfile()">
                                            <UserPen class="size-4" />
                                            Akun
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="editAddress()">
                                            <MapPin class="size-4" />
                                            Alamat
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                </div>
                                <DropdownMenuItem
                                    class="text-destructive focus:text-destructive"
                                    as-child
                                >
                                    <Link
                                        :href="logout()"
                                        @click="router.flushAll()"
                                        as="button"
                                        class="w-full"
                                    >
                                        <LogOut class="size-4" />
                                        Logout
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Guest: login/register (desktop only) -->
                        <template v-if="!user">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="hidden lg:inline-flex"
                                as-child
                            >
                                <Link :href="'/login'">Masuk</Link>
                            </Button>
                            <Button
                                size="sm"
                                class="hidden lg:inline-flex"
                                as-child
                            >
                                <Link :href="'/register'">
                                    <UserPen class="size-4" />
                                    Daftar
                                </Link>
                            </Button>
                        </template>
                    </div>
                </div>

                <!-- Baris 2: search (mobile saja) — sticky mengikuti header -->
                <div
                    v-if="isCatalogPage || isPromoPage"
                    class="flex items-center py-2 lg:hidden"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="headerSearch"
                            class="h-9 pr-9 pl-9"
                            :placeholder="
                                isPromoPage
                                    ? 'Cari promo / judul buku...'
                                    : 'Cari judul / penulis...'
                            "
                        />
                        <button
                            v-if="headerSearch"
                            type="button"
                            class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground"
                            title="Hapus pencarian"
                            aria-label="Hapus pencarian"
                            @click="headerSearch = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- ── Main content ── -->
        <main class="mx-auto w-full max-w-6xl flex-1 px-4 pt-8 pb-20 lg:pb-8">
            <slot />
        </main>

        <!-- ── Footer ── -->
        <footer class="border-t py-6 pb-20 lg:pb-6">
            <p class="text-center text-sm text-muted-foreground">
                © {{ new Date().getFullYear() }} {{ storeName }}
            </p>
        </footer>

        <!-- ── Bottom nav (mobile only) ── -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur lg:hidden"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-around px-2"
            >
                <template v-for="item in bottomNavItems" :key="item.key">
                    <Link
                        :href="item.href ?? '/'"
                        class="relative flex flex-col items-center justify-center gap-0.5 px-3 py-1.5 text-[10px] font-medium transition-colors"
                        :class="[
                            isBottomNavActive(item) &&
                                'rounded-lg bg-primary/10',
                            isBottomNavActive(item)
                                ? 'text-primary'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <component :is="item.icon" class="size-5" />
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.badge && item.badge > 0"
                            class="absolute -top-0.5 right-1 inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </template>

                <!-- Notifikasi → menu dropdown — di luar v-for ber-key (stabil),
                     tampil hanya untuk customer login (mobile). -->
                <DropdownMenu v-if="user && !isAdmin">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="relative flex flex-col items-center gap-0.5 px-3 py-1.5 text-[10px] font-medium transition-colors"
                            :class="
                                notificationsCount > 0
                                    ? 'rounded-lg bg-primary/10 text-primary'
                                    : 'text-muted-foreground'
                            "
                        >
                            <BellRing class="size-5" />
                            <span>Notifikasi</span>
                            <span
                                v-if="notificationsCount > 0"
                                class="absolute -top-0.5 right-1 inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground"
                            >
                                {{
                                    notificationsCount > 9
                                        ? '9+'
                                        : notificationsCount
                                }}
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        side="top"
                        :side-offset="8"
                        class="w-80"
                    >
                        <DropdownMenuLabel
                            class="flex items-center justify-between font-normal"
                        >
                            <span class="text-sm font-medium">Notifikasi</span>
                            <button
                                v-if="notificationsCount > 0"
                                type="button"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                @click="markAllNotificationsRead"
                            >
                                <CheckCheck class="size-3.5" />
                                Tandai dibaca
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div
                            v-if="notifications.length === 0"
                            class="px-4 py-6 text-center"
                        >
                            <Bell
                                class="mx-auto mb-2 size-6 text-muted-foreground/60"
                            />
                            <p class="text-sm text-muted-foreground">
                                Belum ada notifikasi.
                            </p>
                        </div>
                        <div v-else class="max-h-80 overflow-y-auto">
                            <button
                                v-for="notification in notifications"
                                :key="notification.id"
                                type="button"
                                class="flex w-full flex-col gap-0.5 border-b px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted"
                                :class="
                                    notification.read_at
                                        ? 'opacity-60'
                                        : 'bg-primary/5'
                                "
                                @click="openNotification(notification)"
                            >
                                <span class="text-sm font-medium">
                                    {{ notification.message }}
                                </span>
                                <span
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        timeAgoID(notification.created_at)
                                    }}
                                </span>
                            </button>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Profil → menu dropdown — di luar v-for ber-key (stabil) -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-[10px] font-medium transition-colors"
                            :class="
                                isProfilActive
                                    ? 'rounded-lg bg-primary/10 text-primary'
                                    : 'text-muted-foreground'
                            "
                        >
                            <UserPen class="size-5" />
                            <span>Profil</span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        side="top"
                        :side-offset="8"
                        class="min-w-44"
                    >
                        <DropdownMenuItem as-child>
                            <Link :href="home()">
                                <Home class="size-4" />
                                Beranda
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="about()">
                                <Info class="size-4" />
                                Tentang Kami
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="isAdmin" as-child>
                            <Link :href="adminDashboard()">
                                <LayoutGrid class="size-4" />
                                Dashboard
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <template v-if="user">
                            <DropdownMenuItem as-child>
                                <Link :href="editProfile()">
                                    <UserPen class="size-4" />
                                    Akun
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="editAddress()">
                                    <MapPin class="size-4" />
                                    Alamat
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="text-destructive focus:text-destructive"
                                as-child
                            >
                                <Link
                                    :href="logout()"
                                    @click="router.flushAll()"
                                    as="button"
                                >
                                    <LogOut class="size-4" />
                                    Logout
                                </Link>
                            </DropdownMenuItem>
                        </template>
                        <template v-else>
                            <DropdownMenuItem as-child>
                                <Link :href="'/login'">
                                    <LogIn class="size-4" />
                                    Masuk
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="'/register'">
                                    <UserPen class="size-4" />
                                    Daftar
                                </Link>
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </nav>

        <Toaster />
    </div>
</template>
