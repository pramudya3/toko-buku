<script setup lang="ts">
/**
 * Beranda editorial artikel — halaman utama "/".
 *
 * Layout: Artikel Unggulan (featured pilihan admin) → Artikel Terbaru
 * (5 terbaru upload) → Semua Artikel (feed, filter kategori + pencarian,
 * dengan muat lainnya) → Buku Pilihan (iklan kecil dari toko).
 *
 * Palette: latar #FCFBF7, teks #1F2937, hijau #166534, emas #B08D32,
 * tepian #E7E2D8. Font: Inter (teks) + Lora (judul).
 */
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { BookOpen, Loader2, ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import EditorialArticleCard from '@/components/storefront/EditorialArticleCard.vue';
import type { EditorialArticle } from '@/components/storefront/EditorialArticleCard.vue';
import EditorialFeatured from '@/components/storefront/EditorialFeatured.vue';
import EditorialLayout from '@/layouts/customer/EditorialLayout.vue';
import { bookShowUrl } from '@/lib/slug';
import { home as homeRoute } from '@/routes';
import { loadMore as loadMoreUrl } from '@/routes/articles';
import { catalog as catalogUrl, show as showRoute } from '@/routes/books';

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    harga: number;
    cover_url: string | null;
    stok: number;
    price_breakdown?: {
        original_price: number;
        final_price: number;
        promo_name: string | null;
    } | null;
};

type Category = {
    id: string;
    nama: string;
    articles_count: number;
};

type ArticleFilters = {
    search?: string | null;
    category_id?: string | null;
};

