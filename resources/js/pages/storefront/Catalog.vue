<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import {
    BookX,
    ChevronLeft,
    ChevronRight,
    LayoutGrid,
    List,
    Loader2,
    ShoppingBag,
} from '@lucide/vue';
import { useResizeObserver } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { formatDateID } from '@/lib/date';
import { bookShowUrl } from '@/lib/slug';
import {
    catalog as catalogUrl,
    loadMore as loadMoreUrl,
    show as showRoute,
} from '@/routes/books';

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    is_preorder: boolean;
    preorder_eta: string | null;
    category: { id: string; nama: string } | null;
    price_breakdown?: {
        original_price: number;
        promo_discount: number;
        final_price: number;
        promo_name: string | null;
    } | null;
};

type BundleBook = {
    id: string;
    judul: string;
    cover_url: string | null;
    price_original: number;
    unit_price: number;
    unit_discount: number;
    unit_final: number;
    stok: number;
};

type Bundle = {
    id: string;
    promo_name: string;
    discount_percent: number;
    books: BundleBook[];
    total_original: number;
    total_discount: number;
    total_final: number;
};

type Promo = {
    id: string;
    promo_name: string;
    promo_type: 'percentage' | 'fixed';
    discount_percentage: number | null;
    promo_value: number | null;
    start_date: string;
    end_date: string;
    is_global: boolean;
    books: Array<{
        id: string;
        judul: string;
        cover_url: string | null;
    }>;
};

