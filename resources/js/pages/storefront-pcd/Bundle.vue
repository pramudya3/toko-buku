<script setup lang="ts">
/**
 * Detail paket (bundle) proto-d — /pcd/paket/{bundle}. Data nyata dari
 * backend: breakdown harga per buku + total hemat via PricingService.
 */
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, MessageCircle, ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { catalog as catalogUrl } from '@/routes/pcd/books';

type BundleBook = {
    id: string;
    judul: string;
    penulis: string | null;
    cover_url: string | null;
    price_original: number;
    unit_discount: number;
    unit_final: number;
    stok: number;
    is_preorder: boolean;
};

const props = defineProps<{
    bundle: {
        id: string;
        promo_name: string;
        discount_percent: number | null;
        books: BundleBook[];
        total_original: number;
        total_discount: number;
        total_final: number;
    };
}>();

defineOptions({ layout: StorefrontPcdLayout });

const bundle = computed(() => props.bundle);
const outOfStockCount = computed(
    () =>
        bundle.value.books.filter((b) => b.stok <= 0 && !b.is_preorder).length,
);
</script>

<template>
    <Head :title="`${bundle.promo_name} — Pustaka Cahaya Peradaban`" />

    <div class="mx-auto max-w-6xl px-4 pt-10 pb-28 md:px-6 md:pt-16 md:pb-32">
        <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
            <ol class="flex items-center gap-1.5">
                <li>
                    <Link
                        :href="catalogUrl().url"
                        class="transition-colors hover:text-flat-primary"
                        >Toko</Link
                    >
                </li>
                <li aria-hidden="true">/</li>
                <li class="text-flat-ink" aria-current="page">Paket</li>
            </ol>
        </nav>

        <div
            class="mt-8 grid gap-12 md:mt-12 lg:grid-cols-[minmax(0,420px)_1fr] lg:gap-16"
        >
            <!-- Kolase sampul -->
            <div class="mx-auto w-full max-w-[340px]">
                <div class="grid grid-cols-2 gap-3">
                    <div
                        v-for="book in bundle.books.slice(0, 4)"
                        :key="book.id"
                        class="overflow-hidden rounded-md border-2 border-flat-border"
                    >
                        <img
                            v-if="book.cover_url"
                            :src="book.cover_url"
                            :alt="`Sampul ${book.judul}`"
                            class="aspect-[5/7] w-full object-cover"
                        />
                        <BookCoverPlaceholder
                            v-else
                            :title="book.judul"
                            class="aspect-[5/7] w-full"
                        />
                    </div>
                </div>
                <p class="mt-3 text-center text-xs text-gray-500">
                    {{ bundle.books.length }} buku dalam satu paket
                </p>
            </div>

            <!-- Info + CTA -->
            <div class="lg:pt-6">
                <p
                    class="text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
                >
                    Paket · Hemat
                </p>
                <h1
                    class="mt-3 text-3xl leading-tight font-extrabold tracking-tight md:text-4xl"
                >
                    {{ bundle.promo_name }}
                </h1>

                <div class="mt-6 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p class="text-3xl font-extrabold tabular-nums text-flat-primary">
                        <Money :value="bundle.total_final" />
                    </p>
                    <p
                        v-if="bundle.total_discount > 0"
                        class="rounded-md bg-flat-accent px-2 py-0.5 text-xs font-bold text-white tabular-nums"
                    >
                        Hemat <Money :value="bundle.total_discount" />
                    </p>
                    <p class="text-sm text-gray-400 tabular-nums line-through">
                        <Money :value="bundle.total_original" />
                    </p>
                </div>
                <p class="mt-1.5 text-xs text-gray-500">
                    Sudah termasuk ongkir — dikirim dalam satu paket.
                </p>

                <!-- Isi paket -->
                <ul
                    class="mt-7 space-y-2.5 border-t-2 border-flat-border pt-6 text-sm"
                >
                    <li
                        v-for="book in bundle.books"
                        :key="book.id"
                        class="flex items-center gap-3"
                    >
                        <Check
                            class="size-4 shrink-0 text-flat-primary"
                            aria-hidden="true"
                        />
                        {{ book.judul }}
                        <span class="text-gray-500"
                            >— <Money :value="book.unit_final"
                        /></span>
                        <s
                            v-if="book.unit_discount > 0"
                            class="ml-auto text-xs text-gray-400"
                            ><Money :value="book.price_original"
                        /></s>
                    </li>
                </ul>

                <p v-if="outOfStockCount > 0" class="mt-4 text-xs text-red-700">
                    {{ outOfStockCount }} buku dalam paket sedang stok habis —
                    hanya buku tersedia yang ditambahkan.
                </p>

                <!-- Satu CTA utama -->
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Form
                        id="addBundleForm"
                        :action="CartController.addBulk().url"
                        method="post"
                        class="flex-1"
                    >
                        <input
                            v-for="book in bundle.books"
                            :key="book.id"
                            type="hidden"
                            name="book_ids[]"
                            :value="book.id"
                        />
                        <button
                            type="submit"
                            class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-md bg-flat-primary px-6 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <ShoppingCart class="size-4" aria-hidden="true" />
                            Tambah Paket ke Keranjang
                        </button>
                    </Form>
                    <a
                        href="#"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md border-2 border-flat-border bg-white px-6 text-sm font-medium transition-all duration-200 hover:bg-flat-muted focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:outline-none"
                    >
                        <MessageCircle class="size-4" aria-hidden="true" />
                        Tanya via WhatsApp
                    </a>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    Pembayaran transfer atau tunai · Garansi rusak diganti.
                </p>
            </div>
        </div>
    </div>

    <!-- Sticky bar (mobile) -->
    <div
        class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-flat-border bg-white lg:hidden"
    >
        <div
            class="flex items-center gap-3 px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]"
        >
            <div class="min-w-0">
                <p class="text-base font-bold tabular-nums text-flat-primary">
                    <Money :value="bundle.total_final" />
                </p>
                <p class="truncate text-[11px] text-gray-500">
                    {{ bundle.books.length }} buku · hemat
                    <Money :value="bundle.total_discount" />
                </p>
            </div>
            <button
                type="submit"
                form="addBundleForm"
                class="ml-auto inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-md bg-flat-primary px-5 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none sm:max-w-xs"
            >
                <ShoppingCart class="size-4" aria-hidden="true" />
                Tambah Paket
            </button>
        </div>
    </div>
</template>
