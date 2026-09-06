<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { BellRing, Check, Minus, Plus, ShoppingCart } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import StockRequestController from '@/actions/App/Http/Controllers/StockRequestController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import EditorialLayout from '@/layouts/customer/EditorialLayout.vue';
import { formatDateID } from '@/lib/date';
import { getStockStatus, stockLabel } from '@/lib/stock';

type BookEdition = {
    id: string;
    cetakan_ke: number;
    harga_beli: number;
    harga_jual: number;
    harga_guru_type?: string | null;
    harga_guru_value?: number | null;
    is_active: boolean;
    stok_sellable?: number;
};

type BookImage = {
    id: string;
    image_url: string;
    urutan: number;
};

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    penterjemah: string | null;
    penerbit: string | null;
    tahun: number | null;
    isbn: string | null;
    sinopsis: string | null;
    harga: number;
    stok: number;
    stock_status?: 'preorder' | 'habis' | 'menipis' | 'tersedia';
    stock_label?: string;
    cover_url: string | null;
    is_preorder: boolean;
    preorder_eta: string | null;
    category: { id: string; nama: string } | null;
    rating_umur: string | null;
    dimensi: string | null;
    kemasan: string | null;
    berat_gr: number | null;
    jumlah_halaman: number | null;
    jenis_kertas: string | null;
    cetakan: string | null;
    bahasa: string | null;
    jenis_cover: string | null;
    editions?: BookEdition[];
    images?: BookImage[];
    price_breakdown?: {
        original_price: number;
        promo_discount: number;
        tier_discount?: number;
        final_price: number;
        promo_name: string | null;
    } | null;
};

const props = defineProps<{
    book: Book;
    /** Apakah user login sudah mengajukan stok buku ini. */
    requested?: boolean;
}>();

defineOptions({
    layout: EditorialLayout,
});

// User login — tombol "Ajukan Stok" hanya untuk yang sudah login.
const page = usePage();
const isLoggedIn = computed(() =>
    Boolean(
        (page.props as unknown as { auth?: { user?: unknown } }).auth?.user,
    ),
);
const lowStockThreshold = computed(
    () =>
        (page.props as unknown as { lowStockThreshold?: number })
            .lowStockThreshold ?? 5,
);
const userWhatsapp = computed<string | null>(() => {
    const user = page.props.auth?.user as
        { whatsapp_number?: string | null } | undefined;

    return user?.whatsapp_number ?? null;
});

// Dialog konfirmasi nomor WhatsApp untuk pengajuan stok (waitlist).
const stockDialogOpen = ref(false);

function openStockRequestDialog(): void {
    stockDialogOpen.value = true;
}

// Animasi singkat saat buku berhasil masuk keranjang.
const cartAnimating = ref(false);
let cartAnimationTimer: ReturnType<typeof setTimeout> | undefined;

function handleAddedToCart(): void {
    cartAnimating.value = true;
    clearTimeout(cartAnimationTimer);
    cartAnimationTimer = setTimeout(() => {
        cartAnimating.value = false;
    }, 900);

    toast.success('Buku masuk keranjang', {
        description: `${props.book.judul} berhasil ditambahkan.`,
        action: {
            label: 'Lihat Keranjang',
            onClick: () => {
                window.location.href = '/checkout';
            },
        },
        duration: 4000,
    });
}

const qty = ref(1);

const userTier = computed(() => {
    const user = (
        page.props as unknown as {
            auth?: { user?: { status_pelanggan?: string } };
        }
    ).auth?.user;

    return (user as any)?.status_pelanggan ?? null;
});
const isGuru = computed(() => userTier.value === 'guru');

function guruPriceForEdition(
    edition: BookEdition | null | undefined,
): number | null {
    if (!edition || !edition.harga_guru_type || !edition.harga_guru_value) {
        return null;
    }

    if (edition.harga_guru_type === 'percent') {
        const p = Math.min(100, Math.max(1, Number(edition.harga_guru_value)));

        return Math.floor((edition.harga_jual * (100 - p)) / 100);
    }

    if (edition.harga_guru_type === 'fixed') {
        return Number(edition.harga_guru_value);
    }

    return null;
}

// Galeri gambar: cover utama diikuti gambar galeri (urut sesuai urutan).
const galleryImages = computed<BookImage[]>(() => [
    ...(props.book.cover_url
        ? [{ id: 'cover', image_url: props.book.cover_url, urutan: -1 }]
        : []),
    ...(props.book.images ?? []),
]);
const selectedImageIndex = ref(0);
const selectedImage = computed(
    () => galleryImages.value[selectedImageIndex.value] ?? null,
);

