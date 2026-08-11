<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search, Trash2, Undo2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import SupplierReturnController from '@/actions/App/Http/Controllers/Admin/SupplierReturnController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import FieldHint from '@/components/FieldHint.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { todayWIB } from '@/lib/date';
import { index as indexRoute } from '@/routes/admin/supplier-returns';
import {
    books as bookOptions,
    purchases as purchaseOptions,
} from '@/routes/admin/supplier-returns/options';
import { detail as purchaseDetail } from '@/routes/admin/supplier-returns/options/purchases';

type SupplierOption = { id: string; nama: string };

type ReturnBook = {
    id: string;
    judul: string;
    kode_sku: string | null;
    stock_defect: number;
    stock_normal: number;
};

type PurchaseOption = {
    id: string;
    ref_code: string;
    purchase_date: string;
    warehouse_nama: string;
};

type PurchaseItem = {
    book_id: string;
    judul: string;
    qty: number;
    price: number;
};

type ReturnItem = {
    book_id: string;
    judul: string;
    source: 'defect' | 'normal';
    qty: number;
    price: number;
    reason: string;
};

defineProps<{
    suppliers: SupplierOption[];
}>();

const form = reactive<{
    supplier_id: string;
    return_date: string;
    purchase_id: string;
    notes: string;
    items: ReturnItem[];
}>({
    supplier_id: '',
    return_date: todayWIB(),
    purchase_id: '',
    notes: '',
    items: [],
});

const processing = ref(false);

// Error validasi dari server (400) — ditampilkan di banner atas.
const serverErrors = computed(
    () => (usePage().props.errors ?? {}) as Record<string, string>,
);

// ── Opsi faktur mengikuti supplier terpilih ───────────────────────
const purchases = ref<PurchaseOption[]>([]);
const purchaseItems = ref<Record<string, PurchaseItem>>({});

// Faktur terpilih — untuk info gudang asal retur otomatis.
const selectedPurchase = computed(
    () =>
        purchases.value.find((p) => String(p.id) === form.purchase_id) ?? null,
);

watch(
    () => form.supplier_id,
    async (supplierId) => {
        form.purchase_id = '';
        purchases.value = [];
        purchaseItems.value = {};

        if (!supplierId) {
            return;
        }

        const response = await fetch(
            purchaseOptions({ query: { supplier_id: supplierId } }).url,
        );
        purchases.value = (await response.json()) as PurchaseOption[];
    },
);

// Muat item faktur untuk prefill harga & batas qty retur.
watch(
    () => form.purchase_id,
    async (purchaseId) => {
        purchaseItems.value = {};

        if (!purchaseId || purchaseId === 'none') {
            return;
        }

        const response = await fetch(
            purchaseDetail({ supplierPurchase: purchaseId }).url,
        );
        const items = (await response.json()) as PurchaseItem[];

        purchaseItems.value = Object.fromEntries(
            items.map((item) => [item.book_id, item]),
        );
    },
);

// ── Pencarian buku ────────────────────────────────────────────────
const search = ref('');
const results = ref<ReturnBook[]>([]);

async function searchBooks() {
    const query = search.value.trim();

    if (!query) {
        results.value = [];

        return;
    }

    const response = await fetch(bookOptions({ query: { search: query } }).url);
    results.value = (await response.json()) as ReturnBook[];
}

function addItem(book: ReturnBook) {
    if (form.items.some((item) => item.book_id === book.id)) {
        toast.info('Buku sudah ada di daftar.');

        return;
    }

    // Prefill harga dari faktur (bila buku ada di faktur yang ditautkan).
    const purchaseItem = purchaseItems.value[book.id];

    form.items.push({
        book_id: book.id,
        judul: book.judul,
        source: 'defect',
        qty: 1,
        price: purchaseItem?.price ?? 0,
        reason: '',
    });
    search.value = '';
    results.value = [];
}

function removeItem(index: number) {
    form.items.splice(index, 1);
}

// ── Submit ────────────────────────────────────────────────────────
const total = () =>
    form.items.reduce((sum, item) => sum + item.qty * item.price, 0);

