<script setup lang="ts">
/**
 * Beranda editorial proto-d — /pcd.
 *
 * Daftar artikel nyata dari CMS (aktif & terbit) dengan filter server-side:
 * pencarian (judul/kata kunci/isi) di header, kategori jamak (OR), bulan,
 * dan tahun di sidebar kiri. Filter hidup di URL query sehingga tetap
 * tersimpan saat navigasi ke detail dan kembali.
 */
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import {
    ArrowRight,
    Loader2,
    ShoppingCart,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import FlatSection from '@/components/storefront/FlatSection.vue';
import ArticleFilterSidebar from '@/components/storefront-pcd/ArticleFilterSidebar.vue';
import ArticleListSkeleton from '@/components/storefront-pcd/ArticleListSkeleton.vue';
import ArticleThumb from '@/components/storefront-pcd/ArticleThumb.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { formatDateID } from '@/lib/date';
import { bookShowUrl } from '@/lib/slug';
import { home as homeRoute } from '@/routes/pcd';
import {
    loadMore as loadMoreUrl,
    show as articleShowRoute,
} from '@/routes/pcd/articles';
import { catalog as catalogUrl, show as showRoute } from '@/routes/pcd/books';
import { show as bundleShowRoute } from '@/routes/pcd/bundles';

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    is_preorder: boolean;
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
    books: Array<{ id: string; judul: string; cover_url: string | null }>;
};

type Motif =
    'stack' | 'manuscript' | 'readers' | 'quote' | 'shelf' | 'pencil' | 'lamp';

type ArticleCard = {
    id: string;
    judul: string;
    slug: string;
    kategori_id: string | null;
    kategori_label: string;
    penulis: string | null;
    ringkasan: string;
    published_at: string | null;
    motif: Motif | null;
    cover_url: string | null;
    menit: number;
};

type ArticleFilters = {
    search?: string | null;
    category_id?: string | null;
    categories?: string[] | null;
    month?: number | null;
    year?: number | null;
};

type CategoryFacet = {
    id: string;
    nama: string;
    articles_count: number;
};

type DateFacet = {
    year: number;
    month: number;
    count: number;
};