// ── Swipe (mobile) ──
const touchStartX = ref(0);
const touchEndX = ref(0);
const touchActive = ref(false);

function onTouchStart(e: TouchEvent): void {
    touchStartX.value = e.touches[0].clientX;
    touchActive.value = true;
}

function onTouchEnd(e: TouchEvent): void {
    if (!touchActive.value) {
        return;
    }

    touchEndX.value = e.changedTouches[0].clientX;
    const diff = touchStartX.value - touchEndX.value;

    if (Math.abs(diff) > 50) {
        if (
            diff > 0 &&
            selectedImageIndex.value < galleryImages.value.length - 1
        ) {
            selectedImageIndex.value++;
        } else if (diff < 0 && selectedImageIndex.value > 0) {
            selectedImageIndex.value--;
        }
    }

    touchActive.value = false;
}

// ── Zoom (desktop) ──
const isZoomed = ref(false);
const zoomOrigin = ref({ x: 50, y: 50 });

function toggleZoom(): void {
    isZoomed.value = !isZoomed.value;
}

function onMouseMove(e: MouseEvent): void {
    if (!isZoomed.value) {
        return;
    }

    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    zoomOrigin.value = {
        x: ((e.clientX - rect.left) / rect.width) * 100,
        y: ((e.clientY - rect.top) / rect.height) * 100,
    };
}

function onMouseLeave(): void {
    isZoomed.value = false;
}

// Cetakan terpilih — default: cetakan aktif (atau yang pertama).
const editions = computed<BookEdition[]>(() => props.book.editions ?? []);
const selectedEdition = ref<BookEdition | null>(
    editions.value.find((e) => e.is_active) ?? editions.value[0] ?? null,
);

// Maksimal qty = stok sellable cetakan terpilih (bukan total seluruh buku).
// Buku pre-order menunggu stok → batas memakai config (bukan stok).
const PREORDER_MAX_QTY = 99;
const isPreorder = computed(() => props.book.is_preorder === true);

const maxQty = computed(() => {
    if (isPreorder.value) {
        return PREORDER_MAX_QTY;
    }

    if (selectedEdition.value) {
        return Math.max(selectedEdition.value.stok_sellable ?? 0, 0);
    }

    return Math.max(props.book.stok, 0);
});

// Ganti cetakan → sesuaikan qty agar tidak melebihi stok cetakan baru.
watch(selectedEdition, () => {
    qty.value = Math.min(qty.value, Math.max(maxQty.value, 1));
});

// Harga tampil mengikuti cetakan terpilih — guru dapat harga guru (coret harga asli).
const displayPrice = computed(() => {
    const breakdown = props.book.price_breakdown;
    const isDefaultEdition =
        selectedEdition.value === null ||
        editions.value.findIndex((e) => e.id === selectedEdition.value?.id) ===
            editions.value.findIndex((e) => e.is_active);

    // Cetakan default: pakai breakdown server (sudah termasuk guru + promo)
    if (
        isDefaultEdition &&
        breakdown &&
        breakdown.final_price < breakdown.original_price
    ) {
        return breakdown.final_price;
    }

    // Cetakan lain: untuk guru, pakai harga guru per cetakan
    if (isGuru.value) {
        const guru = guruPriceForEdition(selectedEdition.value as any);

        if (guru !== null) {
            return guru;
        }
    }

    return selectedEdition.value?.harga_jual ?? props.book.harga;
});

// Harga coret — tampil bila ada diskon (promo atau guru).
const displayOriginal = computed(() => {
    const breakdown = props.book.price_breakdown;
    const isDefaultEdition =
        selectedEdition.value === null ||
        editions.value.findIndex((e) => e.id === selectedEdition.value?.id) ===
            editions.value.findIndex((e) => e.is_active);

    if (
        isDefaultEdition &&
        breakdown &&
        breakdown.final_price < breakdown.original_price
    ) {
        return breakdown.original_price;
    }

    // Cetakan non-default: coret bila guru punya harga khusus
    if (isGuru.value) {
        const guru = guruPriceForEdition(selectedEdition.value as any);

        if (guru !== null) {
            return selectedEdition.value?.harga_jual ?? props.book.harga;
        }
    }

    return null;
});

// Persentase diskon untuk badge (guru + promo).
const discountPercent = computed(() => {
    const breakdown = props.book.price_breakdown;
    const isDefaultEdition =
        selectedEdition.value === null ||
        editions.value.findIndex((e) => e.id === selectedEdition.value?.id) ===
            editions.value.findIndex((e) => e.is_active);

    if (
        isDefaultEdition &&
        breakdown &&
        breakdown.final_price < breakdown.original_price
    ) {
        const total = breakdown.original_price - breakdown.final_price;

        return Math.round((total / breakdown.original_price) * 100);
    }

    if (isGuru.value) {
        const guru = guruPriceForEdition(selectedEdition.value as any);
        const original = selectedEdition.value?.harga_jual ?? props.book.harga;

        if (guru !== null && guru < original) {
            return Math.round(((original - guru) / original) * 100);
        }
    }

    return 0;
});

