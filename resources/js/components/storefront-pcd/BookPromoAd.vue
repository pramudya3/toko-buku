<script setup lang="ts">
/**
 * BookPromoAd — kartu buku + harga untuk slot iklan artikel proto-d.
 *
 * 'side': tumpukan vertikal kartu horizontal (cover kiri) di rail samping.
 * 'bottom': grid kartu vertikal (cover atas) di bawah artikel.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import { bookShowUrl } from '@/lib/slug';
import { show as showRoute } from '@/routes/pcd/books';
import type { BookPromo } from '@/types';

const props = defineProps<{
    books: BookPromo[];
    variant: 'side' | 'bottom';
}>();

const isSide = computed(() => props.variant === 'side');
</script>

<template>
    <div
        :class="
            isSide
                ? 'space-y-5 p-5'
                : 'grid grid-cols-2 gap-x-4 gap-y-6 p-5 sm:grid-cols-4'
        "
    >
        <Link
            v-for="book in books.slice(0, isSide ? 3 : 4)"
            :key="book.id"
            :href="showRoute.url(bookShowUrl(book))"
            class="group min-w-0"
            :class="isSide ? 'flex items-start gap-3' : 'block'"
            :aria-label="`Buka buku ${book.judul}`"
        >
            <div
                class="relative shrink-0 overflow-hidden rounded-md border-2 border-flat-border"
                :class="isSide ? 'w-14' : 'w-full'"
            >
                <img
                    v-if="book.cover_url"
                    :src="book.cover_url"
                    :alt="`Sampul ${book.judul}`"
                    class="aspect-[5/7] w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                />
                <BookCoverPlaceholder
                    v-else
                    :title="book.judul"
                    class="aspect-[5/7] w-full"
                />
            </div>
            <div class="min-w-0" :class="isSide ? 'flex-1' : 'mt-2.5'">
                <h4
                    class="truncate text-sm font-semibold transition-colors group-hover:text-flat-primary"
                >
                    {{ book.judul }}
                </h4>
                <p class="truncate text-xs text-gray-500">
                    {{ book.penulis ?? '—' }}
                </p>
                <p class="mt-1 text-sm font-semibold tabular-nums">
                    <Money
                        :value="book.price_breakdown?.final_price ?? book.harga"
                    />
                    <s
                        v-if="book.price_breakdown"
                        class="ml-1 text-xs font-normal text-gray-400"
                    >
                        <Money :value="book.price_breakdown.original_price" />
                    </s>
                </p>
            </div>
        </Link>
    </div>
</template>