const props = defineProps<{
    featured: ArticleCard | null;
    books: Book[];
    categories: Array<{ id: string; nama: string }>;
    bundles: Bundle[];
    promos: Promo[];
    articles: {
        data: ArticleCard[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    filters: ArticleFilters;
    articleCategories: CategoryFacet[];
    dateFacets: DateFacet[];
}>();

defineOptions({ layout: StorefrontPcdLayout });

// ── State filter (disinkronkan dari props server / URL) ──
const selectedCategories = ref<string[]>(props.filters.categories ?? []);
const month = ref<number | null>(props.filters.month ?? null);
const year = ref<number | null>(props.filters.year ?? null);
const searchActive = computed(() => Boolean(props.filters.search));
const hasFilters = computed(
    () =>
        searchActive.value ||
        selectedCategories.value.length > 0 ||
        month.value !== null ||
        year.value !== null,
);

// ── Daftar artikel (paginator, append saat load-more) ──
const articlesData = ref<ArticleCard[]>(props.articles.data);
const currentPage = ref(props.articles.current_page);

watch(
    () => props.articles,
    (next) => {
        articlesData.value = next.data;
        currentPage.value = next.current_page;
    },
);

watch(
    () => props.filters,
    (next) => {
        selectedCategories.value = next.categories ?? [];
        month.value = next.month ?? null;
        year.value = next.year ?? null;
    },
);

// Artikel unggulan dari server (is_featured=true, pilihan admin) — hanya
// saat tanpa filter & halaman pertama. Fallback: artikel terbaru pertama.
const featuredArticle = computed<ArticleCard | null>(() =>
    !hasFilters.value && currentPage.value === 1
        ? (props.featured ?? articlesData.value[0] ?? null)
        : null,
);

// Daftar tanpa artikel unggulan (hindari duplikat di feed).
const listArticles = computed<ArticleCard[]>(() => {
    if (!featuredArticle.value) {
        return articlesData.value;
    }

    return articlesData.value.filter(
        (article) => article.id !== featuredArticle.value?.id,
    );
});

// ── Navigasi filter: skeleton saat menunggu respons Inertia ──
const loadingList = ref(false);
let offStart: () => void;
let offFinish: () => void;

onMounted(() => {
    offStart = router.on('start', () => {
        if (!loadingMore.value) {
            loadingList.value = true;
        }
    });
    offFinish = router.on('finish', () => {
        loadingList.value = false;
    });
});

onUnmounted(() => {
    offStart();
    offFinish();
});

function applyFilters(): void {
    router.get(
        homeRoute().url,
        {
            search: props.filters.search || undefined,
            categories:
                selectedCategories.value.length > 0
                    ? selectedCategories.value
                    : undefined,
            month: month.value ?? undefined,
            year: year.value ?? undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['articles', 'filters'],
        },
    );
}

function updateCategories(value: string[]): void {
    selectedCategories.value = value;
    applyFilters();
}

function selectMonth(value: number | null): void {
    month.value = value;
    applyFilters();
}

function selectYear(value: number | null): void {
    year.value = value;

    if (value !== null && month.value !== null) {
        const available = props.dateFacets.some(
            (f) => f.year === value && f.month === month.value,
        );

        if (!available) {
            month.value = null;
        }
    }

    applyFilters();
}

function clearFilters(): void {
    selectedCategories.value = [];
    month.value = null;
    year.value = null;
    applyFilters();
}

/** Bersihkan semua filter termasuk pencarian (dari state kosong). */
function resetAll(): void {
    router.get(
        homeRoute().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['articles', 'filters'],
        },
    );
}

// ── Load more (halaman berikutnya) ──
const loadMoreRequest = useHttp();
const loadingMore = ref(false);
const canLoadMore = computed(
    () => articlesData.value.length < props.articles.total,
);
let loadMoreToken = 0;

function loadMore(): void {
    if (loadingMore.value || !canLoadMore.value) {
        return;
    }

    loadingMore.value = true;
    const token = ++loadMoreToken;

    loadMoreRequest.get(
        loadMoreUrl({
            query: {
                page: currentPage.value + 1,
                search: props.filters.search || undefined,
                categories:
                    selectedCategories.value.length > 0
                        ? selectedCategories.value
                        : undefined,
                month: month.value ?? undefined,
                year: year.value ?? undefined,
            },
        }).url,
        {
            onSuccess: (data: unknown) => {
                if (token !== loadMoreToken) {
                    return;
                }

                const page = data as {
                    data: ArticleCard[];
                    current_page: number;
                    last_page: number;
                };
                articlesData.value = [...articlesData.value, ...page.data];
                currentPage.value = page.current_page;
            },
            onFinish: () => {
                if (token === loadMoreToken) {
                    loadingMore.value = false;
                }
            },
        },
    );
}

// ── Filter mobile: panel kolapsibel ──
const filterOpen = ref(false);

// ── Promo slot (data nyata dari backend) ──
const heroBundle = computed(() => props.bundles[0] ?? null);
const featuredBooks = computed(() => props.books);

const bundleHref = computed(() =>
    heroBundle.value
        ? bundleShowRoute({ bundle: heroBundle.value.id }).url
        : catalogUrl().url,
);

const dismissedPromos = ref<Set<string>>(new Set());
function dismissPromo(id: string): void {
    dismissedPromos.value.add(id);
}

const dateLabel = (value: string | null): string =>
    value ? formatDateID(value) : '';
</script>

<template>
    <Head title="Artikel — Pustaka Cahaya Peradaban" />

    <div class="mx-auto max-w-6xl px-4 pb-24 md:pb-32">
        <!-- Intro -->
        <div class="pt-14 pb-10 text-center md:pt-20">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
            >
                Redaksi
            </p>
            <h1
                class="mx-auto mt-3 max-w-2xl text-3xl font-extrabold tracking-tight md:text-4xl"
            >
                Membaca adalah cara kami berpikir
            </h1>
            <p class="mx-auto mt-3 max-w-2xl text-[15px] leading-relaxed text-gray-500">
                Kami menerbitkan buku dan menulis tentangnya — esai, resensi,
                dan catatan dari meja redaksi.
            </p>
        </div>

        <!-- Artikel unggulan (hanya saat tanpa filter) -->
        <FlatSection
            v-if="featuredArticle && !loadingList"
            variant="primary"
            decoration
        >
            <article class="mx-auto max-w-3xl py-6 text-center md:py-10">
                <p
                    class="text-xs font-semibold tracking-[0.16em] text-white/80 uppercase"
                >
                    {{ featuredArticle.kategori_label }} ·
                    {{ dateLabel(featuredArticle.published_at) }}
                </p>
                <h2
                    class="mt-4 text-3xl leading-[1.15] font-extrabold tracking-tight text-white md:text-5xl"
                >
                    <Link
                        :href="
                            articleShowRoute({
                                article: featuredArticle.slug,
                            }).url
                        "
                        class="transition-colors hover:text-white/85"
                    >
                        {{ featuredArticle.judul }}
                    </Link>
                </h2>
                <p class="mx-auto mt-5 max-w-xl text-[15px] leading-[1.7] text-white/80">
                    {{ featuredArticle.ringkasan }}
                </p>
                <p class="mt-5 text-sm text-white/70">
                    {{ featuredArticle.penulis ?? 'Tim Penerbit' }} ·
                    {{ featuredArticle.menit }} menit baca
                </p>

                <Link
                    :href="articleShowRoute({ article: featuredArticle.slug }).url"
                    class="mt-8 block"
                >
                    <img
                        v-if="featuredArticle.cover_url"
                        :src="featuredArticle.cover_url"
                        :alt="`Ilustrasi artikel ${featuredArticle.judul}`"
                        class="mx-auto aspect-video w-full max-w-2xl rounded-lg object-cover ring-2 ring-white/20 transition-transform duration-300 hover:scale-[1.01]"
                    />
                    <ArticleThumb
                        v-else
                        :motif="featuredArticle.motif ?? 'quote'"
                        :label="`Ilustrasi artikel ${featuredArticle.judul}`"
                    />
                </Link>

                <Link
                    :href="articleShowRoute({ article: featuredArticle.slug }).url"
                    class="mt-8 inline-flex min-h-12 items-center gap-1.5 rounded-md bg-white px-6 text-sm font-semibold text-flat-primary transition-all duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-flat-primary focus-visible:outline-none"
                >
                    Baca artikel
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </article>
        </FlatSection>

        <!-- Daftar artikel + filter -->
        <section
            id="artikel-terbaru"
            class="mt-16 scroll-mt-24 md:mt-20"
            aria-labelledby="artikel-terbaru-title"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2
                    id="artikel-terbaru-title"
                    class="text-2xl font-extrabold tracking-tight"
                >
                    Artikel Terbaru
                </h2>
                <p class="text-xs text-gray-500" aria-live="polite">
                    {{
                        hasFilters
                            ? `${articlesData.length} hasil`
                            : `${props.articles.total} artikel`
                    }}
                </p>
            </div>

            <!-- Tombol filter mobile -->
            <button
                type="button"
                class="mt-6 inline-flex min-h-12 items-center gap-2 rounded-md border-2 px-4 text-[13px] font-medium transition-all duration-200 focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none lg:hidden"
                :class="
                    filterOpen
                        ? 'border-flat-primary bg-flat-primary text-white'
                        : 'border-flat-border text-flat-ink hover:bg-flat-muted'
                "
                :aria-expanded="filterOpen"
                aria-controls="pcd-filter-sidebar"
                @click="filterOpen = !filterOpen"
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />
                Filter
                <span
                    v-if="
                        selectedCategories.length > 0 ||
                        month !== null ||
                        year !== null
                    "
                    class="rounded-full bg-flat-accent px-2 py-0.5 text-[11px] font-semibold text-white"
                    aria-label="Jumlah filter aktif"
                >
                    {{
                        selectedCategories.length +
                        (month !== null ? 1 : 0) +
                        (year !== null ? 1 : 0)
                    }}
                </span>
            </button>

            <!-- Kolom kiri: sidebar filter · Kanan: daftar -->
            <div
                class="mt-6 lg:grid lg:grid-cols-[260px_minmax(0,1fr)] lg:items-start lg:gap-10"
            >
                <aside
                    id="pcd-filter-sidebar"
                    class="lg:sticky lg:top-24 lg:self-start"
                    aria-label="Filter artikel"
                >
                    <div :class="filterOpen ? 'block' : 'hidden lg:block'">
                        <ArticleFilterSidebar
                            :categories="articleCategories"
                            :date-facets="dateFacets"
                            :categories-selected="selectedCategories"
                            :month="month"
                            :year="year"
                            :loading="loadingList"
                            @update:categories="updateCategories"
                            @update:month="selectMonth"
                            @update:year="selectYear"
                            @clear="clearFilters"
                        />
                    </div>
                </aside>

                <div class="mt-10 min-w-0 lg:mt-0">
                    <!-- Skeleton saat navigasi filter -->
                    <ArticleListSkeleton v-if="loadingList" :rows="5" />

                    <!-- Daftar artikel -->
                    <template v-else>
                        <article
                            v-for="a in listArticles"
                            :key="a.id"
                            class="flex gap-5 border-b-2 border-flat-border py-8"
                        >
                            <Link
                                :href="
                                    articleShowRoute({ article: a.slug }).url
                                "
                                class="w-28 shrink-0 text-left sm:w-40"
                                :aria-label="`Buka artikel ${a.judul}`"
                            >
                                <img
                                    v-if="a.cover_url"
                                    :src="a.cover_url"
                                    :alt="`Ilustrasi artikel ${a.judul}`"
                                    class="aspect-video w-full rounded-md object-cover border-2 border-flat-border transition-transform duration-300 hover:scale-[1.02]"
                                />
                                <ArticleThumb
                                    v-else
                                    :motif="a.motif ?? 'quote'"
                                    :label="`Ilustrasi artikel ${a.judul}`"
                                />
                            </Link>
                            <div class="min-w-0">
                                <p
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                >
                                    <span
                                        class="text-xs font-semibold tracking-[0.14em] text-flat-primary uppercase"
                                        >{{ a.kategori_label }}</span
                                    >
                                    <span
                                        v-if="a.published_at"
                                        class="text-xs text-gray-500"
                                        >{{ dateLabel(a.published_at) }}</span
                                    >
                                </p>
                                <h3
                                    class="mt-2.5 text-lg leading-snug font-extrabold tracking-tight md:text-xl"
                                >
                                    <Link
                                        :href="
                                            articleShowRoute({
                                                article: a.slug,
                                            }).url
                                        "
                                        class="transition-colors hover:text-flat-primary"
                                    >
                                        {{ a.judul }}
                                    </Link>
                                </h3>
                                <p
                                    class="mt-2 text-sm leading-[1.7] text-gray-500"
                                >
                                    {{ a.ringkasan }}
                                </p>
                            </div>
                        </article>

                        <!-- Tidak ada hasil -->
                        <div
                            v-if="listArticles.length === 0"
                            class="border-b-2 border-flat-border py-16 text-center"
                        >
                            <p class="text-sm text-gray-500">
                                {{
                                    props.articles.total === 0 && !hasFilters
                                        ? 'Belum ada artikel. Cerita pertama akan segera hadir.'
                                        : 'Tidak ada artikel yang cocok dengan filter.'
                                }}
                            </p>
                            <button
                                v-if="hasFilters"
                                type="button"
                                class="mt-5 inline-flex min-h-12 items-center rounded-md border-2 border-flat-border bg-white px-6 text-sm font-medium transition-all duration-200 hover:bg-flat-muted focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:outline-none"
                                @click="resetAll"
                            >
                                Bersihkan filter &amp; pencarian
                            </button>
                        </div>

                        <!-- Muat lainnya -->
                        <div
                            v-if="canLoadMore && !loadingList"
                            class="mt-10 flex justify-center"
                        >
                            <button
                                type="button"
                                :disabled="loadingMore"
                                class="inline-flex min-h-12 items-center gap-2 rounded-md bg-flat-primary px-8 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                                @click="loadMore"
                            >
                                <Loader2
                                    v-if="loadingMore"
                                    class="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                                Muat artikel lainnya
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- Promo slot: paket (data nyata) -->
        <FlatSection
            v-if="heroBundle && !dismissedPromos.has(`bundle-${heroBundle.id}`)"
            variant="secondary"
            decoration
            class="mt-16"
        >
            <div class="relative flex items-center gap-5">
                <button
                    type="button"
                    class="absolute -top-3 -right-1 flex size-10 items-center justify-center rounded-full bg-white/20 text-white transition-all duration-200 hover:scale-105 hover:bg-white/30"
                    :aria-label="`Tutup promo ${heroBundle.promo_name}`"
                    @click="dismissPromo(`bundle-${heroBundle.id}`)"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
                <div class="grid shrink-0 grid-cols-2 gap-1.5">
                    <img
                        v-for="b in heroBundle.books.slice(0, 4)"
                        :key="b.id"
                        :src="b.cover_url ?? ''"
                        :alt="b.cover_url ? `Sampul ${b.judul}` : ''"
                        class="size-11 rounded-sm object-cover ring-2 ring-white/20"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-xs font-semibold tracking-[0.14em] text-white/80 uppercase"
                    >
                        Promo · Paket
                    </p>
                    <h3 class="mt-1.5 truncate text-base font-extrabold text-white">
                        {{ heroBundle.promo_name }}
                    </h3>
                    <p class="mt-0.5 truncate text-xs text-white/70">
                        {{ heroBundle.books.length }} buku · hemat
                        <Money :value="heroBundle.total_discount" class="text-xs" />
                    </p>
                </div>
                <Link
                    :href="bundleHref"
                    class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-md bg-white px-5 text-sm font-semibold text-flat-secondary transition-all duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-flat-secondary focus-visible:outline-none"
                    >Lihat Paket</Link
                >
            </div>
        </FlatSection>

        <!-- Dari Toko: buku unggulan (data nyata) -->
        <section
            class="mt-20 md:mt-28"
            aria-labelledby="dari-toko"
        >
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
                    >
                        Dari Toko
                    </p>
                    <h2
                        id="dari-toko"
                        class="mt-2 text-2xl font-extrabold tracking-tight md:text-3xl"
                    >
                        Buku Pilihan
                    </h2>
                </div>
                <Link
                    :href="catalogUrl().url"
                    class="shrink-0 text-sm font-semibold text-flat-primary transition-colors hover:text-flat-primary-dark"
                    >Semua buku →</Link
                >
            </div>

            <div
                v-if="featuredBooks.length > 0"
                class="mt-10 grid grid-cols-2 gap-x-6 gap-y-12 sm:grid-cols-4"
            >
                <div v-for="book in featuredBooks" :key="book.id">
                    <Link
                        :href="showRoute.url(bookShowUrl(book))"
                        class="group block"
                    >
                        <div
                            class="relative overflow-hidden rounded-md border-2 border-flat-border"
                        >
                            <img
                                v-if="book.cover_url"
                                :src="book.cover_url"
                                :alt="`Sampul buku ${book.judul}`"
                                class="aspect-[5/7] w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                            />
                            <BookCoverPlaceholder
                                v-else
                                :title="book.judul"
                                class="aspect-[5/7] w-full"
                            />
                        </div>
                        <h3 class="mt-3 truncate text-sm font-extrabold">
                            {{ book.judul }}
                        </h3>
                        <p class="mt-0.5 truncate text-xs text-gray-500">
                            {{ book.penulis ?? '—' }}
                        </p>
                        <p class="mt-2 text-sm font-bold tabular-nums text-flat-primary">
                            <Money
                                :value="
                                    book.price_breakdown?.final_price ??
                                    book.harga
                                "
                            />
                            <s
                                v-if="book.price_breakdown"
                                class="ml-1 text-xs font-normal text-gray-400"
                            >
                                <Money
                                    :value="book.price_breakdown.original_price"
                                />
                            </s>
                        </p>
                    </Link>
                    <Form
                        :action="CartController.add().url"
                        method="post"
                        class="mt-3"
                    >
                        <input type="hidden" name="book_id" :value="book.id" />
                        <input type="hidden" name="qty" value="1" />
                        <button
                            type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-md border-2 border-flat-border bg-white text-xs font-semibold text-flat-ink transition-all duration-200 hover:bg-flat-muted focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:outline-none"
                        >
                            <ShoppingCart class="size-3.5" aria-hidden="true" />
                            Tambah
                        </button>
                    </Form>
                </div>
            </div>

            <p v-else class="mt-10 text-sm text-gray-500">
                Belum ada buku yang ditampilkan.
            </p>
        </section>
    </div>
</template>
