<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Minus, Plus, ShoppingCart } from '@lucide/vue';
import { ref } from 'vue';
import { computed } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

type Book = {
    id: number;
    judul: string;
    penulis: string | null;
    penerbit: string | null;
    tahun: number | null;
    isbn: string | null;
    sinopsis: string | null;
    harga: number;
    stok: number;
    is_preorder: boolean;
    cover_url: string | null;
    category: { id: number; nama: string } | null;
    rating_umur: string | null;
    dimensi: string | null;
    kemasan: string | null;
    berat_gr: number | null;
    jumlah_halaman: number | null;
    jenis_kertas: string | null;
    cetakan: string | null;
};

const props = defineProps<{
    book: Book;
}>();

defineOptions({
    layout: CustomerLayout,
});

const qty = ref(1);

const specs = computed(() => [
    { label: 'Penerbit', value: props.book.penerbit },
    { label: 'Tahun Terbit', value: props.book.tahun },
    { label: 'ISBN', value: props.book.isbn },
    { label: 'Jumlah Halaman', value: props.book.jumlah_halaman },
    { label: 'Dimensi', value: props.book.dimensi },
    { label: 'Berat', value: props.book.berat_gr ? `${props.book.berat_gr} gr` : null },
    { label: 'Jenis Kertas', value: props.book.jenis_kertas },
    { label: 'Kemasan', value: props.book.kemasan },
    { label: 'Cetakan', value: props.book.cetakan },
    { label: 'Rating Umur', value: props.book.rating_umur },
].filter((spec) => spec.value !== null && spec.value !== undefined));
</script>

<template>

    <Head :title="book.judul">
        <meta
            name="description"
            :content="
                book.sinopsis
                    ? book.sinopsis.slice(0, 160)
                    : `${book.judul} — ${book.penulis ?? 'Toko Buku Online'}`
            "
        />
        <meta :property="'og:title'" :content="`${book.judul} — Toko Buku Online`" />
        <meta
            :property="'og:description'"
            :content="book.sinopsis ? book.sinopsis.slice(0, 160) : book.judul"
        />
        <meta v-if="book.cover_url" :property="'og:image'" :content="book.cover_url" />
    </Head>

    <div class="grid gap-8 md:grid-cols-2">
        <div
            class="flex aspect-[2/3] max-h-[480px] items-center justify-center overflow-hidden rounded-xl border bg-muted">
            <img v-if="book.cover_url" :src="book.cover_url" :alt="book.judul" class="h-full w-full object-cover" />
            <BookCoverPlaceholder
                v-else
                :title="book.judul"
                class="h-full w-full"
            />
        </div>

        <div class="flex flex-col gap-4">
            <div>
                <p v-if="book.category" class="text-sm text-muted-foreground">
                    {{ book.category.nama }}
                </p>
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ book.judul }}
                </h1>
                <p class="text-muted-foreground">
                    {{ book.penulis ?? '—' }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Money :value="book.harga" class="text-2xl font-bold" />
                <span v-if="book.is_preorder"
                    class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                    Preorder
                </span>
            </div>

            <p v-if="book.stok === 0" class="text-sm text-destructive">
                Stok habis — silakan hubungi kami untuk preorder.
            </p>

            <Form :action="CartController.add().url" method="post" class="flex items-center gap-3">
                <input type="hidden" name="book_id" :value="book.id" />
                <input type="hidden" name="qty" :value="qty" />
                <div class="flex items-center gap-2 rounded-md border px-2 py-1">
                    <Button type="button" variant="ghost" size="icon-sm" :disabled="qty <= 1"
                        @click="qty = Math.max(1, qty - 1)">
                        <Minus class="size-3.5" />
                    </Button>
                    <span class="w-8 text-center tabular-nums">{{ qty }}</span>
                    <Button type="button" variant="ghost" size="icon-sm" @click="qty += 1">
                        <Plus class="size-3.5" />
                    </Button>
                </div>
                <Button type="submit" :disabled="book.stok === 0">
                    <ShoppingCart class="size-4" />
                    Beli Sekarang
                </Button>
            </Form>

            <div v-if="book.sinopsis" class="mt-2 grid gap-2 rounded-xl border p-4 text-sm">
                <h2 class="font-semibold">Sinopsis</h2>
                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                    {{ book.sinopsis }}
                </p>
            </div>

            <div v-if="specs.length" class="mt-2 grid gap-2 rounded-xl border p-4 text-sm">
                <h2 class="font-semibold">Spesifikasi</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                    <template v-for="spec in specs" :key="spec.label">
                        <dt class="text-muted-foreground">{{ spec.label }}</dt>
                        <dd class="text-right font-medium">{{ spec.value }}</dd>
                    </template>
                </dl>
            </div>
        </div>
    </div>
</template>
