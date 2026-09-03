<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronDown,
    Clock,
    Landmark,
    Package,
    Printer,
    ShoppingBag,
    Upload,
    X,
    ZoomIn,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import MyOrderController from '@/actions/App/Http/Controllers/MyOrderController';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EditorialLayout from '@/layouts/customer/EditorialLayout.vue';
import { invoice as invoiceRoute } from '@/routes/my-orders';

type OrderItem = {
    id: string;
    judul_snapshot: string;
    edition_snapshot: string | null;
    qty: number;
    price_final: number;
};

type Order = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    total: number;
    shipping_cost: number;
    voucher_code_snapshot: string | null;
    voucher_scope_snapshot: string;
    voucher_discount_amount: number;
    metode_bayar: string;
    payment_status: string;
    bukti_transfer_path: string | null;
    bukti_transfer_at: string | null;
    items: OrderItem[];
};

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

const props = defineProps<{
    order: Order;
    bankAccounts: BankAccount[];
}>();

defineOptions({
    layout: EditorialLayout,
});

// Upload bukti butuh login (endpoint my-orders.upload-bukti terproteksi).
const page = usePage();
const isLoggedIn = computed(() => Boolean(page.props.auth?.user));

const selectedFile = ref('');

// Pratinjau lokal file terpilih — user cek dulu sebelum kirim ke server.
const fileInputEl = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const previewOpen = ref(false);

