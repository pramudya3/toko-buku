<script setup lang="ts">
/**
 * Katalog proto-d — /pcd/buku. Data nyata dari backend: buku, kategori,
 * promo. Filter kategori (chips) & urutan dikirim ke server (server-side),
 * konsisten dengan storefront lama.
 */
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { bookShowUrl } from '@/lib/slug';
import {
    catalog as catalogUrl,
    loadMore as loadMoreUrl,
    show as showRoute,
} from '@/routes/pcd/books';

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

const props = defineProps<{
    books: {
        data: Book[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    categories: Array<{ id: string; nama: string }>;
    filters: {
        search?: string;
        category_id?: string;
        sort?: string;
    };
}>();

defineOptions({ layout: StorefrontPcdLayout });

const books = ref<Book[]>(props.books.data);
const currentPage = ref(props.books.current_page);
const lastPage = ref(props.books.last_page);
const total = ref(props.books.total);
const loadingMore = ref(false);
const loadMoreRequest = useHttp();

const activeCategory = ref(props.filters.category_id ?? 'all');
const sort = ref(props.filters.sort ?? '');

const sortOptions = [
    { value: '', label: 'Urutan default' },
    { value: 'newest', label: 'Terbaru' },
    { value: 'cheapest', label: 'Termurah' },
    { value: 'expensive', label: 'Termahal' },
];

const canLoadMore = computed(() => currentPage.value < lastPage.value);

function applyFilters(): void {
    router.get(
        catalogUrl().url,
        {
            category_id:
                activeCategory.value === 'all'
                    ? undefined
                    : activeCategory.value,
            sort: sort.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function selectCategory(id: string): void {
    if (activeCategory.value === id) {
        return;
    }

    activeCategory.value = id;
    applyFilters();
}

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
                category_id:
                    activeCategory.value === 'all'
                        ? undefined
                        : activeCategory.value,
                sort: sort.value || undefined,
            },
        }).url,
        {
            onSuccess: (data: unknown) => {
                if (token !== loadMoreToken) {
                    return;
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
    <Head title="Toko — Pustaka Cahaya Peradaban" />

    <div class="mx-auto max-w-6xl px-4 pt-16 pb-24 md:px-6 md:pt-24 md:pb-32">
        <div class="text-center">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-pcd-accent uppercase"
            >
                Toko
            </p>
            <h1
                class="mt-3 font-serif text-3xl font-semibold tracking-tight md:text-4xl"
            >
                Semua Buku
            </h1>
            <p class="mt-3 text-sm text-pcd-muted">
                {{ total }} judul — fisik dan e-book, dikirim dari Malang.
            </p>
        </div>

        <!-- Filter kategori -->
        <div
            class="mt-10 flex flex-wrap items-center justify-center gap-2"
            role="group"
            aria-label="Filter kategori"
        >
            <button
                v-for="cat in [{ id: 'all', nama: 'Semua' }, ...categories]"
                :key="cat.id"
                type="button"
                class="min-h-12 rounded-full px-4 text-[13px] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong"
                :class="
                    activeCategory === cat.id
                        ? 'border border-transparent bg-pcd-ink font-medium text-white'
                        : 'border border-pcd-hairline text-pcd-muted hover:border-pcd-ink hover:text-pcd-ink'
                "
                :aria-pressed="activeCategory === cat.id"
                @click="selectCategory(cat.id)"
            >
                {{ cat.nama }}
            </button>
        </div>

        <!-- Urutan -->
        <div class="mt-6 flex justify-end">
            <label class="flex items-center gap-3">
                <span class="text-xs font-medium text-pcd-muted">Urutkan</span>
                <select
                    v-model="sort"
                    class="min-h-12 rounded-lg border border-pcd-hairline bg-pcd-surface px-3 text-sm transition-colors outline-none focus:border-pcd-accent focus:ring-2 focus:ring-pcd-accent/25"
                    @change="applyFilters()"
                >
                    <option
                        v-for="opt in sortOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
            </label>
        </div>

        <!-- Grid buku -->
        <div
            v-if="books.length > 0"
            class="mt-10 grid grid-cols-2 gap-x-6 gap-y-14 md:grid-cols-3 md:gap-x-10"
        >
            <Link
                v-for="book in books"
                :key="book.id"
                :href="showRoute.url(bookShowUrl(book))"
                class="group"
            >
                <div
                    class="relative overflow-hidden rounded-md ring-1 ring-pcd-hairline"
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
                    <span
                        v-if="book.price_breakdown"
                        class="absolute top-2.5 left-2.5 rounded-full bg-red-700 px-2.5 py-1 text-xs font-semibold text-white"
                        >-{{
                            Math.round(
                                (book.price_breakdown.promo_discount /
                                    book.price_breakdown.original_price) *
                                    100,
                            )
                        }}%</span
                    >
                </div>
                <h2 class="mt-4 text-sm leading-snug font-semibold">
                    {{ book.judul }}
                </h2>
                <p class="mt-1 text-xs text-pcd-muted">
                    {{ book.penulis ?? '—' }}
                </p>
                <p class="mt-2 text-sm font-semibold tabular-nums">
                    <Money
                        :value="book.price_breakdown?.final_price ?? book.harga"
                    />
                    <s
                        v-if="book.price_breakdown"
                        class="ml-1 text-xs font-normal text-pcd-muted"
                    >
                        <Money :value="book.price_breakdown.original_price" />
                    </s>
                </p>
            </Link>
        </div>

        <EmptyState
            v-else
            title="Tidak ada buku yang cocok"
            description="Coba ubah filter kategori atau urutan."
            class="mt-16"
        />

        <div v-if="canLoadMore" class="mt-14 flex justify-center">
            <button
                type="button"
                :disabled="loadingMore"
                class="inline-flex min-h-12 items-center gap-2 rounded-lg border border-pcd-hairline bg-pcd-surface px-8 text-sm font-medium transition-colors hover:border-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong disabled:opacity-60"
                @click="loadMore()"
            >
                <Loader2
                    v-if="loadingMore"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                Muat buku lainnya
            </button>
        </div>

        <p class="mt-6 text-center text-xs text-pcd-muted">
            Pembayaran transfer atau tunai · Gratis ongkir min. Rp 150.000
        </p>
    </div>
</template>
