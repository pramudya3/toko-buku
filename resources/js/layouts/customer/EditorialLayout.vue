<script setup lang="ts">
/**
 * EditorialLayout — layout utama seluruh halaman storefront customer.
 *
 * Desain editorial Islami: latar hangat, tipografi Inter + Lora, aksen hijau
 * tua & emas lembut, banyak ruang kosong. Header sticky + pencarian
 * (beranda/katalog/promo), menu mobile, lonceng notifikasi customer,
 * dan footer "Toko Buku" dengan daftar menu.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    BookOpen,
    Home,
    LogIn,
    LogOut,
    Package,
    Search,
    ShoppingCart,
    X,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import type { Component } from 'vue';
import NotificationsMenu from '@/components/storefront/NotificationsMenu.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import { about as aboutRoute, home as homeRoute, logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { catalog as catalogUrl, promo as promoRoute } from '@/routes/books';
import { index as myOrdersRoute } from '@/routes/my-orders';
import { edit as storefrontProfileEdit } from '@/routes/storefront/profile';

const page = usePage();
const storeName = computed(() =>
    String(page.props.storeName ?? 'Toko Buku'),
);
const storeLogoUrl = computed(() => String(page.props.storeLogoUrl ?? ''));
const storeAddress = computed(() => String(page.props.storeAddress ?? ''));
const storePhone = computed(() => String(page.props.storePhone ?? ''));
const user = computed(() => page.props.auth?.user ?? null);
const cartCount = computed(() => Number(page.props.cartCount ?? 0));
const isAdmin = computed(() => user.value?.is_admin === true);
const activeOrdersCount = computed<number>(() =>
    Number(page.props.activeOrdersCount ?? 0),
);

// ── Bottom nav (mobile only) ──
// Beranda + Toko + Pesanan (badge). Profil (avatar inisial / tombol login)
// dirender terpisah di luar v-for ber-key (stabil) untuk menghindari crash
// Vue saat re-render.
type BottomNavItem = {
    key: string;
    label?: string;
    href?: string;
    icon: Component;
    badge?: number;
};

const bottomNavItems = computed<BottomNavItem[]>(() => {
    const items: BottomNavItem[] = [
        {
            key: 'beranda',
            label: 'Beranda',
            href: homeRoute().url,
            icon: Home,
        },
        {
            key: 'toko',
            label: 'Toko',
            href: catalogUrl().url,
            icon: BookOpen,
        },
    ];

    if (user.value && !isAdmin.value) {
        items.push({
            key: 'pesanan',
            label: 'Pesanan',
            href: '/pesanan-saya',
            icon: Package,
            badge: activeOrdersCount.value,
        });
    }

    return items;
});

// Inisial nama user — sama seperti avatar desktop.
const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

function isBottomNavActive(item: BottomNavItem): boolean {
    if (!item.href) {
        return false;
    }

    if (item.href === homeRoute().url) {
        // Beranda = hanya halaman utama (/). Katalog punya item sendiri (Toko).
        return page.url === homeRoute().url;
    }

    return page.url.startsWith(item.href);
}

// Profil aktif saat berada di halaman profil/akun/alamat.
const isProfilActive = computed(
    () => page.url.startsWith('/profil') || page.url.startsWith('/settings'),
);

// ── Pencarian di header — semua halaman; katalog membuka /buku?search= ──
const isHomePage = computed(() => page.component === 'storefront/Home');
const isCatalogPage = computed(() => page.component === 'storefront/Catalog');
const isPromoPage = computed(() => page.component === 'storefront/Promo');
// Halaman settings (akun, alamat, keamanan) — fokus form, tanpa pencarian.
const isSettingsPage = computed(() => page.component.startsWith('settings/'));
const isBookDetailPage = computed(
    () => page.component === 'storefront/BookDetail',
);
// Halaman form checkout (isi alamat & bayar) — fokus transaksi.
const isCheckoutFormPage = computed(
    () => page.component === 'storefront/Checkout',
);
// Halaman sukses checkout — ikut alur pembayaran (tanpa pencarian),
// tapi boleh menampilkan nav Toko & ikon keranjang (belanja lanjutan).
const isCheckoutSuccessPage = computed(
    () => page.component === 'storefront/CheckoutSuccess',
);
const isOrdersPage = computed(
    () =>
        page.component === 'storefront/MyOrders' ||
        page.component === 'storefront/OrderDetail',
);
// Halaman tanpa pencarian: detail buku, checkout, pesanan, & settings — fokus
// ke konten.
const isNoSearchPage = computed(
    () =>
        isBookDetailPage.value ||
        isCheckoutPage.value ||
        isOrdersPage.value ||
        isSettingsPage.value,
);
const isCheckoutPage = computed(
    () => isCheckoutFormPage.value || isCheckoutSuccessPage.value,
);
const showHeaderSearch = computed(() => !isNoSearchPage.value);

// Placeholder pencarian menyesuaikan halaman aktif.
// Beranda: cari artikel DAN buku; Toko: hanya buku.
const searchPlaceholder = computed(() => {
    if (isPromoPage.value) {
        return 'Cari promo / judul buku…';
    }

    if (isHomePage.value) {
        return 'Cari artikel atau buku';
    }

    return 'Cari judul / penulis';
});

// Ikon keranjang di header desktop — tampil juga di halaman pesanan & sukses
// checkout; disembunyikan hanya di halaman checkout itu sendiri (sudah di cart).
const showHeaderCart = computed(
    () =>
        isCatalogPage.value ||
        isBookDetailPage.value ||
        isOrdersPage.value ||
        isCheckoutSuccessPage.value,
);
const filters = computed<Record<string, unknown>>(
    () => (page.props.filters ?? {}) as Record<string, unknown>,
);
const headerSearch = ref('');

// Search mobile: bar tersembunyi, dibuka lewat ikon kaca pembesar.
const mobileSearchOpen = ref(false);

function toggleMobileSearch(): void {
    mobileSearchOpen.value = !mobileSearchOpen.value;

    if (mobileSearchOpen.value) {
        nextTick(() => {
            document
                .querySelector<HTMLInputElement>('#ed-search-mobile')
                ?.focus();
        });
    }
}

watch(
    () => [page.component, filters.value.search],
    () => {
        if (!isHomePage.value && !isCatalogPage.value && !isPromoPage.value) {
            return;
        }

        headerSearch.value =
            typeof filters.value.search === 'string'
                ? filters.value.search
                : '';
    },
    { immediate: true },
);

// Pencarian hanya dijalankan saat Enter — tidak auto-cari saat mengetik.
function submitSearch(): void {
    runSearch(headerSearch.value);
}

// Bersihkan pencarian — langsung fetch ulang tanpa perlu Enter.
function clearSearch(): void {
    headerSearch.value = '';
    runSearch('');
}

function runSearch(value: string): void {
    if (isCatalogPage.value) {
        router.get(
            catalogUrl().url,
            {
                search: value || undefined,
                category_id:
                    typeof filters.value.category_id === 'string' &&
                    filters.value.category_id
                        ? filters.value.category_id
                        : undefined,
            },
            {
                preserveState: true,
                replace: true,
                only: ['books', 'filters'],
            },
        );

        return;
    }

    // Promo: filter kartu promo secara lokal di halaman (data dimuat penuh).
    if (isPromoPage.value) {
        router.get(
            promoRoute().url,
            { search: value || undefined },
            {
                preserveState: true,
                replace: true,
                only: ['bundles', 'promos', 'vouchers', 'filters'],
            },
        );

        return;
    }

    if (!isHomePage.value) {
        return;
    }

    router.get(
        homeRoute().url,
        {
            search: value || undefined,
            category_id:
                typeof filters.value.category_id === 'string' &&
                filters.value.category_id
                    ? filters.value.category_id
                    : undefined,
        },
        {
            preserveState: true,
            replace: true,
            only: [
                'featured',
                'recent',
                'articles',
                'books',
                'categories',
                'filters',
            ],
        },
    );
}
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-article-bg text-article-ink antialiased"
    >
        <Toaster position="top-center" :rich-colors="false" />

        <a
            href="#main"
            class="sr-only z-50 rounded-md bg-article-primary px-4 py-3 text-sm font-medium text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Langsung ke konten
        </a>

        <!-- ══════════ HEADER ══════════ -->
        <header
            class="sticky top-0 z-40 border-b border-article-border bg-article-bg/95 backdrop-blur supports-[backdrop-filter]:bg-article-bg/80"
        >
            <div class="mx-auto flex w-full max-w-6xl flex-col px-4">
                <!-- Baris 1: logo + search (desktop) + nav + avatar — desktop hanya -->
                <div class="hidden h-20 items-center gap-3 lg:flex">
                    <!-- Logo → Beranda -->
                    <Link
                        :href="homeRoute().url"
                        class="flex shrink-0 items-center gap-2.5"
                        :aria-label="`${storeName} — beranda`"
                    >
                        <img
                            v-if="storeLogoUrl"
                            :src="storeLogoUrl"
                            :alt="storeName"
                            class="h-9 w-auto object-contain"
                        />
                        <BookOpen
                            v-else
                            class="h-9 w-9 text-article-primary"
                            aria-hidden="true"
                        />
                        <span
                            class="hidden text-[17px] font-extrabold tracking-tight lg:inline"
                            >{{ storeName }}</span
                        >
                    </Link>

                    <!-- Pencarian (desktop) — selalu terlihat, cari saat Enter -->
                    <div
                        v-if="showHeaderSearch"
                        class="relative ml-2 hidden flex-1 lg:block"
                    >
                        <label class="sr-only" for="ed-search"
                            >Cari buku atau artikel</label
                        >
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-article-muted"
                            aria-hidden="true"
                        />
                        <input
                            id="ed-search"
                            v-model="headerSearch"
                            type="search"
                            :placeholder="searchPlaceholder"
                            class="min-h-10 w-full rounded-lg border border-article-border bg-article-surface py-2 pr-9 pl-9 text-sm transition-colors outline-none placeholder:text-article-muted focus:border-article-primary focus:ring-2 focus:ring-article-primary/20"
                            @keyup.enter="submitSearch"
                        />
                        <button
                            v-if="headerSearch"
                            type="button"
                            class="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded text-article-muted transition-colors hover:text-article-ink"
                            aria-label="Bersihkan pencarian"
                            @click="clearSearch"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </div>

                    <!-- Nav desktop — ml-auto mendorong nav + cart + avatar
                         ke ujung kanan: logo ----search---- Toko 🛒 │ avatar -->
                    <nav
                        class="ml-auto hidden shrink-0 items-center gap-1 text-sm lg:flex"
                        aria-label="Navigasi utama"
                    >
                        <!-- Link Toko — disembunyikan saat sudah berada di
                             toko / detail buku / form checkout -->
                        <Link
                            v-if="
                                !isCatalogPage &&
                                !isBookDetailPage &&
                                !isCheckoutFormPage
                            "
                            :href="catalogUrl().url"
                            class="rounded-md px-3 py-1.5 font-semibold text-article-ink transition-colors hover:bg-article-border/50"
                        >
                            Toko</Link
                        >
                        <!-- Pesanan Saya — hanya di menu Toko (katalog) -->
                        <template v-if="isCatalogPage && user && !isAdmin">
                            <Link
                                :href="'/pesanan-saya'"
                                class="relative rounded-md px-3 py-1.5 text-article-muted transition-colors hover:bg-article-border/50 hover:text-article-ink"
                            >
                                Pesanan Saya
                                <span
                                    v-if="activeOrdersCount > 0"
                                    class="ml-1.5 inline-flex size-5 items-center justify-center rounded-full bg-article-primary text-[11px] font-bold text-white"
                                >
                                    {{ activeOrdersCount }}
                                </span>
                            </Link>
                        </template>
                    </nav>

                    <!-- Avatar / login (desktop) — tanpa ml-auto: ruang kosong
                         sudah diserap nav di kiri agar Toko menempel ke cart -->
                    <div class="flex shrink-0 items-center gap-1">
                        <!-- Keranjang — semua halaman kecuali form checkout;
                             di pesanan & sukses checkout tetap tersedia agar
                             user bisa balik ke keranjang -->
                        <Link
                            v-if="showHeaderCart"
                            :href="'/checkout'"
                            class="relative flex size-10 shrink-0 items-center justify-center rounded-lg text-article-ink transition-colors hover:bg-article-border/50"
                            :aria-label="`Keranjang, ${cartCount} item`"
                        >
                            <ShoppingCart class="size-5" aria-hidden="true" />
                            <span
                                v-if="cartCount > 0"
                                class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-article-primary text-[10px] font-bold text-white"
                                aria-hidden="true"
                                >{{ cartCount }}</span
                            >
                        </Link>

                        <!-- Separator + space — hanya muncul jika ada cart di kiri -->
                        <span
                            v-if="showHeaderCart"
                            class="mx-2 h-6 w-px shrink-0 bg-article-border"
                            aria-hidden="true"
                        ></span>

                        <!-- Lonceng notifikasi — customer login (bukan admin) -->
                        <NotificationsMenu v-if="user && !isAdmin">
                            <template #trigger="{ count }">
                                <button
                                    type="button"
                                    class="relative flex size-10 shrink-0 items-center justify-center rounded-lg text-article-ink transition-colors hover:bg-article-border/50"
                                    aria-label="Notifikasi"
                                >
                                    <BellRing
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-if="count > 0"
                                        class="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-article-primary px-1 text-[10px] font-bold text-white"
                                    >
                                        {{ count > 9 ? '9+' : count }}
                                    </span>
                                </button>
                            </template>
                        </NotificationsMenu>

                        <template v-if="user">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-article-primary text-sm font-bold text-white transition-colors hover:bg-article-primary-dark focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:ring-offset-2 focus-visible:outline-none"
                                        :aria-label="`Menu ${user.name}`"
                                    >
                                        {{
                                            (user.name ?? '?')
                                                .split(/\s+/)
                                                .slice(0, 2)
                                                .map((part) =>
                                                    part
                                                        .charAt(0)
                                                        .toUpperCase(),
                                                )
                                                .join('')
                                        }}
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    class="min-w-44"
                                >
                                    <DropdownMenuItem v-if="isAdmin" as-child>
                                        <Link :href="adminDashboard().url">
                                            Dashboard
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="'/profil'">
                                            Profil Saya
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="aboutRoute().url">
                                            Tentang
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
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
                                            <LogOut
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            Logout
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>
                        <template v-else>
                            <Link
                                :href="'/login'"
                                class="hidden min-h-10 items-center rounded-md px-3 text-sm font-semibold text-article-muted transition-colors hover:bg-article-border/50 hover:text-article-ink md:inline-flex"
                            >
                                Masuk</Link
                            >
                            <Link
                                :href="'/register'"
                                class="hidden min-h-10 items-center rounded-md bg-article-primary px-4 text-sm font-semibold text-white transition-colors hover:bg-article-primary-dark md:inline-flex"
                            >
                                Daftar</Link
                            >
                        </template>
                    </div>
                </div>

                <!-- Baris 2 (mobile saja) -->
                <div class="flex h-16 items-center gap-2 lg:hidden">
                    <!-- Toko (katalog): icon company saja + search + cart -->
                    <template v-if="isCatalogPage">
                        <Link
                            :href="homeRoute().url"
                            class="flex min-w-0 shrink-0 items-center"
                            :aria-label="`${storeName} — beranda`"
                        >
                            <img
                                v-if="storeLogoUrl"
                                :src="storeLogoUrl"
                                :alt="storeName"
                                class="h-8 w-auto object-contain"
                            />
                            <BookOpen
                                v-else
                                class="h-8 w-8 text-article-primary"
                                aria-hidden="true"
                            />
                        </Link>

                        <!-- Search bar (mobile toko) -->
                        <label class="relative block min-w-0 flex-1">
                            <span class="sr-only">Cari judul / penulis</span>
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-article-muted"
                                aria-hidden="true"
                            />
                            <input
                                v-model="headerSearch"
                                type="search"
                                :placeholder="searchPlaceholder"
                                class="min-h-10 w-full rounded-lg border border-article-border bg-article-surface py-2 pr-9 pl-9 text-sm transition-colors outline-none placeholder:text-article-muted focus:border-article-primary focus:ring-2 focus:ring-article-primary/20"
                                @keyup.enter="submitSearch"
                            />
                            <button
                                v-if="headerSearch"
                                type="button"
                                class="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded text-article-muted transition-colors hover:text-article-ink"
                                aria-label="Bersihkan pencarian"
                                @click="clearSearch"
                            >
                                <X class="size-4" aria-hidden="true" />
                            </button>
                        </label>

                        <!-- Cart di kanan -->
                        <Link
                            :href="'/checkout'"
                            class="relative flex size-10 shrink-0 items-center justify-center rounded-lg text-article-ink transition-colors hover:bg-article-border/50"
                            :aria-label="`Keranjang, ${cartCount} item`"
                        >
                            <ShoppingCart class="size-5" aria-hidden="true" />
                            <span
                                v-if="cartCount > 0"
                                class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-article-primary text-[10px] font-bold text-white"
                                aria-hidden="true"
                                >{{ cartCount }}</span
                            >
                        </Link>
                    </template>

                    <!-- Detail buku / checkout / pesanan: logo + nama (tanpa search) -->
                    <template v-else-if="isNoSearchPage">
                        <Link
                            :href="homeRoute().url"
                            class="flex min-w-0 shrink-0 items-center gap-2"
                            :aria-label="`${storeName} — beranda`"
                        >
                            <img
                                v-if="storeLogoUrl"
                                :src="storeLogoUrl"
                                :alt="storeName"
                                class="h-8 w-auto object-contain"
                            />
                            <BookOpen
                                v-else
                                class="h-8 w-8 text-article-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="truncate text-sm font-extrabold tracking-tight"
                                >{{ storeName }}</span
                            >
                        </Link>

                        <div class="ml-auto flex shrink-0 items-center">
                            <!-- Cart di kanan — hanya detail buku (checkout sudah di halaman cart) -->
                            <Link
                                v-if="isBookDetailPage"
                                :href="'/checkout'"
                                class="relative flex size-10 items-center justify-center rounded-lg text-article-ink transition-colors hover:bg-article-border/50"
                                :aria-label="`Keranjang, ${cartCount} item`"
                            >
                                <ShoppingCart
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                <span
                                    v-if="cartCount > 0"
                                    class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-article-primary text-[10px] font-bold text-white"
                                    aria-hidden="true"
                                    >{{ cartCount }}</span
                                >
                            </Link>
                        </div>
                    </template>

                    <!-- Artikel/beranda: logo + nama + icon search -->
                    <template v-else>
                        <Link
                            :href="homeRoute().url"
                            class="flex min-w-0 shrink-0 items-center gap-2"
                            :aria-label="`${storeName} — beranda`"
                        >
                            <img
                                v-if="storeLogoUrl"
                                :src="storeLogoUrl"
                                :alt="storeName"
                                class="h-8 w-auto object-contain"
                            />
                            <BookOpen
                                v-else
                                class="h-8 w-8 text-article-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="truncate text-sm font-extrabold tracking-tight"
                                >{{ storeName }}</span
                            >
                        </Link>

                        <div class="ml-auto flex shrink-0 items-center">
                            <!-- Ikon pencarian — buka bar di bawah -->
                            <button
                                type="button"
                                class="flex size-10 items-center justify-center rounded-lg transition-colors hover:bg-article-border/50"
                                :aria-label="
                                    mobileSearchOpen
                                        ? 'Tutup pencarian'
                                        : 'Buka pencarian'
                                "
                                :aria-expanded="mobileSearchOpen"
                                aria-controls="ed-mobile-search-row"
                                @click="toggleMobileSearch"
                            >
                                <X
                                    v-if="mobileSearchOpen"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                <Search
                                    v-else
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Bar pencarian mobile (toggle) — hanya di luar halaman toko -->
            <div
                v-if="mobileSearchOpen && !isCatalogPage"
                id="ed-mobile-search-row"
                class="border-t border-article-border lg:hidden"
            >
                <div class="mx-auto max-w-6xl px-4 py-3">
                    <label class="relative block">
                        <span class="sr-only">Cari buku atau artikel</span>
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-article-muted"
                            aria-hidden="true"
                        />
                        <input
                            id="ed-search-mobile"
                            v-model="headerSearch"
                            type="search"
                            :placeholder="searchPlaceholder"
                            class="min-h-12 w-full rounded-lg border border-article-border bg-article-surface pr-9 pl-9 text-sm transition-colors outline-none placeholder:text-article-muted focus:border-article-primary focus:ring-2 focus:ring-article-primary/20"
                            @keyup.enter="submitSearch"
                        />
                        <button
                            v-if="headerSearch"
                            type="button"
                            class="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded text-article-muted transition-colors hover:text-article-ink"
                            aria-label="Bersihkan pencarian"
                            @click="clearSearch"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </label>
                </div>
            </div>
        </header>

        <!-- ══════════ KONTEN ══════════ -->
        <main id="main" class="flex-1">
            <slot />
        </main>

        <!-- ══════════ FOOTER ══════════ -->
        <footer
            class="border-t border-article-border bg-article-surface pb-18 lg:pb-0"
        >
            <div class="mx-auto max-w-6xl px-4">
                <div
                    class="flex flex-col gap-6 py-6 lg:flex-row lg:items-start lg:justify-between lg:gap-12"
                >
                    <!-- Info toko & alamat — rata kiri -->
                    <div class="max-w-sm text-left">
                        <p
                            class="text-sm font-bold tracking-tight text-article-ink"
                        >
                            {{ storeName }}
                        </p>
                        <p
                            v-if="storeAddress"
                            class="mt-1.5 text-xs leading-relaxed text-article-muted"
                        >
                            {{ storeAddress }}
                        </p>
                        <p
                            v-if="storePhone"
                            class="mt-1 text-xs text-article-muted"
                        >
                            {{ storePhone }}
                        </p>
                    </div>

                    <!-- Daftar menu — dua kolom di mobile, rata kanan di
                         desktop; memastikan semua halaman (termasuk Promo)
                         selalu terjangkau dari navigasi -->
                    <nav
                        class="flex shrink-0 gap-12 sm:gap-16"
                        aria-label="Menu footer"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-article-ink uppercase"
                            >
                                Jelajahi
                            </p>
                            <ul class="mt-3 space-y-2 text-left">
                                <li>
                                    <Link
                                        :href="homeRoute().url"
                                        class="text-xs text-article-muted transition-colors hover:text-article-ink"
                                    >
                                        Beranda
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        :href="catalogUrl().url"
                                        class="text-xs text-article-muted transition-colors hover:text-article-ink"
                                    >
                                        Katalog Buku
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        :href="promoRoute().url"
                                        class="text-xs text-article-muted transition-colors hover:text-article-ink"
                                    >
                                        Promo
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        :href="aboutRoute().url"
                                        class="text-xs text-article-muted transition-colors hover:text-article-ink"
                                    >
                                        Tentang Kami
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Kolom akun — hanya customer yang login -->
                        <div v-if="user && !isAdmin">
                            <p
                                class="text-xs font-semibold tracking-wider text-article-ink uppercase"
                            >
                                Akun
                            </p>
                            <ul class="mt-3 space-y-2 text-left">
                                <li>
                                    <Link
                                        :href="myOrdersRoute().url"
                                        class="text-xs text-article-muted transition-colors hover:text-article-ink"
                                    >
                                        Pesanan Saya
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        :href="storefrontProfileEdit().url"
                                        class="text-xs text-article-muted transition-colors hover:text-article-ink"
                                    >
                                        Profil Saya
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Copyright — garis pemisah hanya setengah, di tengah -->
            <p
                class="relative px-4 py-2 text-center text-xs text-article-muted before:absolute before:top-0 before:left-1/2 before:h-px before:w-1/2 before:-translate-x-1/2 before:bg-article-border"
            >
                © {{ new Date().getFullYear() }} {{ storeName }}.
            </p>
        </footer>

        <!-- ══════════ BOTTOM NAV (mobile only) ══════════ -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-article-border bg-article-surface lg:hidden"
            aria-label="Navigasi bawah"
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
                                'rounded-lg bg-article-primary/10',
                            isBottomNavActive(item)
                                ? 'text-article-primary'
                                : 'text-article-muted',
                        ]"
                    >
                        <component
                            :is="item.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.badge && item.badge > 0"
                            class="absolute -top-0.5 right-1 inline-flex size-4 items-center justify-center rounded-full bg-article-primary text-[10px] font-semibold text-white"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </template>

                <!-- Notifikasi → dropdown — di luar v-for ber-key (stabil) -->
                <NotificationsMenu v-if="user && !isAdmin" side="top">
                    <template #trigger="{ count }">
                        <button
                            type="button"
                            class="relative flex flex-col items-center gap-0.5 px-3 py-1.5 text-[10px] font-medium transition-colors"
                            :class="
                                count > 0
                                    ? 'text-article-primary'
                                    : 'text-article-muted'
                            "
                        >
                            <BellRing class="size-5" aria-hidden="true" />
                            <span>Notifikasi</span>
                            <span
                                v-if="count > 0"
                                class="absolute -top-0.5 right-1 inline-flex size-4 items-center justify-center rounded-full bg-article-primary text-[10px] font-semibold text-white"
                            >
                                {{ count > 9 ? '9+' : count }}
                            </span>
                        </button>
                    </template>
                </NotificationsMenu>

                <!-- Profil → dropdown — di luar v-for ber-key (stabil),
                     menghindari crash Vue pada dropdown yang teleport. -->
                <template v-if="user">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-[10px] font-medium transition-colors"
                                :class="
                                    isProfilActive
                                        ? 'text-article-primary'
                                        : 'text-article-muted'
                                "
                            >
                                <span
                                    class="flex size-6 items-center justify-center rounded-full bg-article-primary text-[10px] font-bold text-white"
                                    :class="
                                        isProfilActive &&
                                        'ring-2 ring-article-primary/30'
                                    "
                                >
                                    {{ initials }}
                                </span>
                                <span>Profil</span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            side="top"
                            :side-offset="8"
                            class="min-w-44"
                        >
                            <DropdownMenuItem v-if="isAdmin" as-child>
                                <Link :href="adminDashboard().url">
                                    Dashboard
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="'/profil'"> Profil Saya </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="aboutRoute().url"> Tentang </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
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
                                    <LogOut class="size-4" aria-hidden="true" />
                                    Logout
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
                <template v-else>
                    <!-- Guest: langsung tombol login -->
                    <Link
                        :href="'/login'"
                        class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-[10px] font-medium transition-colors"
                        :class="
                            isProfilActive
                                ? 'text-article-primary'
                                : 'text-article-muted'
                        "
                    >
                        <LogIn class="size-5" aria-hidden="true" />
                        <span>Masuk</span>
                    </Link>
                </template>
            </div>
        </nav>
    </div>
</template>
