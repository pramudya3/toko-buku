<script setup lang="ts">
/**
 * PromoCard — Flat design promo card for the promo page.
 * Solid color background, no shadow, hover scale effect.
 */
import Money from '@/components/Money.vue';

type PromoBook = {
    id: string;
    judul: string;
    cover_url: string | null;
    price_breakdown?: {
        original_price: number;
        promo_discount: number;
        final_price: number;
        promo_name: string | null;
    } | null;
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
    books: PromoBook[];
};

defineProps<{
    promo: Promo;
}>();
</script>

<template>
    <div
        class="group cursor-pointer rounded-lg bg-white p-4 transition-all duration-200 hover:scale-[1.02]"
    >
        <!-- Header with promo type badge -->
        <div class="flex items-start justify-between">
            <h3
                class="text-sm font-bold text-flat-ink"
                style="font-family: var(--font-flat)"
            >
                {{ promo.promo_name }}
            </h3>
            <span
                class="shrink-0 rounded-md bg-flat-accent px-2 py-0.5 text-xs font-bold text-white"
            >
                <template v-if="promo.discount_percentage">
                    {{ promo.discount_percentage }}%
                </template>
                <template v-else-if="promo.promo_value">
                    <Money :amount="promo.promo_value" />
                </template>
            </span>
        </div>

        <!-- Date range -->
        <p class="mt-2 text-xs text-gray-500">
            {{ promo.start_date }} — {{ promo.end_date }}
        </p>

        <!-- Global badge -->
        <div v-if="promo.is_global" class="mt-2">
            <span
                class="inline-block rounded-md bg-flat-secondary/10 px-2 py-0.5 text-xs font-semibold text-flat-secondary"
            >
                Semua Buku
            </span>
        </div>

        <!-- Books preview -->
        <div v-if="promo.books.length > 0" class="mt-4">
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="book in promo.books.slice(0, 4)"
                    :key="book.id"
                    class="relative size-12 overflow-hidden rounded-md bg-flat-muted"
                >
                    <img
                        v-if="book.cover_url"
                        :src="book.cover_url"
                        :alt="book.judul"
                        class="size-full object-cover"
                    />
                    <div
                        v-if="book.price_breakdown?.promo_discount"
                        class="absolute inset-0 flex items-center justify-center bg-black/50"
                    >
                        <span class="text-[8px] font-bold text-white">HEMAT</span>
                    </div>
                </div>
                <div
                    v-if="promo.books.length > 4"
                    class="flex size-12 items-center justify-center rounded-md bg-flat-muted text-xs font-semibold text-gray-500"
                >
                    +{{ promo.books.length - 4 }}
                </div>
            </div>
        </div>

        <!-- Global promo: no specific books -->
        <div v-else-if="promo.is_global" class="mt-4">
            <div
                class="flex size-12 items-center justify-center rounded-md bg-flat-primary/10"
            >
                <span class="text-lg">📚</span>
            </div>
        </div>
    </div>
</template>