type ArticlePaginator = {
    data: EditorialArticle[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

const props = defineProps<{
    featured: EditorialArticle | null;
    recent: EditorialArticle[];
    articles: ArticlePaginator;
    categories: Category[];
    books: Book[];
    filters: ArticleFilters;
    tagline: string;
}>();

defineOptions({ layout: EditorialLayout });

const allCategories = '__all__';
const selectedCategory = ref(props.filters.category_id ?? allCategories);

// Sinkron filter kategori dari server (navigasi balik/maju, submit).
watch(
    () => props.filters.category_id,
    (value) => {
        selectedCategory.value = value ?? allCategories;
    },
);

function applyCategoryFilter(): void {
    const categoryId =
        selectedCategory.value === allCategories
            ? undefined
            : selectedCategory.value;

    router.get(
        homeRoute().url,
        {
            search: props.filters.search || undefined,
            category_id: categoryId,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['articles', 'filters'],
        },
    );
}

const searchActive = computed(() => Boolean(props.filters.search));

// ── Load more (feed semua artikel) ──
const articlesData = ref(props.articles.data);

watch(
    () => props.articles.data,
    (data) => {
        articlesData.value = data;
    },
);

const loadMoreRequest = useHttp();
const loadingMore = ref(false);
const canLoadMore = computed(
    () => articlesData.value.length < props.articles.total,
);

function loadMore(): void {
    if (loadingMore.value || !canLoadMore.value) {
        return;
    }

    loadingMore.value = true;

    loadMoreRequest.get(
        loadMoreUrl({
            query: {
                page: props.articles.current_page + 1,
                search: props.filters.search || undefined,
                category_id:
                    selectedCategory.value === allCategories
                        ? undefined
                        : selectedCategory.value,
            },
        }).url,
        {
            onSuccess: (data: unknown) => {
                const page = data as ArticlePaginator;
                articlesData.value = [...articlesData.value, ...page.data];
                (props.articles as ArticlePaginator).current_page =
                    page.current_page;
                (props.articles as ArticlePaginator).last_page = page.last_page;
                (props.articles as ArticlePaginator).total = page.total;
            },
            onFinish: () => {
                loadingMore.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Beranda" />

    <div class="mx-auto max-w-6xl px-4 pb-20 md:px-6 md:pb-28">
        <!-- Intro + tagline -->
        <!-- <div class="pt-12 text-center md:pt-16 md:pb-4">
            <p
                class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-[0.2em] text-article-accent uppercase"
            >
                <BookOpen class="size-3.5" aria-hidden="true" />
                Pustaka
            </p>
            <h1
                class="mx-auto mt-3 max-w-2xl font-serif text-3xl leading-tight font-bold tracking-tight text-article-ink md:text-4xl"
            >
                {{ tagline }}
            </h1>
        </div> -->

        <!-- Artikel Unggulan -->
        <section
            v-if="featured"
            class="mt-8 md:mt-10"
            aria-label="Artikel unggulan"
        >
            <EditorialFeatured :article="featured" />
        </section>

        <!-- Artikel Terbaru (5 terbaru upload) -->
        <section
            v-if="recent.length > 0"
            class="mt-14 md:mt-20"
            aria-labelledby="artikel-terbaru"
        >
            <h2
                id="artikel-terbaru"
                class="font-serif text-2xl font-bold tracking-tight text-article-ink md:text-3xl"
            >
                Artikel Terbaru
            </h2>
            <!-- Kartu bisa digeser kiri/kanan (horizontal scroll) -->
            <div
                class="mt-8 flex snap-x snap-mandatory scroll-px-1 [scrollbar-width:thin] gap-5 overflow-x-auto overscroll-x-contain scroll-smooth pb-3 [&::-webkit-scrollbar]:h-1.5"
            >
                <div
                    v-for="article in recent"
                    :key="article.id"
                    class="w-72 shrink-0 snap-start sm:w-80"
                >
                    <EditorialArticleCard :article="article" />
                </div>
            </div>
        </section>

        <!-- Semua Artikel: filter kategori + feed -->
        <section class="mt-14 md:mt-20" aria-labelledby="all-articles">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2
                        id="all-articles"
                        class="font-serif text-2xl font-bold tracking-tight text-article-ink md:text-3xl"
                    >
                        {{
                            searchActive
                                ? 'Hasil Pencarian'
                                : 'Semua Artikel'
                        }}
                    </h2>
                    <p
                        v-if="searchActive"
                        class="mt-1 text-sm text-article-muted"
                        aria-live="polite"
                    >
                        Hasil untuk pencarian “{{ filters.search }}”.
                    </p>
                </div>

                <!-- Filter kategori (dropdown) -->
                <label
                    class="flex items-center gap-2 text-sm text-article-muted"
                >
                    <span class="sr-only">Kategori</span>
                    <span class="relative">
                        <select
                            v-model="selectedCategory"
                            class="min-h-11 w-full appearance-none rounded-lg border border-article-border bg-article-surface py-2 pr-9 pl-3 text-sm text-article-ink transition-colors outline-none focus:border-article-primary focus:ring-2 focus:ring-article-primary/20 md:w-auto"
                            aria-label="Filter kategori"
                            @change="applyCategoryFilter"
                        >
                            <option :value="allCategories">Semua kategori</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.nama }}
                            </option>
                        </select>
                        <ChevronDown
                            class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-article-muted"
                            aria-hidden="true"
                        />  
                    </span>
                </label>
            </div>

            <!-- Feed semua artikel -->
            <div
                v-if="articlesData.length > 0"
                class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <EditorialArticleCard
                    v-for="article in articlesData"
                    :key="article.id"
                    :article="article"
                />
            </div>

            <div
                v-else
                class="mt-8 rounded-xl border border-article-border bg-article-surface p-12 text-center"
            >
                <BookOpen
                    class="mx-auto size-10 text-article-accent/60"
                    aria-hidden="true"
                />
                <p class="mt-3 text-sm text-article-muted">
                    {{
                        searchActive
                            ? 'Tidak ada artikel yang cocok dengan pencarian.'
                            : 'Belum ada artikel. Cerita pertama akan segera hadir.'
                    }}
                </p>
            </div>

            <!-- Muat lebih banyak -->
            <div v-if="canLoadMore" class="mt-10 flex justify-center">
                <button
                    type="button"
                    :disabled="loadingMore"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-article-primary px-6 text-sm font-semibold text-white transition-colors hover:bg-article-primary-dark focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
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
        </section>

        <!-- Buku Pilihan (iklan kecil dari toko) -->
        <section
            v-if="books.length > 0"
            class="mt-14 md:mt-20"
            aria-labelledby="buku-title"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2
                    id="buku-title"
                    class="font-serif text-xl font-bold tracking-tight text-article-ink md:text-2xl"
                >
                    Buku Pilihan
                </h2>
                <Link
                    :href="catalogUrl().url"
                    class="text-sm font-semibold text-article-primary transition-colors hover:text-article-primary-dark"
                >
                    Semua buku →
                </Link>
            </div>
            <p class="mt-1 text-sm text-article-muted">
                Temukan buku-buku pilihan dari toko kami.
            </p>
            <!-- Kartu iklan bisa digeser kiri/kanan (horizontal scroll) -->
            <div
                class="mt-6 flex snap-x snap-mandatory scroll-px-1 [scrollbar-width:thin] gap-4 overflow-x-auto overscroll-x-contain scroll-smooth pb-3 [&::-webkit-scrollbar]:h-1.5"
            >
                <Link
                    v-for="book in books"
                    :key="book.id"
                    :href="showRoute.url(bookShowUrl(book))"
                    class="group flex w-40 shrink-0 snap-start flex-col rounded-lg border border-article-border bg-article-surface p-3 transition-shadow duration-200 hover:shadow-md focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:ring-offset-2 focus-visible:outline-none sm:w-44"
                >
                    <div
                        class="relative aspect-[5/6] overflow-hidden rounded-md bg-article-border/40"
                    >
                        <img
                            v-if="book.cover_url"
                            :src="book.cover_url"
                            :alt="`Sampul buku ${book.judul}`"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                        />
                        <BookCoverPlaceholder
                            v-else
                            :title="book.judul"
                            class="size-full"
                        />
                    </div>
                    <h3
                        class="mt-2 line-clamp-2 text-sm font-semibold text-article-ink"
                    >
                        {{ book.judul }}
                    </h3>
                    <p class="mt-0.5 truncate text-xs text-article-muted">
                        {{ book.penulis ?? '—' }}
                    </p>
                    <p
                        class="mt-auto pt-2 text-sm font-bold text-article-primary tabular-nums"
                    >
                        <Money
                            :value="
                                book.price_breakdown?.final_price ?? book.harga
                            "
                        />
                    </p>
                </Link>
            </div>
        </section>
    </div>
</template>