const stockHint = computed(() => {
    if (isPreorder.value) {
        return 'Pre-order: diproses setelah stok tersedia.';
    }

    // Prefer stock_label dari server bila stok utuh (tanpa cetakan terpilih).
    // Untuk cetakan terpilih pakai stok_sellable lokal dengan threshold shared.
    if (
        !selectedEdition.value &&
        (props.book as unknown as { stock_label?: string }).stock_label
    ) {
        return (props.book as unknown as { stock_label: string }).stock_label;
    }

    const status = getStockStatus(maxQty.value, false, lowStockThreshold.value);

    return stockLabel(status);
});

const specs = computed(() =>
    [
        { label: 'Penerbit', value: props.book.penerbit },
        { label: 'Tahun Terbit', value: props.book.tahun },
        { label: 'Jumlah Halaman', value: props.book.jumlah_halaman },
        {
            label: 'Berat',
            value: props.book.berat_gr ? `${props.book.berat_gr} gr` : null,
        },
        { label: 'Dimensi (PxLxT)', value: props.book.dimensi },
        { label: 'ISBN', value: props.book.isbn },
        { label: 'Jenis Kertas', value: props.book.jenis_kertas },
        { label: 'Kemasan', value: props.book.kemasan },
        { label: 'Cetakan', value: props.book.cetakan },
        { label: 'Bahasa', value: props.book.bahasa },
        { label: 'Jenis Cover', value: props.book.jenis_cover },
        { label: 'Rating Umur', value: props.book.rating_umur },
    ].filter((spec) => spec.value !== null && spec.value !== undefined),
);
</script>

