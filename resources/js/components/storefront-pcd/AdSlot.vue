<script setup lang="ts">
/**
 * AdSlot — placeholder iklan responsif untuk halaman artikel proto-d.
 *
 * Tidak menutupi konten: iklan hidup dalam kolom grid-nya sendiri
 * (rail samping sticky hanya di layar besar, hilang di mobile) atau
 * sebagai blok penuh di bawah artikel. Seluruh slot tunduk pada
 * `adConfig.enabled`.
 */
import { computed } from 'vue';
import { adConfig } from '@/config/ads';
import type { BookPromo } from '@/types';
import BookPromoAd from './BookPromoAd.vue';

const props = defineProps<{
    /** 'bottom': blok penuh di bawah artikel · 'side': rail samping. */
    placement: 'bottom' | 'side';
    /** Buku + harga untuk slot promosi buku (fallback ke placeholder bila kosong). */
    books?: BookPromo[] | null;
}>();

const visible = computed(() => {
    if (!adConfig.enabled) {
        return false;
    }

    return props.placement === 'bottom'
        ? adConfig.articleDetail.bottom
        : adConfig.articleDetail.side !== 'none';
});
</script>

<template>
    <aside
        v-if="visible"
        role="complementary"
        aria-label="Iklan"
        class="overflow-hidden rounded-lg border-2 border-flat-border bg-white"
        :class="
            props.placement === 'side'
                ? 'sticky top-24 hidden lg:block'
                : 'mt-10'
        "
    >
        <p
            class="border-b-2 border-flat-border px-3 py-1.5 text-[10px] font-semibold tracking-[0.16em] text-gray-500 uppercase"
        >
            Iklan
        </p>
        <BookPromoAd
            v-if="props.books && props.books.length > 0"
            :books="props.books"
            :variant="props.placement"
        />
        <div
            v-else
            class="flex aspect-[4/3] items-center justify-center p-4 text-center"
            :class="props.placement === 'side' ? 'w-72 max-w-full' : ''"
        >
            <p class="text-xs leading-relaxed text-gray-500">
                Ruang iklan — tempat banner, promosi, atau ad network.
            </p>
        </div>
    </aside>
</template>