function revokePreview(): void {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function onFilePick(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    selectedFile.value = file?.name ?? '';
    revokePreview();

    if (file && file.type.startsWith('image/')) {
        previewUrl.value = URL.createObjectURL(file);
    }
}

// Batalkan pilihan file — reset input agar memilih file sama tetap trigger change.
function removeFile(): void {
    if (fileInputEl.value) {
        fileInputEl.value.value = '';
    }

    selectedFile.value = '';
    revokePreview();
}

// Upload sukses → props ter-refresh & preview server tampil; bersihkan lokal.
function onUploadSuccess(): void {
    selectedFile.value = '';
    revokePreview();
    replacing.value = false;
}

// Mode ganti bukti — sembunyikan preview server & tampilkan form lagi.
const replacing = ref(false);

onBeforeUnmount(revokePreview);

const buktiUrl = computed(() =>
    props.order.bukti_transfer_path
        ? `/storage/${props.order.bukti_transfer_path}`
        : null,
);

const isPaid = computed(() => props.order.payment_status !== 'menunggu');

// Rekening dikelompokkan per nama bank — bila lebih dari 1 bank,
// daftar jadi collapsible per bank agar tidak memanjang di mobile.
const bankGroups = computed<{ bankName: string; accounts: BankAccount[] }[]>(
    () => {
        const groups = new Map<string, BankAccount[]>();

        for (const account of props.bankAccounts) {
            const list = groups.get(account.bank_name) ?? [];
            list.push(account);
            groups.set(account.bank_name, list);
        }

        return [...groups.entries()].map(([bankName, accounts]) => ({
            bankName,
            accounts,
        }));
    },
);
</script>

<template>
    <Head title="Pesanan Berhasil" />

    <div
        class="mx-auto flex max-w-xl flex-col items-center gap-5 px-4 pt-8 pb-24 text-center sm:gap-6 md:px-6 md:pt-12 lg:pb-8"
    >
        <CheckCircle2 class="size-14 text-green-600" />
        <div>
            <h1
                class="font-serif text-2xl font-bold tracking-tight text-article-ink md:text-3xl"
            >
                Terima kasih, {{ order.nama_pembeli }}!
            </h1>
            <p class="mt-2 text-sm text-article-muted">
                Pesanan Anda telah kami terima. Segera selesaikan pembayaran
                agar pesanan tidak dibatalkan otomatis.
            </p>
        </div>

        <!-- ── Langkah 1: Transfer + unggah bukti ── -->
        <div
            class="w-full rounded-xl border border-article-border p-4 text-left sm:p-6"
        >
            <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">No. Order</span>
                <span class="font-mono font-semibold">{{
                    order.no_order
                }}</span>
            </div>
            <div class="mt-2 flex justify-between text-sm">
                <span class="text-muted-foreground">Metode Bayar</span>
                <span class="font-medium">{{ order.metode_bayar }}</span>
            </div>
            <div
                v-if="order.voucher_discount_amount > 0"
                class="mt-2 flex justify-between text-sm"
            >
                <span class="text-muted-foreground"
                    >Voucher {{ order.voucher_code_snapshot ?? 'Diskon'
                    }}<template
                        v-if="order.voucher_scope_snapshot === 'ongkir'"
                    >
                        (ongkir)</template
                    ></span
                >
                <Money
                    :value="-order.voucher_discount_amount"
                    class="font-medium text-destructive"
                />
            </div>
            <div class="mt-2 flex justify-between border-t pt-2 text-sm">
                <span class="text-muted-foreground">Total</span>
                <Money :value="order.total" class="font-semibold" />
            </div>

            <ul class="mt-4 flex flex-col gap-1 border-t pt-4">
                <li
                    v-for="item in order.items"
                    :key="item.id"
                    class="flex justify-between text-sm"
                >
                    <span class="text-muted-foreground">
                        {{ item.judul_snapshot }} × {{ item.qty }}
                        <span
                            v-if="item.edition_snapshot"
                            class="text-xs text-muted-foreground"
                        >
                            ({{ item.edition_snapshot }})
                        </span>
                    </span>
                    <Money :value="item.price_final * item.qty" />
                </li>
            </ul>

            <!-- Instruksi transfer -->
            <div class="mt-4 rounded-lg bg-muted p-3 text-sm sm:p-4">
                <p class="flex items-center gap-1.5 font-medium">
                    <Landmark class="size-4 text-primary" />
                    Cara Membayar
                </p>
                <template v-if="bankAccounts.length > 0">
                    <p class="mt-1 text-muted-foreground">
                        Transfer tepat sesuai nominal ke salah satu rekening
                        berikut:
                    </p>

                    <!-- 1 bank → satu card utuh tanpa collapse -->
                    <div
                        v-if="bankGroups.length === 1"
                        class="mt-2 rounded-md border bg-background text-left"
                    >
                        <p class="border-b px-3 py-2 font-medium">
                            {{ bankGroups[0].bankName }}
                        </p>
                        <div class="divide-y">
                            <div
                                v-for="account in bankAccounts"
                                :key="account.id"
                                class="px-3 py-2"
                            >
                                <p class="tabular-nums">
                                    {{ account.account_number }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    a.n. {{ account.account_holder }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- >1 bank → collapsible per nama bank, 1 card per bank -->
                    <div v-else class="mt-2 grid gap-2">
                        <Collapsible
                            v-for="(group, index) in bankGroups"
                            :key="group.bankName"
                            :default-open="index === 0"
                            class="group/bank overflow-hidden rounded-md border bg-background text-left"
                        >
                            <CollapsibleTrigger
                                class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-accent"
                            >
                                <span
                                    class="min-w-0 flex-1 truncate font-medium"
                                >
                                    {{ group.bankName }}
                                </span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ group.accounts.length }} rekening
                                </span>
                                <ChevronDown
                                    class="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]/bank:rotate-180"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <ul class="divide-y border-t">
                                    <li
                                        v-for="account in group.accounts"
                                        :key="account.id"
                                        class="px-3 py-2"
                                    >
                                        <p class="tabular-nums">
                                            {{ account.account_number }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            a.n.
                                            {{ account.account_holder }}
                                        </p>
                                    </li>
                                </ul>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </template>
                <p v-else class="mt-1 text-muted-foreground">
                    Hubungi kami untuk instruksi pembayaran.
                </p>
                <p
                    class="mt-2 flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                >
                    <Clock class="size-3.5 shrink-0" />
                    Selesaikan pembayaran dalam 24 jam — jika tidak, pesanan
                    otomatis dibatalkan.
                </p>
            </div>

            <!-- ── Bukti transfer ── -->
            <div class="mt-4 border-t pt-4">
                <!-- Sudah lunas (diverifikasi admin) -->
                <template v-if="isPaid">
                    <p
                        class="flex items-center gap-1.5 text-sm font-medium text-emerald-700"
                    >
                        <CheckCircle2 class="size-4" />
                        Pembayaran sudah diterima — terima kasih.
                    </p>
                </template>

                <!-- Sudah unggah bukti → preview (+ ganti bila salah kirim) -->
                <template v-else-if="buktiUrl && !replacing">
                    <p
                        class="flex items-center gap-1.5 text-sm font-medium text-emerald-600"
                    >
                        <CheckCircle2 class="size-4" />
                        Bukti transfer terkirim — menunggu verifikasi admin.
                    </p>
                    <img
                        :src="buktiUrl"
                        alt="Bukti transfer"
                        class="mt-2 max-h-56 w-full rounded-lg border bg-muted object-contain"
                    />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <a :href="buktiUrl" target="_blank" rel="noopener">
                                Buka Bukti
                            </a>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="replacing = true"
                        >
                            <Upload class="size-4" />
                            Ganti Bukti
                        </Button>
                    </div>
                </template>

                <!-- Belum unggah → form langsung di halaman ini -->
                <template v-else>
                    <Form
                        v-if="isLoggedIn"
                        :action="MyOrderController.uploadBukti(order.id).url"
                        method="post"
                        enctype="multipart/form-data"
                        class="flex flex-col items-start gap-2"
                        v-slot="{ processing }"
                        @success="onUploadSuccess"
                    >
                        <Label for="bukti" class="text-sm font-medium">
                            Upload bukti transfer
                        </Label>
                        <div class="flex w-full flex-wrap items-center gap-2">
                            <Input
                                id="bukti"
                                ref="fileInputEl"
                                type="file"
                                name="bukti"
                                accept="image/jpeg,image/png,image/webp"
                                class="max-w-56 text-xs"
                                required
                                :disabled="processing"
                                @change="onFilePick"
                            />
                            <Button type="submit" :disabled="processing">
                                <Upload class="size-4" />
                                {{ processing ? 'Mengirim...' : 'Kirim Bukti' }}
                            </Button>
                        </div>

                        <!-- Pratinjau kecil — klik utk perbesar & pastikan gambar benar -->
                        <div
                            v-if="previewUrl"
                            class="flex items-start gap-3 rounded-lg border border-article-border bg-article-surface p-2"
                        >
                            <button
                                type="button"
                                class="group relative size-16 shrink-0 overflow-hidden rounded-md border bg-muted focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:outline-none"
                                aria-label="Perbesar pratinjau bukti transfer"
                                @click="previewOpen = true"
                            >
                                <img
                                    :src="previewUrl"
                                    alt="Pratinjau bukti transfer"
                                    class="size-full object-cover"
                                />
                                <span
                                    class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                                >
                                    <ZoomIn class="size-5 text-white" />
                                </span>
                            </button>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-medium">
                                    {{ selectedFile }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Klik gambar untuk memastikan bukti sudah
                                    benar sebelum dikirim.
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-1 h-7 px-2 text-xs text-destructive hover:text-destructive"
                                    @click="removeFile"
                                >
                                    <X class="size-3.5" />
                                    Hapus
                                </Button>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            JPG/PNG/WebP maks 2MB — pesanan yang sudah mengirim
                            bukti tidak akan dibatalkan otomatis.
                        </p>
                    </Form>

                    <!-- Guest: order dari session — minta login dulu -->
                    <template v-else>
                        <p class="text-sm text-muted-foreground">
                            Masuk ke akun Anda untuk mengunggah bukti transfer
                            dan memantau status pesanan.
                        </p>
                        <Button as-child size="sm" class="mt-2">
                            <Link href="/login">Masuk untuk Unggah Bukti</Link>
                        </Button>
                    </template>
                </template>
            </div>
        </div>

        <!-- Actions — mobile: primary full-width + secondary berdampingan;
             desktop: 3 kolom rata -->
        <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-3">
            <Button as-child class="col-span-2 sm:col-span-1">
                <Link href="/pesanan-saya">
                    <Package class="size-4" />
                    Lihat Pesanan
                </Link>
            </Button>
            <Button as-child variant="outline">
                <Link href="/buku">
                    <ShoppingBag class="size-4" />
                    Lanjut Belanja
                </Link>
            </Button>
            <Button as-child variant="outline">
                <a
                    :href="invoiceRoute(order.id).url"
                    target="_blank"
                    rel="noopener"
                >
                    <Printer class="size-4" />
                    Cetak Invoice
                </a>
            </Button>
        </div>
    </div>

    <!-- Lightbox pratinjau bukti (sebelum dikirim) -->
    <Dialog v-model:open="previewOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Pratinjau Bukti Transfer</DialogTitle>
                <DialogDescription>
                    Pastikan nominal, nama pengirim, dan tujuan transfer
                    terlihat jelas sebelum dikirim.
                </DialogDescription>
            </DialogHeader>
            <img
                v-if="previewUrl"
                :src="previewUrl"
                alt="Pratinjau bukti transfer"
                class="max-h-[60vh] w-full rounded-lg border bg-muted object-contain"
            />
        </DialogContent>
    </Dialog>
</template>
