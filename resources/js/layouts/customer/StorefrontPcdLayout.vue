<script setup lang="ts">
/**
 * Layout storefront "Pustaka Cahaya Peradaban" — /pcd/**.
 *
 * Desain Flat: blok warna tegas, tanpa bayangan, tipografi geometris
 * (Outfit). Menu lengkap: Artikel, Toko, Tentang, Keranjang (checkout),
 * Pesanan Saya, dan Profil (akun/alamat/logout).
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, LogOut, MapPin, Search, ShoppingCart, User as UserIcon, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { logout } from '@/routes';
import { about as aboutRoute, home as homeRoute } from '@/routes/pcd';
import { catalog as catalogUrl } from '@/routes/pcd/books';
import { index as myOrdersRoute } from '@/routes/pcd/my-orders';
import { edit as profileRoute } from '@/routes/pcd/profile';
import type { User } from '@/types';

const page = usePage();
const storeName = computed(() =>
    String(page.props.storeName ?? 'Pustaka Cahaya Peradaban'),
);
const cartCount = computed(() => Number(page.props.cartCount ?? 0));
const user = computed<User | null>(() => page.props.auth?.user ?? null);
const isCatalogPage = computed(
    () => page.component === 'storefront-pcd/Catalog',
);
const isHomePage = computed(() => page.component === 'storefront-pcd/Home');
const homeUrl = computed(() => homeRoute().url);
const catalogHref = computed(() => catalogUrl().url);
const aboutHref = computed(() => aboutRoute().url);
const myOrdersHref = computed(() => myOrdersRoute().url);
const profileHref = computed(() => profileRoute().url);

const menuOpen = ref(false);
const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

function closeUserMenu(): void {
    userMenuOpen.value = false;
}

function handleClickOutside(event: MouseEvent): void {
    if (
        userMenuRef.value &&
        !userMenuRef.value.contains(event.target as Node)
    ) {
        closeUserMenu();
    }
}

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

watch(userMenuOpen, (open) => {
    if (open) {
        document.addEventListener('mousedown', handleClickOutside);
    } else {
        document.removeEventListener('mousedown', handleClickOutside);
    }
});

// ── Pencarian artikel (header) — hanya di beranda, sinkron dari server ──
const homeFilters = computed<Record<string, unknown>>(
    () => (page.props.filters ?? {}) as Record<string, unknown>,
);
const headerSearch = ref('');
const searchOpen = ref(false);
const mobileSearchInput = ref<HTMLInputElement | null>(null);
const searchError = computed(
    () =>
        ((page.props.errors as Record<string, string> | undefined) ?? {})
            .search ?? '',
);

// Sinkron dari server (navigasi balik/maju, submit filter) tanpa memicu
// request ganda: nilai yang baru saja dipancarkan dilewati oleh debounce.
let lastEmittedSearch: string | null = null;
watch(
    () => [page.component, homeFilters.value.search],
    () => {
        if (!isHomePage.value) {
            return;
        }

        headerSearch.value =
            typeof homeFilters.value.search === 'string'
                ? homeFilters.value.search
                : '';
        lastEmittedSearch = headerSearch.value || null;
    },
    { immediate: true },
);

// Perubahan input → request Inertia debounce (filter tetap terlihat saat scroll).
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(headerSearch, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (!isHomePage.value) {
            return;
        }

        const next = value || null;

        if (next === lastEmittedSearch) {
            return;
        }

        lastEmittedSearch = next;
        router.get(
            homeUrl.value,
            { search: value || undefined },
            {
                preserveState: true,
                replace: true,
                only: ['articles', 'filters'],
            },
        );
    }, 350);
});

function clearSearch(): void {
    headerSearch.value = '';
}

function toggleSearch(): void {
    searchOpen.value = !searchOpen.value;

    if (searchOpen.value) {
        nextTick(() => mobileSearchInput.value?.focus());
    }
}

function handleLogout(): void {
    router.flushAll();
    router.post(logout().url);
}
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-white text-flat-ink antialiased"
        style="font-family: var(--font-flat)"
    >
        <Toaster position="top-center" :rich-colors="false" />

        <a
            href="#main"
            class="sr-only z-50 rounded-md bg-flat-ink px-4 py-3 text-sm font-medium text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Langsung ke konten
        </a>

        <!-- ══════════ HEADER ══════════ -->
        <header
            class="sticky top-0 z-40 border-b border-flat-border bg-white"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center gap-2 px-4 md:h-[72px] md:px-6"
            >
                <Link
                    :href="homeUrl"
                    class="flex min-w-0 items-center gap-2.5"
                    aria-label="Pustaka Cahaya Peradaban — beranda"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-flat-primary"
                    >
                        <BookOpen
                            class="size-4 text-white"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="truncate text-[17px] font-extrabold tracking-tight"
                        >{{ storeName }}</span
                    >
                </Link>

                <!-- Pencarian artikel (desktop) -->
                <div
                    v-if="isHomePage"
                    class="relative ml-3 hidden md:block md:w-56 lg:w-72"
                >
                    <label class="sr-only" for="pcd-header-search"
                        >Cari artikel</label
                    >
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                        aria-hidden="true"
                    />
                    <input
                        id="pcd-header-search"
                        v-model="headerSearch"
                        type="search"
                        placeholder="Cari artikel…"
                        class="min-h-11 w-full rounded-md border-2 border-transparent bg-flat-muted pr-9 pl-9 text-sm transition-colors outline-none placeholder:text-gray-400 focus:border-flat-primary focus:bg-white"
                    />
                    <button
                        v-if="headerSearch"
                        type="button"
                        class="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-500 transition-colors hover:text-flat-ink"
                        aria-label="Bersihkan pencarian"
                        @click="clearSearch"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                    <p v-if="searchError" class="mt-1 text-xs text-red-700">
                        {{ searchError }}
                    </p>
                </div>

                <nav
                    class="ml-auto hidden items-center gap-1 text-sm lg:flex"
                    aria-label="Navigasi utama"
                >
                    <Link
                        :href="homeUrl"
                        class="rounded-md px-3 py-1.5 font-semibold text-flat-ink transition-colors hover:bg-flat-muted"
                        aria-current="page"
                        >Artikel</Link
                    >
                    <Link
                        :href="catalogHref"
                        class="rounded-md px-3 py-1.5 text-gray-500 transition-colors hover:bg-flat-muted hover:text-flat-ink"
                        :class="{ 'font-semibold text-flat-ink': isCatalogPage }"
                        >Toko</Link
                    >
                    <Link
                        :href="aboutHref"
                        class="rounded-md px-3 py-1.5 text-gray-500 transition-colors hover:bg-flat-muted hover:text-flat-ink"
                        >Tentang</Link
                    >
                </nav>

                <div class="ml-auto flex items-center lg:ml-0">
                    <Link
                        href="/pcd/checkout"
                        class="relative flex size-12 items-center justify-center rounded-md transition-colors hover:bg-flat-muted"
                        :aria-label="`Keranjang, ${cartCount} item`"
                    >
                        <ShoppingCart class="size-5" aria-hidden="true" />
                        <span
                            v-if="cartCount > 0"
                            class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-flat-primary text-[10px] font-bold text-white"
                            aria-hidden="true"
                            >{{ cartCount }}</span
                        >
                    </Link>

                    <!-- User terautentikasi (desktop) -->
                    <div
                        v-if="user"
                        ref="userMenuRef"
                        class="relative hidden md:block"
                    >
                        <button
                            type="button"
                            class="ml-1 flex size-10 items-center justify-center rounded-full bg-flat-primary text-sm font-bold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none"
                            :aria-label="`Menu ${user.name}`"
                            :aria-expanded="userMenuOpen"
                            aria-haspopup="menu"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            {{ initials }}
                        </button>
                        <div
                            v-if="userMenuOpen"
                            class="absolute top-full right-0 z-50 mt-2 w-60 overflow-hidden rounded-lg border-2 border-flat-border bg-white"
                            role="menu"
                        >
                            <div class="border-b-2 border-flat-border px-4 py-3">
                                <p class="truncate text-sm font-bold">
                                    {{ user.name }}
                                </p>
                                <p class="truncate text-xs text-gray-500">
                                    {{ user.email }}
                                </p>
                            </div>
                            <Link
                                :href="myOrdersHref"
                                class="flex min-h-11 items-center gap-2.5 px-4 text-sm transition-colors hover:bg-flat-muted"
                                role="menuitem"
                                @click="closeUserMenu"
                            >
                                <UserIcon class="size-4" aria-hidden="true" />
                                Pesanan Saya
                            </Link>
                            <Link
                                :href="profileHref"
                                class="flex min-h-11 items-center gap-2.5 px-4 text-sm transition-colors hover:bg-flat-muted"
                                role="menuitem"
                                @click="closeUserMenu"
                            >
                                <BookOpen class="size-4" aria-hidden="true" />
                                Profil
                            </Link>
                            <Link
                                :href="profileHref + '#alamat'"
                                class="flex min-h-11 items-center gap-2.5 px-4 text-sm transition-colors hover:bg-flat-muted"
                                role="menuitem"
                                @click="closeUserMenu"
                            >
                                <MapPin class="size-4" aria-hidden="true" />
                                Alamat
                            </Link>
                            <button
                                type="button"
                                class="flex min-h-11 w-full items-center gap-2.5 border-t-2 border-flat-border px-4 text-sm text-red-700 transition-colors hover:bg-flat-muted"
                                role="menuitem"
                                @click="handleLogout"
                            >
                                <LogOut class="size-4" aria-hidden="true" />
                                Logout
                            </button>
                        </div>
                    </div>

                    <!-- Tamu (desktop) -->
                    <template v-else>
                        <Link
                            href="/login"
                            class="hidden min-h-12 items-center rounded-md px-3 text-sm font-semibold text-gray-500 transition-colors hover:bg-flat-muted hover:text-flat-ink md:inline-flex"
                            >Masuk</Link
                        >
                        <Link
                            href="/register"
                            class="hidden min-h-12 items-center rounded-md bg-flat-primary px-4 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark md:inline-flex"
                            >Daftar</Link
                        >
                    </template>

                    <button
                        v-if="isHomePage"
                        type="button"
                        class="flex size-12 items-center justify-center rounded-md transition-colors hover:bg-flat-muted lg:hidden"
                        :aria-label="
                            searchOpen ? 'Tutup pencarian' : 'Buka pencarian'
                        "
                        :aria-expanded="searchOpen"
                        aria-controls="pcd-search-row"
                        @click="toggleSearch"
                    >
                        <Search class="size-5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="flex size-12 items-center justify-center rounded-md transition-colors hover:bg-flat-muted lg:hidden"
                        :aria-label="menuOpen ? 'Tutup menu' : 'Buka menu'"
                        :aria-expanded="menuOpen"
                        aria-controls="pcd-mobile-menu"
                        @click="menuOpen = !menuOpen"
                    >
                        <X v-if="menuOpen" class="size-5" aria-hidden="true" />
                        <BookOpen v-else class="size-5" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Baris pencarian mobile (di bawah header, mendorong konten) -->
            <div
                v-if="searchOpen && isHomePage"
                id="pcd-search-row"
                class="border-t border-flat-border lg:hidden"
            >
                <div class="mx-auto max-w-7xl px-4 py-3">
                    <label class="relative block">
                        <span class="sr-only">Cari artikel</span>
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                            aria-hidden="true"
                        />
                        <input
                            ref="mobileSearchInput"
                            v-model="headerSearch"
                            type="search"
                            placeholder="Cari judul, kata kunci, isi artikel…"
                            class="min-h-12 w-full rounded-md border-2 border-transparent bg-flat-muted pr-10 pl-9 text-sm transition-colors outline-none placeholder:text-gray-400 focus:border-flat-primary focus:bg-white"
                        />
                        <button
                            v-if="headerSearch"
                            type="button"
                            class="absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-gray-500 transition-colors hover:text-flat-ink"
                            aria-label="Bersihkan pencarian"
                            @click="clearSearch"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </label>
                    <p v-if="searchError" class="mt-2 text-xs text-red-700">
                        {{ searchError }}
                    </p>
                </div>
            </div>

            <div
                v-if="menuOpen"
                id="pcd-mobile-menu"
                class="border-t border-flat-border lg:hidden"
            >
                <nav
                    class="mx-auto max-w-7xl px-4 py-2"
                    aria-label="Navigasi mobile"
                >
                    <Link
                        :href="homeUrl"
                        class="flex min-h-12 items-center border-b-2 border-flat-border font-semibold text-flat-ink"
                        @click="menuOpen = false"
                        >Artikel</Link
                    >
                    <Link
                        :href="catalogHref"
                        class="flex min-h-12 items-center border-b-2 border-flat-border text-gray-500"
                        :class="{ 'font-semibold text-flat-ink': isCatalogPage }"
                        @click="menuOpen = false"
                        >Toko</Link
                    >
                    <Link
                        :href="aboutHref"
                        class="flex min-h-12 items-center border-b-2 border-flat-border text-gray-500"
                        @click="menuOpen = false"
                        >Tentang</Link
                    >
                    <Link
                        href="/pcd/checkout"
                        class="flex min-h-12 items-center border-b-2 border-flat-border text-gray-500"
                        @click="menuOpen = false"
                    >
                        Keranjang
                        <span
                            v-if="cartCount > 0"
                            class="ml-2 inline-flex size-5 items-center justify-center rounded-full bg-flat-primary text-[11px] font-bold text-white"
                            >{{ cartCount }}</span
                        >
                    </Link>
                    <template v-if="user">
                        <Link
                            :href="myOrdersHref"
                            class="flex min-h-12 items-center border-b-2 border-flat-border text-gray-500"
                            @click="menuOpen = false"
                            >Pesanan Saya</Link
                        >
                        <Link
                            :href="profileHref"
                            class="flex min-h-12 items-center border-b-2 border-flat-border text-gray-500"
                            @click="menuOpen = false"
                            >Profil</Link
                        >
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center text-left text-red-700"
                            @click="menuOpen = false"
                        >
                            <span class="w-full" @click="handleLogout"
                                >Logout</span
                            >
                        </button>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="flex min-h-12 items-center border-b-2 border-flat-border text-gray-500"
                            @click="menuOpen = false"
                            >Masuk</Link
                        >
                        <Link
                            href="/register"
                            class="flex min-h-12 items-center font-semibold text-flat-primary"
                            @click="menuOpen = false"
                            >Daftar</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <!-- ══════════ KONTEN ══════════ -->
        <main id="main" class="flex-1">
            <slot />
        </main>

        <!-- ══════════ FOOTER ══════════ -->
        <footer class="border-t border-flat-border bg-flat-muted">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center px-4 pt-12 pb-14 text-center"
            >
                <Link
                    :href="homeUrl"
                    class="flex items-center gap-2.5"
                    aria-label="Pustaka Cahaya Peradaban — beranda"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-flat-primary"
                    >
                        <BookOpen
                            class="size-3.5 text-white"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="font-extrabold tracking-tight">{{
                        storeName
                    }}</span>
                </Link>
                <nav
                    class="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-gray-500"
                    aria-label="Navigasi kaki"
                >
                    <Link
                        :href="homeUrl"
                        class="transition-colors hover:text-flat-ink"
                        >Artikel</Link
                    >
                    <Link
                        :href="catalogHref"
                        class="transition-colors hover:text-flat-ink"
                        >Toko</Link
                    >
                    <Link
                        :href="aboutHref"
                        class="transition-colors hover:text-flat-ink"
                        >Tentang</Link
                    >
                    <template v-if="user">
                        <Link
                            :href="myOrdersHref"
                            class="transition-colors hover:text-flat-ink"
                            >Pesanan Saya</Link
                        >
                        <Link
                            :href="profileHref"
                            class="transition-colors hover:text-flat-ink"
                            >Profil</Link
                        >
                    </template>
                </nav>
                <p class="mt-8 text-xs text-gray-500">
                    © 2026 {{ storeName }}
                </p>
            </div>
        </footer>
    </div>
</template>
