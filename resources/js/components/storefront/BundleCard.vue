<script setup lang="ts">
/**
 * BundleCard — Flat design bundle/package card.
 * Shows bundle with books, discount, and pricing.
 */
import { Link } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';

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

defineProps<{
    bundle: Bundle;
    /** Show add to cart button */
    showCart?: boolean;
}>();

defineEmits<{
    addToCart: [];
}>();
</script>

<template>
    <div
        class="group overflow-hidden rounded-lg bg-white transition-all duration-200 hover:scale-[1.02]"
    >
        <!-- Header with discount badge -->
        <div class="bg-flat-accent p-4 text-white">
            <div class="flex items-center justify-between">
                <h3
                    class="text-sm font-bold"
                    style="font-family: var(--font-flat)"
                >
                    {{ bundle.promo_name }}
                </h3>
                <span class="rounded-md bg-white/20 px-2 py-0.5 text-xs font-bold">
                    HEMAT {{ bundle.discount_percent }}%
                </span>
            </div>
        </div>

        <!-- Books grid -->
        <div class="p-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div
                    v-for="book in bundle.books"
                    :key="book.id"
                    class="relative"
                >
                    <div class="aspect-[3/4] overflow-hidden rounded-md bg-flat-muted">
                        <img
                            v-if="book.cover_url"
                            :src="book.cover_url"
                            :alt="book.judul"
                            class="size-full object-cover"
                        />
                        <BookCoverPlaceholder v-else class="size-full" />
                    </div>
                    <p class="mt-1 line-clamp-1 text-xs text-gray-600">
                        {{ book.judul }}
                    </p>
                </div>
            </div>

            <!-- Pricing -->
            <div class="mt-4 border-t border-flat-border pt-4">
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>Harga normal</span>
                    <span class="line-through">
                        <Money :amount="bundle.total_original" />
                    </span>
                </div>
                <div class="mt-1 flex items-center justify-between text-xs text-flat-secondary">
                    <span>Diskon paket</span>
                    <span>
                        -<Money :amount="bundle.total_discount" />
                    </span>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-sm font-bold text-flat-ink">Harga paket</span>
                    <span class="text-lg font-bold text-flat-primary">
                        <Money :amount="bundle.total_final" />
                    </span>
                </div>
            </div>

            <!-- Add to cart button -->
            <button
                v-if="showCart && bundle.books.some(b => b.stok > 0)"
                type="button"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-md bg-flat-primary px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark"
                @click="$emit('addToCart')"
            >
                <ShoppingCart class="size-4" />
                Beli Paket
            </button>
        </div>
    </div>
</template>
