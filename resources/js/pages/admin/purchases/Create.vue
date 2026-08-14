<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Barang Masuk', href: '/admin/purchases' },
            { title: 'Buat' },
        ],
    },
});

import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import SupplierPurchaseController from '@/actions/App/Http/Controllers/Admin/SupplierPurchaseController';
import BookPicker from '@/components/BookPicker.vue';
import type { BookOption } from '@/components/BookPicker.vue';
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
import { todayWIB } from '@/lib/date';
import { index as indexRoute } from '@/routes/admin/purchases';
import { books as bookOptions } from '@/routes/admin/purchases/options';

type SupplierOption = { id: string; nama: string };

type WarehouseOption = { kode: string; nama: string };

type Book = {
    id: string;
    judul: string;
    kode_sku: string | null;
    harga: number;
};

type PurchaseItem = {
    book_id: string;
    judul: string;
    qty: number;
    price: number;
};

defineProps<{
    suppliers: SupplierOption[];
    warehouses: WarehouseOption[];
}>();

const form = reactive<{
    supplier_id: string;
    purchase_date: string;
    ref_code: string;
    warehouse_kode: string;
    paid_amount: number;
    notes: string;
    items: PurchaseItem[];
}>({
    supplier_id: '',
    purchase_date: todayWIB(),
    ref_code: '',
    warehouse_kode: '',
    paid_amount: 0,
    notes: '',
    items: [],
});

const processing = ref(false);

// Error validasi dari server (400) — ditampilkan di banner atas.
const serverErrors = computed(
    () => (usePage().props.errors ?? {}) as Record<string, string>,
);

// ── Pencarian buku ────────────────────────────────────────────────
function onBookSelect(book: BookOption) {
    addItem(book as Book);
}

function addItem(book: Book) {
    if (form.items.some((item) => item.book_id === book.id)) {
        toast.info('Buku sudah ada di daftar.');

        return;
    }

    form.items.push({
        book_id: book.id,
        judul: book.judul,
        qty: 1,
        price: book.harga,
    });
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

    processing.value = true;

    router.post(
        SupplierPurchaseController.store.url(),
        {
            supplier_id: form.supplier_id,
            ref_code: form.ref_code,
            purchase_date: form.purchase_date,
            warehouse_kode: form.warehouse_kode,
            paid_amount: form.paid_amount,
            notes: form.notes,
            items: form.items.map(({ book_id, qty, price }) => ({
                book_id,
                qty,
                price,
            })),
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
    <Head title="Catat Barang Masuk" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Catat Barang Masuk
                </h1>
                <p class="text-sm text-muted-foreground">
                    Mencatat pembelian buku dari supplier
                </p>
            </div>
        </div>

        <FormErrorAlert :errors="serverErrors" />

        <Card>
            <CardHeader>
                <CardTitle class="text-base font-medium"
                    >Informasi Pembelian</CardTitle
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
                    <Label for="purchase_date">Tanggal *</Label>
                    <Input
                        id="purchase_date"
                        v-model="form.purchase_date"
                        type="date"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="ref_code">Ref Code *</Label>
                    <Input
                        id="ref_code"
                        v-model="form.ref_code"
                        placeholder="mis. PO-20260810-001"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Gudang Tujuan *</Label>
                    <Select v-model="form.warehouse_kode">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih gudang" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="warehouse in warehouses"
                                :key="warehouse.kode"
                                :value="warehouse.kode"
                            >
                                {{ warehouse.nama }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label
                        for="paid_amount"
                        class="inline-flex w-fit items-center gap-1"
                    >
                        Bayar Saat Ini (Rp)
                        <FieldHint
                            text="Kosongkan untuk mencatat sebagai hutang."
                        />
                    </Label>
                    <CurrencyInput
                        id="paid_amount"
                        v-model="form.paid_amount"
                        placeholder="Kosongkan bila hutang"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="notes">Catatan</Label>
                    <Input
                        id="notes"
                        v-model="form.notes"
                        placeholder="Catatan pembelian (opsional)"
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
                <BookPicker
                    :base-url="bookOptions().url"
                    class="max-w-sm"
                    placeholder="Cari judul buku / SKU..."
                    @select="onBookSelect"
                />

                <Table v-if="form.items.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-80">Buku</TableHead>
                            <TableHead class="w-28">Qty</TableHead>
                            <TableHead class="w-32">Harga Beli</TableHead>
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
                                    placeholder="Harga beli"
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
                        Total pembelian:
                        <span class="font-semibold text-foreground">
                            <Money :value="total()" />
                        </span>
                    </p>
                    <div class="flex items-center gap-3">
                        <Button variant="outline" type="button" as-child>
                            <Link :href="indexRoute().url">Batal</Link>
                        </Button>
                        <Button :disabled="processing" @click="submit">
                            <Plus class="size-4" />
                            {{
                                processing ? 'Menyimpan...' : 'Simpan Pembelian'
                            }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
