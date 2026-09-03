<script setup lang="ts">
/**
 * BookCard — Flat design book card for catalog and home page.
 * Solid background, no shadow, hover scale effect.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import { resolveStockStatus } from '@/lib/stock';

const page = usePage<{ lowStockThreshold?: number }>();
const lowStockThreshold = computed(
    () => (page.props.lowStockThreshold as number | undefined) ?? 5,
);

function stockStatus(
    book: Book,
): 'preorder' | 'habis' | 'menipis' | 'tersedia' {
    return resolveStockStatus(
        book as unknown as {
            stok: number;
            is_preorder: boolean;
            stock_status?: string;
        },
        lowStockThreshold.value,
    );
}

type PriceBreakdown = {
    original_price: number;
    promo_discount: number;
    final_price: number;
    promo_name: string | null;
};

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    is_preorder: boolean;
    category: { id: string; nama: string } | null;
    stock_status?: 'preorder' | 'habis' | 'menipis' | 'tersedia';
    stock_label?: string;
    price_breakdown?: PriceBreakdown | null;
};

defineProps<{
    book: Book;
    /** Show add to cart button */
    showCart?: boolean;
    /** URL for book detail */
    href?: string;
}>();

defineEmits<{
    addToCart: [];
}>();

function bookUrl(book: Book): string {
    const slug = book.judul
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

    return `/buku/${book.id}-${slug}`;
}
</script>

<template>
    <Link
        :href="href ?? bookUrl(book)"
        class="group block cursor-pointer rounded-lg bg-white p-4 transition-all duration-200 hover:scale-[1.02]"
    >
        <!-- Cover -->
        <div
            class="relative aspect-[3/4] overflow-hidden rounded-md bg-flat-muted"
        >
            <img
                v-if="book.cover_url"
                :src="book.cover_url"
                :alt="book.judul"
                class="size-full object-cover transition-transform duration-200 group-hover:scale-105"
            />
            <BookCoverPlaceholder :title="book.judul" class="size-full" />

            <!-- Promo badge -->
            <div
                v-if="book.price_breakdown?.promo_discount"
                class="absolute top-2 right-2 rounded-md bg-flat-accent px-2 py-1 text-xs font-bold text-white"
            >
                HEMAT
            </div>

            <!-- Pre-order badge -->
            <div
                v-if="book.is_preorder"
                class="absolute top-2 left-2 rounded-md bg-flat-secondary px-2 py-1 text-xs font-bold text-white"
            >
                PRE-ORDER
            </div>
        </div>

        <!-- Info -->
        <div class="mt-4">
            <h3
                class="line-clamp-2 text-sm font-semibold text-flat-ink"
                style="font-family: var(--font-flat)"
            >
                {{ book.judul }}
            </h3>

            <p
                v-if="book.penulis"
                class="mt-1 line-clamp-1 text-xs text-gray-500"
            >
                {{ book.penulis }}
            </p>

            <p
                v-if="book.category"
                class="mt-1 text-xs font-medium text-flat-primary"
            >
                {{ book.category.nama }}
            </p>

            <!-- Price -->
            <div class="mt-3">
                <template v-if="book.price_breakdown?.promo_discount">
                    <span class="text-xs text-gray-400 line-through">
                        <Money :value="book.price_breakdown.original_price" />
                    </span>
                    <span class="ml-2 text-sm font-bold text-flat-primary">
                        <Money :value="book.price_breakdown.final_price" />
                    </span>
                </template>
                <template v-else>
                    <span class="text-sm font-bold text-flat-ink">
                        <Money :value="book.harga" />
                    </span>
                </template>
            </div>

            <!-- Stock status — hanya label, tanpa angka stok (prefer stock_status dari server) -->
            <div class="mt-2">
                <span
                    v-if="stockStatus(book) === 'preorder'"
                    class="text-xs font-medium text-sky-700"
                >
                    Pre-Order
                </span>
                <span
                    v-else-if="stockStatus(book) === 'habis'"
                    class="text-xs font-medium text-gray-400"
                >
                    Stok habis
                </span>
                <span
                    v-else-if="stockStatus(book) === 'menipis'"
                    class="text-xs font-medium text-amber-600"
                >
                    Stok menipis
                </span>
                <span v-else class="text-xs font-medium text-emerald-700">
                    Stok tersedia
                </span>
            </div>

            <!-- Add to cart button -->
            <button
                v-if="showCart && (book.stok > 0 || book.is_preorder)"
                type="button"
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-md bg-flat-primary px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark"
                @click.prevent="$emit('addToCart')"
            >
                <ShoppingCart class="size-4" />
                Keranjang
            </button>
        </div>
    </Link>
</template>
