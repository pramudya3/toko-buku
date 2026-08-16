<script setup lang="ts">
/**
 * Layout storefront proto-d ("Pustaka Cahaya Peradaban") — /pcd/**.
 *
 * Ringan & tenang: wordmark serif + nav Artikel/Toko/Tentang + keranjang,
 * tanpa notifikasi / chrome admin. Data bersama diambil dari shared props
 * (cartCount, storeName) yang sama dengan storefront lama.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ShoppingCart, Sun, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { about as aboutRoute, home as homeRoute } from '@/routes/pcd';
import { catalog as catalogUrl } from '@/routes/pcd/books';
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
const homeUrl = computed(() => homeRoute().url);
const catalogHref = computed(() => catalogUrl().url);
const aboutHref = computed(() => aboutRoute().url);

const menuOpen = ref(false);
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-pcd-paper font-sans text-pcd-ink antialiased"
    >
        <Toaster position="top-center" :rich-colors="false" />

        <a
            href="#main"
            class="sr-only z-50 rounded-lg bg-pcd-ink px-4 py-3 text-sm font-medium text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Langsung ke konten
        </a>

        <!-- ══════════ HEADER ══════════ -->
        <header
            class="sticky top-0 z-40 border-b border-pcd-hairline bg-pcd-paper/95 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4 md:h-[72px] md:px-6"
            >
                <Link
                    :href="homeUrl"
                    class="flex min-w-0 items-center gap-2.5"
                    aria-label="Pustaka Cahaya Peradaban — beranda"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-pcd-ink"
                    >
                        <Sun
                            class="size-4 text-pcd-accent"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="truncate font-serif text-[17px] font-semibold tracking-tight"
                        >{{ storeName }}</span
                    >
                </Link>

                <nav
                    class="ml-auto hidden items-center gap-7 text-sm md:flex"
                    aria-label="Navigasi utama"
                >
                    <Link
                        :href="homeUrl"
                        class="font-semibold text-pcd-ink"
                        aria-current="page"
                        >Artikel</Link
                    >
                    <Link
                        :href="catalogHref"
                        class="text-pcd-muted transition-colors hover:text-pcd-ink"
                        :class="{ 'font-semibold text-pcd-ink': isCatalogPage }"
                        >Toko</Link
                    >
                    <Link
                        :href="aboutHref"
                        class="text-pcd-muted transition-colors hover:text-pcd-ink"
                        >Tentang</Link
                    >
                </nav>

                <div class="ml-auto flex items-center md:ml-0">
                    <template v-if="!user">
                        <Link
                            href="/login"
                            class="hidden min-h-12 items-center px-2 text-sm font-medium text-pcd-muted transition-colors hover:text-pcd-ink sm:inline-flex"
                            >Masuk</Link
                        >
                    </template>
                    <Link
                        href="/pcd/checkout"
                        class="relative flex size-12 items-center justify-center rounded-lg transition-colors hover:bg-pcd-ink/5"
                        :aria-label="`Keranjang, ${cartCount} item`"
                    >
                        <ShoppingCart class="size-5" aria-hidden="true" />
                        <span
                            v-if="cartCount > 0"
                            class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-pcd-accent-strong text-[10px] font-semibold text-white"
                            aria-hidden="true"
                            >{{ cartCount }}</span
                        >
                    </Link>
                    <button
                        type="button"
                        class="flex size-12 items-center justify-center rounded-lg transition-colors hover:bg-pcd-ink/5 md:hidden"
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

            <div
                v-if="menuOpen"
                id="pcd-mobile-menu"
                class="border-t border-pcd-hairline md:hidden"
            >
                <nav
                    class="mx-auto max-w-6xl px-4 py-2"
                    aria-label="Navigasi mobile"
                >
                    <Link
                        :href="homeUrl"
                        class="flex min-h-12 items-center border-b border-pcd-hairline font-medium text-pcd-ink"
                        @click="menuOpen = false"
                        >Artikel</Link
                    >
                    <Link
                        :href="catalogHref"
                        class="flex min-h-12 items-center border-b border-pcd-hairline text-pcd-muted"
                        @click="menuOpen = false"
                        >Toko</Link
                    >
                    <Link
                        :href="aboutHref"
                        class="flex min-h-12 items-center text-pcd-muted"
                        @click="menuOpen = false"
                        >Tentang</Link
                    >
                </nav>
            </div>
        </header>

        <!-- ══════════ KONTEN ══════════ -->
        <main id="main" class="flex-1">
            <slot />
        </main>

        <!-- ══════════ FOOTER ══════════ -->
        <footer class="border-t border-pcd-hairline">
            <div
                class="mx-auto flex max-w-2xl flex-col items-center px-4 pt-14 pb-16 text-center"
            >
                <Link
                    :href="homeUrl"
                    class="flex items-center gap-2.5"
                    aria-label="Pustaka Cahaya Peradaban — beranda"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-pcd-ink"
                    >
                        <Sun
                            class="size-3.5 text-pcd-accent"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="font-serif font-semibold tracking-tight">{{
                        storeName
                    }}</span>
                </Link>
                <nav
                    class="mt-6 flex items-center gap-6 text-sm text-pcd-muted"
                    aria-label="Navigasi kaki"
                >
                    <Link
                        :href="homeUrl"
                        class="transition-colors hover:text-pcd-ink"
                        >Artikel</Link
                    >
                    <Link
                        :href="catalogHref"
                        class="transition-colors hover:text-pcd-ink"
                        >Toko</Link
                    >
                    <Link
                        :href="aboutHref"
                        class="transition-colors hover:text-pcd-ink"
                        >Tentang</Link
                    >
                </nav>
                <p class="mt-8 text-xs text-pcd-muted">
                    © 2026 {{ storeName }}
                </p>
            </div>
        </footer>
    </div>
</template>