function submit() {
    if (!form.supplier_id) {
        toast.error('Pilih supplier terlebih dahulu.');

        return;
    }

    if (!form.items.length) {
        toast.error('Minimal satu item barang.');

        return;
    }

    if (form.items.some((item) => !item.reason.trim())) {
        toast.error('Alasan retur wajib diisi untuk setiap item.');

        return;
    }

    processing.value = true;

    router.post(
        SupplierReturnController.store.url(),
        {
            supplier_id: form.supplier_id,
            return_date: form.return_date,
            purchase_id:
                form.purchase_id === 'none' ? undefined : form.purchase_id,
            notes: form.notes,
            items: form.items.map(
                ({ book_id, source, qty, price, reason }) => ({
                    book_id,
                    source,
                    qty,
                    price,
                    reason,
                }),
            ),
        },
        {
            onSuccess: () => {
                processing.value = false;
            },
            onError: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Catat Retur Supplier" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Catat Retur Supplier
                </h1>
                <p class="text-sm text-muted-foreground">
                    Mencatat pengembalian barang ke supplier
                </p>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="indexRoute().url">← Kembali</Link>
            </Button>
        </div>

        <FormErrorAlert :errors="serverErrors" />

        <Card>
            <CardHeader>
                <CardTitle class="text-base font-medium"
                    >Informasi Retur</CardTitle
                >
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label>Supplier *</Label>
                    <Select v-model="form.supplier_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="supplier in suppliers"
                                :key="supplier.id"
                                :value="String(supplier.id)"
                            >
                                {{ supplier.nama }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="return_date">Tanggal *</Label>
                    <Input
                        id="return_date"
                        v-model="form.return_date"
                        type="date"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label class="inline-flex w-fit items-center gap-1">
                        Faktur Pembelian
                        <FieldHint
                            text="Tautkan ke faktur untuk mengurangi hutang faktur tersebut."
                        />
                    </Label>
                    <Select
                        v-model="form.purchase_id"
                        :disabled="!form.supplier_id"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Tanpa faktur / bebas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Tanpa faktur</SelectItem>
                            <SelectItem
                                v-for="purchase in purchases"
                                :key="purchase.id"
                                :value="String(purchase.id)"
                            >
                                {{ purchase.ref_code }} ({{
                                    purchase.purchase_date
                                }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="
                            selectedPurchase?.warehouse_nama &&
                            form.purchase_id !== 'none'
                        "
                        class="text-xs text-muted-foreground"
                    >
                        Gudang asal retur otomatis:{' '}
                        <span class="font-medium">
                            {{ selectedPurchase.warehouse_nama }}
                        </span>
                    </p>
                </div>
                <div class="grid gap-2 md:col-span-3">
                    <Label for="notes">Catatan</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Catatan retur (opsional)"
                        rows="2"
                    />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base font-medium"
                    >Item Barang *</CardTitle
                >
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="relative max-w-sm">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Cari judul buku / SKU..."
                        @input="searchBooks"
                    />
                </div>

                <div
                    v-if="results.length"
                    class="max-h-56 overflow-auto rounded-md border"
                >
                    <button
                        v-for="book in results"
                        :key="book.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted"
                        @click="addItem(book)"
                    >
                        <span>
                            {{ book.judul }}
                            <span class="text-muted-foreground">
                                ({{ book.kode_sku ?? 'tanpa SKU' }})
                            </span>
                        </span>
                        <span class="text-xs text-muted-foreground">
                            Cacat {{ book.stock_defect }} · Normal
                            {{ book.stock_normal }}
                        </span>
                    </button>
                </div>

                <Table v-if="form.items.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Buku</TableHead>
                            <TableHead class="w-32">Sumber Stok</TableHead>
                            <TableHead class="w-24">Qty</TableHead>
                            <TableHead class="w-32">Harga</TableHead>
                            <TableHead class="min-w-48">Alasan *</TableHead>
                            <TableHead class="w-32 text-right"
                                >Subtotal</TableHead
                            >
                            <TableHead class="w-12 text-right">
                                <span class="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(item, index) in form.items"
                            :key="item.book_id"
                        >
                            <TableCell class="font-medium">
                                {{ item.judul }}
                            </TableCell>
                            <TableCell>
                                <Select v-model="item.source">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="defect"
                                            >Stok Cacat</SelectItem
                                        >
                                        <SelectItem value="normal"
                                            >Stok Normal</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model.number="item.qty"
                                    type="number"
                                    min="1"
                                />
                            </TableCell>
                            <TableCell>
                                <CurrencyInput
                                    v-model="item.price"
                                    input-class="h-8 w-32"
                                    placeholder="Harga"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="item.reason"
                                    placeholder="mis. Salah kirim, halaman rusak..."
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <Money :value="item.qty * item.price" />
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-muted-foreground"
                                    @click="removeItem(index)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        Total retur:
                        <span class="font-semibold text-foreground">
                            <Money :value="total()" />
                        </span>
                    </p>
                    <div class="flex items-center gap-3">
                        <Button variant="outline" type="button" as-child>
                            <Link :href="indexRoute().url">Batal</Link>
                        </Button>
                        <Button :disabled="processing" @click="submit">
                            <Undo2 class="size-4" />
                            {{ processing ? 'Menyimpan...' : 'Simpan Retur' }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