<template>
    <Head :title="book.judul">
        <meta
            name="description"
            :content="
                book.sinopsis
                    ? book.sinopsis.slice(0, 160)
                    : `${book.judul} — ${book.penulis ?? 'Toko Buku'}`
            "
        />
        <meta
            :property="'og:title'"
            :content="`${book.judul} — Toko Buku`"
        />
        <meta
            :property="'og:description'"
            :content="book.sinopsis ? book.sinopsis.slice(0, 160) : book.judul"
        />
        <meta
            v-if="book.cover_url"
            :property="'og:image'"
            :content="book.cover_url"
        />
    </Head>

    <div class="mx-auto max-w-6xl px-4 py-8 md:px-6 md:py-12">
        <div class="flex flex-col gap-8">
            <div class="grid gap-8 md:grid-cols-2">
                <div class="mx-auto flex w-full max-w-[360px] flex-col gap-3">
                    <div
                        class="relative flex aspect-[2/3] w-full items-center justify-center overflow-hidden rounded-xl border bg-muted"
                        :class="isZoomed ? 'cursor-zoom-out' : 'cursor-zoom-in'"
                        @click="toggleZoom"
                        @mousemove="onMouseMove"
                        @mouseleave="onMouseLeave"
                        @touchstart="onTouchStart"
                        @touchend="onTouchEnd"
                    >
                        <img
                            v-if="selectedImage"
                            :src="selectedImage.image_url"
                            :alt="book.judul"
                            class="h-full w-full object-contain transition-transform duration-200"
                            :class="isZoomed ? 'scale-150' : 'scale-100'"
                            :style="
                                isZoomed
                                    ? {
                                          transformOrigin: `${zoomOrigin.x}% ${zoomOrigin.y}%`,
                                      }
                                    : undefined
                            "
                        />
                        <BookCoverPlaceholder
                            v-else
                            :title="book.judul"
                            class="h-full w-full"
                        />
                    </div>

                    <div
                        v-if="galleryImages.length > 1"
                        class="flex gap-2 overflow-x-auto pb-1"
                    >
                        <button
                            v-for="(image, i) in galleryImages"
                            :key="image.id"
                            type="button"
                            class="h-20 w-14 shrink-0 overflow-hidden rounded-md border bg-muted transition-colors"
                            :class="
                                i === selectedImageIndex
                                    ? 'border-primary ring-1 ring-primary'
                                    : 'border-border hover:border-muted-foreground'
                            "
                            :aria-label="`Lihat gambar ${i + 1}`"
                            @click="selectedImageIndex = i"
                        >
                            <img
                                :src="image.image_url"
                                :alt="`Gambar ${i + 1}`"
                                class="h-full w-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div>
                        <p
                            v-if="book.category"
                            class="text-sm text-muted-foreground"
                        >
                            {{ book.category.nama }}
                        </p>
                        <h1 class="text-2xl font-bold tracking-tight">
                            {{ book.judul }}
                        </h1>
                        <p class="text-muted-foreground">
                            {{ book.penulis ?? '—' }}
                            <template v-if="book.penterjemah">
                                · Penterjemah: {{ book.penterjemah }}
                            </template>
                        </p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <template v-if="displayOriginal !== null">
                            <div class="flex items-center gap-3">
                                <Money
                                    :value="displayPrice"
                                    class="text-2xl font-bold text-primary"
                                />
                                <span
                                    class="rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                                >
                                    -{{ discountPercent }}%
                                </span>
                                <Money
                                    :value="displayOriginal"
                                    class="text-lg text-muted-foreground line-through"
                                />
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-3">
                                <Money
                                    :value="displayPrice"
                                    class="text-2xl font-bold"
                                />
                            </div>
                        </template>
                    </div>

                    <!-- Buku pre-order (new coming): badge + estimasi tersedia -->
                    <div
                        v-if="book.is_preorder"
                        class="flex flex-col gap-1 rounded-lg border border-sky-200 bg-sky-50 p-3"
                    >
                        <p
                            class="flex items-center gap-2 text-sm font-semibold text-sky-800"
                        >
                            <ShoppingCart class="size-4" />
                            Pre-Order — Buku Baru Akan Datang
                        </p>
                        <p class="text-xs text-sky-700">
                            Estimasi tersedia
                            {{
                                book.preorder_eta
                                    ? formatDateID(book.preorder_eta, {
                                          year: 'numeric',
                                          month: 'short',
                                          day: '2-digit',
                                      })
                                    : '(menyusul)'
                            }}. Pesanan akan diproses setelah stok tiba.
                        </p>
                        <!-- Waitlist tanpa bayar: kabari saat stok tiba -->
                        <Button
                            v-if="isLoggedIn"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="mt-1.5 border-sky-200 text-sky-700 hover:bg-sky-100"
                            :disabled="requested"
                            @click="openStockRequestDialog"
                        >
                            <BellRing class="size-3.5" />
                            {{
                                requested
                                    ? 'Sudah Diajukan'
                                    : 'Beri Tahu Saya Saat Tersedia'
                            }}
                        </Button>
                    </div>

                    <p
                        v-else-if="book.stok === 0"
                        class="text-sm text-destructive"
                    >
                        Stok habis
                        <template v-if="isLoggedIn">
                            — ajukan pemberitahuan, kami kabari saat tersedia.
                        </template>
                        <template v-else> — silakan hubungi kami. </template>
                    </p>

                    <!-- Stok habis + login → ajukan pemberitahuan stok -->
                    <Button
                        v-if="
                            book.stok === 0 && !book.is_preorder && isLoggedIn
                        "
                        type="button"
                        size="lg"
                        :disabled="requested"
                        class="w-full"
                        @click="openStockRequestDialog"
                    >
                        <BellRing class="size-4" />
                        {{ requested ? 'Sudah Diajukan' : 'Ajukan Stok' }}
                    </Button>
                    <p
                        v-if="
                            book.stok === 0 && !book.is_preorder && isLoggedIn
                        "
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            requested
                                ? 'Anda sudah mengajukan pemberitahuan untuk buku ini.'
                                : 'Kami kirim pemberitahuan saat stok kembali tersedia.'
                        }}
                    </p>

                    <!-- Pilih cetakan: tiap cetakan punya harga sendiri -->
                    <div
                        v-if="
                            (book.stok > 0 || book.is_preorder) &&
                            editions.length > 1
                        "
                        class="grid gap-2"
                    >
                        <label class="text-sm font-medium" for="edition-select">
                            Pilih Cetakan
                        </label>
                        <Select
                            :model-value="
                                selectedEdition
                                    ? String(selectedEdition.id)
                                    : undefined
                            "
                            @update:model-value="
                                (val) => {
                                    selectedEdition =
                                        editions.find(
                                            (e) => String(e.id) === val,
                                        ) ?? null;
                                }
                            "
                        >
                            <SelectTrigger
                                id="edition-select"
                                class="w-full max-w-xs"
                            >
                                <SelectValue placeholder="Pilih cetakan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="edition in editions"
                                    :key="edition.id"
                                    :value="String(edition.id)"
                                >
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        Cetakan ke-{{ edition.cetakan_ke }} —
                                        <template
                                            v-if="
                                                isGuru &&
                                                guruPriceForEdition(
                                                    edition as any,
                                                ) !== null
                                            "
                                        >
                                            {{
                                                new Intl.NumberFormat('id-ID', {
                                                    style: 'currency',
                                                    currency: 'IDR',
                                                    maximumFractionDigits: 0,
                                                }).format(
                                                    guruPriceForEdition(
                                                        edition as any,
                                                    )!,
                                                )
                                            }}
                                            <span
                                                class="text-xs text-muted-foreground line-through"
                                            >
                                                {{
                                                    new Intl.NumberFormat(
                                                        'id-ID',
                                                        {
                                                            style: 'currency',
                                                            currency: 'IDR',
                                                            maximumFractionDigits: 0,
                                                        },
                                                    ).format(edition.harga_jual)
                                                }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            {{
                                                new Intl.NumberFormat('id-ID', {
                                                    style: 'currency',
                                                    currency: 'IDR',
                                                    maximumFractionDigits: 0,
                                                }).format(edition.harga_jual)
                                            }}
                                        </template>
                                        <template v-if="edition.is_active">
                                            (default)
                                        </template>
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Form
                        v-if="book.stok > 0 || book.is_preorder"
                        :action="CartController.add().url"
                        method="post"
                        class="flex flex-col gap-3"
                        @success="handleAddedToCart"
                    >
                        <input type="hidden" name="book_id" :value="book.id" />
                        <input
                            type="hidden"
                            name="book_edition_id"
                            :value="selectedEdition?.id ?? ''"
                        />
                        <input type="hidden" name="qty" :value="qty" />
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center gap-2 rounded-md border px-2 py-1"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="qty <= 1"
                                    @click="qty = Math.max(1, qty - 1)"
                                >
                                    <Minus class="size-3.5" />
                                </Button>
                                <span class="w-8 text-center tabular-nums">{{
                                    qty
                                }}</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="qty >= maxQty"
                                    @click="qty = Math.min(qty + 1, maxQty)"
                                >
                                    <Plus class="size-3.5" />
                                </Button>
                            </div>
                            <Button
                                type="submit"
                                :disabled="maxQty === 0"
                                :class="cartAnimating && 'animate-bounce'"
                            >
                                <Check v-if="cartAnimating" class="size-4" />
                                <ShoppingCart v-else class="size-4" />
                                {{
                                    cartAnimating
                                        ? 'Masuk Keranjang!'
                                        : 'Beli Sekarang'
                                }}
                            </Button>
                        </div>
                        <p
                            v-if="maxQty > 0 && qty >= maxQty"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                isPreorder
                                    ? 'Batas maksimal per pesanan tercapai.'
                                    : 'Stok terbatas — kurangi qty atau hubungi kami.'
                            }}
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            {{ stockHint }}
                        </p>
                    </Form>

                    <!-- Detail buku: spesifikasi fisik (dimensi, berat, halaman, dll.) -->
                    <div
                        v-if="specs.length"
                        class="grid gap-3 rounded-xl border p-4 text-sm"
                    >
                        <h2 class="font-semibold">Detail Buku</h2>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3">
                            <div
                                v-for="spec in specs"
                                :key="spec.label"
                                class="flex flex-col gap-0.5"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ spec.label }}
                                </dt>
                                <dd class="font-medium">{{ spec.value }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Sinopsis di bagian bawah sendiri -->
            <div
                v-if="book.sinopsis"
                class="grid gap-3 rounded-xl border p-4 text-sm md:p-6"
            >
                <h2 class="font-semibold">Sinopsis</h2>
                <p
                    class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ book.sinopsis }}
                </p>
            </div>
        </div>
    </div>

    <!-- Dialog konfirmasi nomor WhatsApp (pengajuan stok / waitlist) -->
    <Dialog v-model:open="stockDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Konfirmasi Nomor WhatsApp</DialogTitle>
                <DialogDescription>
                    Kami akan mengirim pemberitahuan ke nomor ini saat stok buku
                    tersedia.
                </DialogDescription>
            </DialogHeader>

            <Form
                :action="StockRequestController.store(book.id).url"
                method="post"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="stockDialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="whatsapp_number">Nomor WhatsApp *</Label>
                    <Input
                        id="whatsapp_number"
                        name="whatsapp_number"
                        type="tel"
                        :default-value="userWhatsapp ?? undefined"
                        required
                        placeholder="08xxxxxxxxxx"
                    />
                    <p
                        v-if="errors.whatsapp_number"
                        class="text-sm text-destructive"
                    >
                        {{ errors.whatsapp_number }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="stockDialogOpen = false"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Konfirmasi' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