type Props = {
    books: {
        data: Book[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    categories: Array<{ id: string; nama: string }>;
    bundles: Bundle[];
    promos: Promo[];
    filters: {
        search?: string;
        category_id?: string;
        stok?: string;
    };
};

defineOptions({
    layout: CustomerLayout,
});

const props = defineProps<Props>();

// Paket bundle yang sedang dibuka di modal detail.
const activeBundle = ref<Bundle | null>(null);

// Search & kategori hidup di header (CustomerLayout) — halaman ini hanya
// mengelola filter stok. Nilai search/kategori dibaca dari props server.
const stokFilter = ref<'all' | 'ready' | 'preorder' | 'empty'>(
    props.filters.stok === 'ready' ||
        props.filters.stok === 'preorder' ||
        props.filters.stok === 'empty'
        ? props.filters.stok
        : 'all',
);
const books = ref<Book[]>(props.books.data);
const currentPage = ref(props.books.current_page);
const lastPage = ref(props.books.last_page);
const loadingMore = ref(false);
const loadMoreRequest = useHttp();

// Tampilan desktop: grid atau list — preferensi disimpan di localStorage.
const viewMode = ref<'grid' | 'list'>(
    localStorage.getItem('catalog-view-mode') === 'list' ? 'list' : 'grid',
);

watch(viewMode, (mode) => {
    localStorage.setItem('catalog-view-mode', mode);
});

// Token generasi load-more — response yang sudah basi (filter berubah)
// diabaikan supaya tidak mencampur hasil lama ke daftar baru.
let loadMoreToken = 0;

let timer: ReturnType<typeof setTimeout> | undefined;

watch(stokFilter, () => {
    // Filter berubah → response load-more lama tidak berlaku lagi.
    loadMoreToken++;
    loadingMore.value = false;

    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            catalogUrl().url,
            {
                search: props.filters.search ?? undefined,
                category_id: props.filters.category_id ?? undefined,
                stok: stokFilter.value === 'all' ? undefined : stokFilter.value,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
});

// Chips kategori (mobile) — kirim langsung; select kategori di header
// tersinkron otomatis lewat props.filters.
function applyCategory(categoryId: string | null): void {
    loadMoreToken++;
    loadingMore.value = false;

    router.get(
        catalogUrl().url,
        {
            search: props.filters.search ?? undefined,
            category_id: categoryId ?? undefined,
            stok: stokFilter.value === 'all' ? undefined : stokFilter.value,
        },
        { preserveState: true, replace: true },
    );
}

// Sync daftar buku saat props berubah (search/filter reload dengan preserveState).
watch(
    () => props.books,
    (newBooks) => {
        books.value = newBooks.data;
        currentPage.value = newBooks.current_page;
        lastPage.value = newBooks.last_page;
    },
);

// Jumlah buku stok habis per paket — dihitung sekali, bukan per render.
const bundleStockCounts = computed<Record<string, number>>(() =>
    Object.fromEntries(
        props.bundles.map((bundle) => [
            bundle.id,
            bundle.books.filter((book) => book.stok <= 0).length,
        ]),
    ),
);

// Paket yang tampil sesuai filter stok:
// - Semua → semua paket
// - Tersedia → hanya paket yang semua bukunya punya stok
// - Habis → tanpa paket (fokus item stok habis)
const visibleBundles = computed(() => {
    if (stokFilter.value !== 'ready') {
        return props.bundles;
    }

    return props.bundles.filter((bundle) =>
        bundle.books.every((book) => book.stok > 0),
    );
});

// Filter kategori/search aktif → hanya item yang berkaitan; section Promo
// (paket hemat + promo per item) disembunyikan agar hasil filter tetap fokus.
const categoryActive = computed(() => Boolean(props.filters.category_id));

const showPromoSection = computed(
    () =>
        !categoryActive.value &&
        !props.filters.search &&
        (props.promos.length > 0 || visibleBundles.value.length > 0),
);

// Deskripsi empty state yang menarik — dibentuk dari filter aktif.
const activeCategoryName = computed(
    () =>
        props.categories.find(
            (category) => String(category.id) === props.filters.category_id,
        )?.nama,
);

const emptyStateDescription = computed(() => {
    const parts: string[] = [];

    if (activeCategoryName.value) {
        parts.push(`di kategori «${activeCategoryName.value}»`);
    }

    if (props.filters.search) {
        parts.push(`yang cocok dengan "${props.filters.search}"`);
    }

    if (stokFilter.value === 'ready') {
        parts.push('dengan stok tersedia');
    } else if (stokFilter.value === 'preorder') {
        parts.push('yang bisa dipesan pre-order');
    } else if (stokFilter.value === 'empty') {
        parts.push('dengan stok habis');
    }

    return `Tidak ada buku ${parts.join(' ')} — coba ubah filter atau cari kata lain.`;
});

// Buku "habis" = stok kosong DAN bukan pre-order (pre-order = orderable).
function isEmptyStock(book: Book): boolean {
    return book.stok <= 0 && !book.is_preorder;
}

function preorderEtaLabel(book: Book): string {
    return book.preorder_eta ? formatDateID(book.preorder_eta) : '';
}

function resetFilters(): void {
    clearTimeout(timer);
    stokFilter.value = 'all';

    router.get(
        catalogUrl().url,
        {},
        {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        },
    );
}

// ── Scroll horizontal Paket Hemat (desktop: tombol kiri/kanan) ──
const bundleRow = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

function updateBundleScrollState(): void {
    const el = bundleRow.value;

    if (!el) {
        return;
    }

    canScrollLeft.value = el.scrollLeft > 4;
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
}

useResizeObserver(bundleRow, () => updateBundleScrollState());

function scrollBundles(direction: -1 | 1): void {
    const el = bundleRow.value;

    if (!el) {
        return;
    }

    const card = el.querySelector<HTMLElement>('[data-bundle-card]');
    const step = (card?.offsetWidth ?? 280) + 16;

    el.scrollBy({ left: direction * step, behavior: 'smooth' });
}

// ── Scroll horizontal section Promo (unit promos) ──
const promoRow = ref<HTMLElement | null>(null);
const canPromoScrollLeft = ref(false);
const canPromoScrollRight = ref(false);

function updatePromoScrollState(): void {
    const el = promoRow.value;

    if (!el) {
        return;
    }

    canPromoScrollLeft.value = el.scrollLeft > 4;
    canPromoScrollRight.value =
        el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
}

useResizeObserver(promoRow, () => updatePromoScrollState());

function scrollPromos(direction: -1 | 1): void {
    const el = promoRow.value;

    if (!el) {
        return;
    }

    const card = el.querySelector<HTMLElement>('[data-promo-card]');
    const step = (card?.offsetWidth ?? 320) + 16;

    el.scrollBy({ left: direction * step, behavior: 'smooth' });
}

// Label diskon promo — persentase atau harga tetap.
function promoBadge(promo: Promo): string {
    return promo.promo_type === 'percentage'
        ? `-${promo.discount_percentage ?? 0}%`
        : 'Harga tetap';
}

// Persentase diskon untuk badge — dihitung dari price_breakdown (0 jika tidak ada promo).
function discountPercent(book: Book): number {
    const breakdown = book.price_breakdown;

    if (!breakdown?.promo_discount || !breakdown.original_price) {
        return 0;
    }

    return Math.round(
        (breakdown.promo_discount / breakdown.original_price) * 100,
    );
}

function loadMore() {
    if (loadingMore.value || currentPage.value >= lastPage.value) {
        return;
    }

    loadingMore.value = true;
    const token = ++loadMoreToken;

    loadMoreRequest.get(
        loadMoreUrl({
            query: {
                page: currentPage.value + 1,
                search: props.filters.search ?? undefined,
                category_id: props.filters.category_id ?? undefined,
                stok: stokFilter.value === 'all' ? undefined : stokFilter.value,
            },
        }).url,
        {
            onSuccess: (data) => {
                if (token !== loadMoreToken) {
                    return; // filter berubah — hasil ini basi
                }

                const page = data as {
                    data: Book[];
                    current_page: number;
                    last_page: number;
                };

                books.value = [...books.value, ...page.data];
                currentPage.value = page.current_page;
                lastPage.value = page.last_page;
            },
            onFinish: () => {
                if (token === loadMoreToken) {
                    loadingMore.value = false;
                }
            },
        },
    );
}
</script>

<template>
    <Head title="Katalog Buku">
        <meta
            name="description"
            content="Katalog buku toko — fiksi, non-fiksi, pendidikan, dan religi. Belanja mudah, harga bersahabat."
        />
        <meta
            property="og:title"
            content="Katalog Buku — Pustaka Cahaya Peradaban"
        />
        <meta
            property="og:description"
            content="Temukan buku berkualitas untuk semua kalangan."
        />
    </Head>

    <div class="flex flex-col gap-6">
        <!-- ── Filter stok + toggle grid/list ── -->
        <div class="flex items-center justify-between gap-3">
            <div
                class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1"
            >
                <button
                    v-for="option in [
                        { value: 'all', label: 'Semua' },
                        { value: 'ready', label: 'Tersedia' },
                        { value: 'preorder', label: 'Pre-Order' },
                        { value: 'empty', label: 'Habis' },
                    ]"
                    :key="option.value"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        stokFilter === option.value
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="
                        stokFilter = option.value as
                            'all' | 'ready' | 'preorder' | 'empty'
                    "
                >
                    {{ option.label }}
                </button>
            </div>

            <!-- Toggle grid/list — hanya desktop -->
            <div
                class="hidden items-center gap-1 rounded-lg border bg-muted/40 p-1 lg:flex"
            >
                <button
                    type="button"
                    class="rounded-md p-2 transition-colors"
                    :class="
                        viewMode === 'grid'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    title="Tampilan grid"
                    @click="viewMode = 'grid'"
                >
                    <LayoutGrid class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-2 transition-colors"
                    :class="
                        viewMode === 'list'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    title="Tampilan list"
                    @click="viewMode = 'list'"
                >
                    <List class="size-4" />
                </button>
            </div>
        </div>

        <!-- Chips kategori — mobile saja (desktop di header) -->
        <div
            v-if="categories.length"
            class="flex gap-2 overflow-x-auto pb-1 lg:hidden"
        >
            <button
                type="button"
                class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                :class="
                    !props.filters.category_id
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background text-muted-foreground'
                "
                @click="applyCategory(null)"
            >
                Semua
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                :class="
                    props.filters.category_id === String(category.id)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background text-muted-foreground'
                "
                @click="applyCategory(String(category.id))"
            >
                {{ category.nama }}
            </button>
        </div>

        <!-- ── Promo: wrapper paket hemat + promo per item ── -->
        <section
            v-if="showPromoSection"
            id="promo"
            class="flex scroll-mt-24 flex-col gap-4 rounded-xl border bg-muted/20 p-4 lg:p-5"
        >
            <div class="flex items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold tracking-tight">Promo</h2>
                    <p class="text-sm text-muted-foreground">
                        Penawaran terbaik — paket hemat & diskon per buku
                    </p>
                </div>
                <p
                    class="hidden items-center gap-0.5 text-xs text-muted-foreground lg:hidden"
                >
                    Geser untuk lihat lainnya
                    <ChevronRight class="size-3.5" />
                </p>
            </div>

            <!-- ── Paket Hemat: promo bundle aktif ── -->
            <div v-if="visibleBundles.length" class="flex flex-col gap-3">
                <h3 class="text-sm font-semibold">Paket Hemat</h3>
                <!-- Scroll horizontal (mobile & desktop) — desktop pakai tombol panah -->
                <div class="relative">
                    <div
                        ref="bundleRow"
                        class="flex snap-x scroll-px-1 [scrollbar-width:thin] gap-4 overflow-x-auto overscroll-x-contain pb-3 [&::-webkit-scrollbar]:h-1.5"
                        @scroll="updateBundleScrollState"
                    >
                        <div
                            v-for="bundle in visibleBundles"
                            :key="bundle.id"
                            data-bundle-card
                            class="flex w-[85vw] max-w-[240px] shrink-0 snap-start flex-col gap-2 rounded-xl border bg-background p-3 transition-shadow hover:shadow-md lg:w-[260px] lg:max-w-none"
                        >
                            <div class="flex items-start gap-3">
                                <!-- Cover buku paket (tumpuk) -->
                                <div class="relative">
                                    <div class="flex -space-x-3">
                                        <template
                                            v-for="book in bundle.books.slice(
                                                0,
                                                3,
                                            )"
                                            :key="book.id"
                                        >
                                            <div
                                                class="size-13 shrink-0 overflow-hidden rounded-md border bg-muted shadow-sm"
                                            >
                                                <img
                                                    v-if="book.cover_url"
                                                    :src="book.cover_url"
                                                    :alt="book.judul"
                                                    class="h-full w-full object-cover"
                                                />
                                                <BookCoverPlaceholder
                                                    v-else
                                                    :title="book.judul"
                                                    class="h-full w-full"
                                                />
                                            </div>
                                        </template>
                                    </div>
                                    <span
                                        v-if="bundle.books.length > 3"
                                        class="absolute -right-1.5 -bottom-1.5 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground shadow-sm"
                                    >
                                        +{{ bundle.books.length - 3 }}
                                    </span>
                                </div>
                                <span
                                    class="ml-auto rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                                >
                                    -{{ bundle.discount_percent }}%
                                </span>
                            </div>

                            <div>
                                <p class="font-semibold">
                                    {{ bundle.promo_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ bundle.books.length }} buku · diskon
                                    {{ bundle.discount_percent }}% saat beli
                                    semua
                                </p>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <div
                                    class="flex flex-wrap items-baseline gap-x-2"
                                >
                                    <Money
                                        :value="bundle.total_final"
                                        class="font-bold text-primary"
                                    />
                                    <Money
                                        :value="bundle.total_original"
                                        class="text-xs text-muted-foreground line-through"
                                    />
                                </div>
                                <p class="text-xs font-medium text-destructive">
                                    Hemat
                                    <Money :value="bundle.total_discount" />
                                </p>
                            </div>

                            <p
                                class="mt-auto line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{
                                    bundle.books.map((b) => b.judul).join(', ')
                                }}
                            </p>

                            <p
                                v-if="(bundleStockCounts[bundle.id] ?? 0) > 0"
                                class="text-xs font-medium text-destructive"
                            >
                                {{ bundleStockCounts[bundle.id] }} buku stok
                                habis
                            </p>

                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full"
                                @click="activeBundle = bundle"
                            >
                                Lihat Paket
                            </Button>
                        </div>
                    </div>
                    <!-- Tombol geser — desktop saja -->
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="absolute top-1/2 -left-4 z-10 hidden -translate-y-1/2 rounded-full shadow-sm lg:inline-flex"
                        :disabled="!canScrollLeft"
                        aria-label="Geser paket ke kiri"
                        @click="scrollBundles(-1)"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="absolute top-1/2 -right-4 z-10 hidden -translate-y-1/2 rounded-full shadow-sm lg:inline-flex"
                        :disabled="!canScrollRight"
                        aria-label="Geser paket ke kanan"
                        @click="scrollBundles(1)"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- ── Promo per item ── -->
            <div v-if="promos.length" class="flex flex-col gap-3">
                <h3 class="text-sm font-semibold">Promo per item</h3>
                <div class="relative">
                    <div
                        ref="promoRow"
                        class="flex snap-x scroll-px-1 [scrollbar-width:thin] gap-4 overflow-x-auto overscroll-x-contain pb-3 [&::-webkit-scrollbar]:h-1.5"
                        @scroll="updatePromoScrollState"
                    >
                        <div
                            v-for="promo in promos"
                            :key="promo.id"
                            data-promo-card
                            class="flex w-[85vw] max-w-[250px] shrink-0 snap-start flex-col gap-2 rounded-xl border bg-background p-3 transition-shadow hover:shadow-md lg:w-[270px] lg:max-w-none"
                        >
                            <div class="flex items-start gap-2">
                                <ShoppingBag
                                    class="size-4 shrink-0 text-primary"
                                />
                                <p class="min-w-0 flex-1 text-sm font-semibold">
                                    {{ promo.promo_name }}
                                </p>
                                <span
                                    class="rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                                >
                                    {{ promoBadge(promo) }}
                                </span>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                <template v-if="promo.promo_type === 'fixed'">
                                    Harga tetap
                                    <Money
                                        :value="promo.promo_value ?? 0"
                                        class="font-medium text-foreground"
                                    />
                                    per buku
                                </template>
                                <template v-else>
                                    Diskon
                                    {{ promo.discount_percentage ?? 0 }}% untuk
                                    buku-buku berikut
                                </template>
                                <span v-if="promo.is_global">
                                    — berlaku untuk semua buku
                                </span>
                            </p>

                            <p class="text-xs text-muted-foreground">
                                Berlaku s.d.
                                {{ promo.end_date }}
                            </p>

                            <div
                                v-if="promo.books.length"
                                class="mt-auto flex items-center gap-2"
                            >
                                <template
                                    v-for="book in promo.books.slice(0, 3)"
                                    :key="book.id"
                                >
                                    <Link
                                        :href="showRoute.url(bookShowUrl(book))"
                                        class="size-11 shrink-0 overflow-hidden rounded-md border bg-muted transition-shadow hover:shadow-sm"
                                        :title="book.judul"
                                    >
                                        <img
                                            v-if="book.cover_url"
                                            :src="book.cover_url"
                                            :alt="book.judul"
                                            class="h-full w-full object-cover"
                                        />
                                        <BookCoverPlaceholder
                                            v-else
                                            :title="book.judul"
                                            class="h-full w-full"
                                        />
                                    </Link>
                                </template>
                                <span
                                    v-if="promo.books.length > 3"
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    +{{ promo.books.length - 3 }} buku
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- Tombol geser — desktop saja -->
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="absolute top-1/2 -left-4 z-10 hidden -translate-y-1/2 rounded-full shadow-sm lg:inline-flex"
                        :disabled="!canPromoScrollLeft"
                        aria-label="Geser promo ke kiri"
                        @click="scrollPromos(-1)"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="absolute top-1/2 -right-4 z-10 hidden -translate-y-1/2 rounded-full shadow-sm lg:inline-flex"
                        :disabled="!canPromoScrollRight"
                        aria-label="Geser promo ke kanan"
                        @click="scrollPromos(1)"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </section>

        <!-- ── List view (desktop) ── -->
        <div
            v-if="books.length && viewMode === 'list'"
            class="flex flex-col gap-3"
        >
            <Link
                v-for="book in books"
                :key="book.id"
                :href="showRoute.url(bookShowUrl(book))"
                class="flex items-center gap-4 rounded-xl border p-3 transition-shadow hover:shadow-md"
                :class="isEmptyStock(book) && 'opacity-60 saturate-50'"
            >
                <div
                    class="relative size-14 shrink-0 overflow-hidden rounded-md border bg-muted sm:size-16"
                >
                    <span
                        v-if="discountPercent(book) > 0"
                        class="absolute top-1 left-1 z-10 rounded-md bg-destructive px-1 py-0.5 text-[10px] font-bold text-destructive-foreground shadow-sm"
                    >
                        -{{ discountPercent(book) }}%
                    </span>
                    <span
                        v-if="book.is_preorder"
                        class="absolute top-1 left-1 z-10 rounded-md bg-sky-600 px-1 py-0.5 text-[10px] font-bold text-white shadow-sm"
                    >
                        Pre-Order
                    </span>
                    <img
                        v-if="book.cover_url"
                        :src="book.cover_url"
                        :alt="book.judul"
                        class="h-full w-full object-cover"
                        :class="isEmptyStock(book) && 'grayscale'"
                    />
                    <BookCoverPlaceholder
                        v-else
                        :title="book.judul"
                        class="h-full w-full"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ book.judul }}</p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ book.penulis ?? '—' }}
                    </p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <template v-if="book.price_breakdown?.promo_discount">
                            <Money
                                :value="book.price_breakdown.final_price"
                                class="font-semibold text-primary"
                            />
                            <Money
                                :value="book.harga"
                                class="text-xs text-muted-foreground line-through"
                            />
                        </template>
                        <Money
                            v-else
                            :value="book.harga"
                            class="font-semibold"
                        />
                    </div>
                    <p
                        v-if="book.is_preorder"
                        class="mt-0.5 text-xs font-medium text-sky-700"
                    >
                        Estimasi tersedia
                        {{ preorderEtaLabel(book) || '(menyusul)' }}
                    </p>
                </div>
                <span
                    v-if="book.stok > 0"
                    class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                >
                    Stok {{ book.stok }}
                </span>
                <span
                    v-else-if="isEmptyStock(book)"
                    class="shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500"
                >
                    Habis
                </span>
            </Link>
        </div>

        <!-- ── Grid view (mobile & desktop) ── -->
        <div
            v-else-if="books.length"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="book in books"
                :key="book.id"
                :href="showRoute.url(bookShowUrl(book))"
                class="flex flex-col overflow-hidden rounded-xl border transition-shadow hover:shadow-md"
                :class="isEmptyStock(book) && 'opacity-60 saturate-50'"
            >
                <div
                    class="relative flex aspect-[2/3] items-center justify-center overflow-hidden bg-muted text-4xl"
                >
                    <span
                        v-if="discountPercent(book) > 0"
                        class="absolute top-2 left-2 rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground shadow-sm"
                    >
                        -{{ discountPercent(book) }}%
                    </span>
                    <span
                        v-if="book.is_preorder"
                        class="absolute top-2 left-2 rounded-md bg-sky-600 px-1.5 py-0.5 text-xs font-bold text-white shadow-sm"
                    >
                        Pre-Order
                    </span>
                    <img
                        v-if="book.cover_url"
                        :src="book.cover_url"
                        :alt="book.judul"
                        class="h-full w-full object-contain"
                        :class="isEmptyStock(book) && 'grayscale'"
                    />
                    <BookCoverPlaceholder
                        v-else
                        :title="book.judul"
                        class="h-full w-full"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-1 p-3 sm:p-4">
                    <p class="line-clamp-2 text-sm font-medium">
                        {{ book.judul }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ book.penulis ?? '—' }}
                    </p>
                    <p
                        v-if="book.is_preorder"
                        class="truncate text-[11px] font-medium text-sky-700"
                    >
                        Estimasi {{ preorderEtaLabel(book) || '(menyusul)' }}
                    </p>
                    <div class="mt-auto flex flex-col gap-1.5 pt-2">
                        <div class="flex flex-wrap items-baseline gap-x-1.5">
                            <template
                                v-if="book.price_breakdown?.promo_discount"
                            >
                                <Money
                                    :value="book.price_breakdown.final_price"
                                    class="font-semibold text-primary"
                                />
                                <Money
                                    :value="book.harga"
                                    class="text-xs text-muted-foreground line-through"
                                />
                            </template>
                            <template v-else>
                                <Money
                                    :value="book.harga"
                                    class="font-semibold"
                                />
                            </template>
                        </div>
                        <span
                            v-if="book.stok > 0"
                            class="self-start rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                        >
                            Stok {{ book.stok }}
                        </span>
                        <span
                            v-else-if="isEmptyStock(book)"
                            class="self-start rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500"
                        >
                            Habis
                        </span>
                    </div>
                </div>
            </Link>
        </div>

        <EmptyState
            v-else
            :lucide-icon="BookX"
            title="Buku tidak ditemukan"
            :description="emptyStateDescription"
        >
            <Button variant="outline" size="sm" @click="resetFilters">
                Lihat Semua Buku
            </Button>
        </EmptyState>

        <div
            v-if="books.length && currentPage < lastPage"
            class="flex justify-center"
        >
            <Button variant="outline" :disabled="loadingMore" @click="loadMore">
                <Loader2 v-if="loadingMore" class="size-4 animate-spin" />
                {{ loadingMore ? 'Memuat...' : 'Muat Lebih Banyak' }}
            </Button>
        </div>
    </div>

    <!-- ── Modal: Detail Paket Hemat ── -->
    <Dialog
        :open="activeBundle !== null"
        @update:open="(v) => !v && (activeBundle = null)"
    >
        <DialogScrollContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    {{ activeBundle?.promo_name }}
                    <span
                        v-if="activeBundle"
                        class="rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                    >
                        -{{ activeBundle.discount_percent }}%
                    </span>
                </DialogTitle>
                <DialogDescription>
                    Diskon {{ activeBundle?.discount_percent }}% berlaku saat
                    membeli semua buku dalam paket ini
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[45vh] overflow-y-auto border-y">
                <ul v-if="activeBundle" class="divide-y">
                    <li
                        v-for="book in activeBundle.books"
                        :key="book.id"
                        class="flex items-center gap-3 py-2.5"
                    >
                        <Link
                            :href="showRoute.url(bookShowUrl(book))"
                            class="size-12 shrink-0 overflow-hidden rounded-md border bg-muted"
                        >
                            <img
                                v-if="book.cover_url"
                                :src="book.cover_url"
                                :alt="book.judul"
                                class="h-full w-full object-cover"
                            />
                            <BookCoverPlaceholder
                                v-else
                                :title="book.judul"
                                class="h-full w-full"
                            />
                        </Link>
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="showRoute.url(bookShowUrl(book))"
                                class="line-clamp-1 text-sm font-medium hover:underline"
                            >
                                {{ book.judul }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                <span class="line-through">
                                    <Money :value="book.price_original" />
                                </span>
                                <span class="ml-1.5 font-medium text-primary">
                                    <Money :value="book.unit_final" />
                                </span>
                                <span
                                    v-if="book.unit_discount > 0"
                                    class="ml-1.5 font-medium text-destructive"
                                >
                                    −
                                    <Money :value="book.unit_discount" />
                                </span>
                                <span
                                    v-if="book.stok <= 0"
                                    class="ml-1.5 rounded bg-neutral-100 px-1.5 py-0.5 font-medium text-neutral-500"
                                >
                                    Habis
                                </span>
                            </p>
                        </div>
                    </li>
                </ul>
            </div>

            <div
                class="flex flex-col gap-1.5 rounded-lg border bg-muted/30 p-3 text-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground"
                        >Harga satuan total</span
                    >
                    <Money :value="activeBundle?.total_original ?? 0" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground"
                        >Diskon paket (-{{
                            activeBundle?.discount_percent
                        }}%)</span
                    >
                    <Money
                        :value="-(activeBundle?.total_discount ?? 0)"
                        class="font-medium text-destructive"
                    />
                </div>
                <div class="flex items-center justify-between border-t pt-1.5">
                    <span class="font-semibold">Harga paket</span>
                    <Money
                        :value="activeBundle?.total_final ?? 0"
                        class="font-bold text-primary"
                    />
                </div>
            </div>

            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <p
                    v-if="
                        activeBundle &&
                        (bundleStockCounts[activeBundle.id] ?? 0) > 0
                    "
                    class="text-xs font-medium text-destructive"
                >
                    {{ bundleStockCounts[activeBundle.id] }} buku dalam paket
                    sedang stok habis — hanya buku tersedia yang ditambahkan.
                </p>
                <Form
                    v-if="activeBundle"
                    :action="CartController.addBulk().url"
                    method="post"
                    class="w-full"
                >
                    <input
                        v-for="book in activeBundle.books"
                        :key="book.id"
                        type="hidden"
                        name="book_ids[]"
                        :value="String(book.id)"
                    />
                    <Button type="submit" class="w-full">
                        Masukkan Semua ke Keranjang
                    </Button>
                </Form>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
