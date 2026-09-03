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
import { ArrowLeft, Plus, Trash2, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import SupplierPurchaseController from '@/actions/App/Http/Controllers/Admin/SupplierPurchaseController';
import BookPicker from '@/components/BookPicker.vue';
import type { BookOption } from '@/components/BookPicker.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CurrencyInput from '@/components/CurrencyInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import FieldHint from '@/components/FieldHint.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import Money from '@/components/Money.vue';
import { Badge } from '@/components/ui/badge';
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

type BookEdition = {
    id: string;
    cetakan_ke: number;
    harga_beli: number;
    harga_jual: number;
    is_active: boolean;
    stok?: number;
};

type Book = {
    id: string;
    judul: string;
    kode_sku: string | null;
    harga: number;
    editions?: BookEdition[];
};

type PurchaseItem = {
    book_id: string;
    judul: string;
    book_edition_id: string | null;
    price: number;
    allocations: Record<string, number>;
};

const props = defineProps<{
    suppliers: SupplierOption[];
    warehouses: WarehouseOption[];
}>();

const suppliers = props.suppliers;
const warehouses = props.warehouses;

const form = reactive<{
    supplier_id: string;
    purchase_date: string;
    ref_code: string;
    warehouse_kodes: string[];
    shipping_cost: number;
    paid_amount: number;
    notes: string;
    items: PurchaseItem[];
}>({
    supplier_id: '',
    purchase_date: todayWIB(),
    ref_code: '',
    warehouse_kodes: [],
    shipping_cost: 0,
    paid_amount: 0,
    notes: '',
    items: [],
});

const processing = ref(false);
const warehouseSelectValue = ref('');

// Error validasi dari server
const serverErrors = computed(
    () => (usePage().props.errors ?? {}) as Record<string, string>,
);

// ── Helpers ───────────────────────────────────────────────────────
function warehouseName(kode: string): string {
    return warehouses.find((w) => w.kode === kode)?.nama ?? kode;
}

const availableWarehouses = computed(() =>
    warehouses.filter((w) => !form.warehouse_kodes.includes(w.kode)),
);

function addWarehouse(kode: string) {
    if (!kode || form.warehouse_kodes.includes(kode)) {
        return;
    }

    form.warehouse_kodes.push(kode);

    for (const item of form.items) {
        if (!(kode in item.allocations)) {
            item.allocations[kode] = 0;
        }
    }
}

function removeWarehouse(kode: string) {
    form.warehouse_kodes = form.warehouse_kodes.filter((k) => k !== kode);

    for (const item of form.items) {
        if (kode in item.allocations) {
            delete item.allocations[kode];
        }
    }
}

function onWarehouseSelect(val: unknown) {
    const kode = String(val ?? '');

    if (!kode) {
        return;
    }

    addWarehouse(kode);
    setTimeout(() => {
        warehouseSelectValue.value = '';
    }, 0);
}

function editionOptions(book: Book): BookEdition[] {
    return book.editions ?? [];
}

function defaultEdition(book: Book): BookEdition | null {
    const editions = editionOptions(book);

    if (!editions.length) {
        return null;
    }

    return editions.find((e) => e.is_active) ?? editions[0];
}

// ── Pencarian buku ────────────────────────────────────────────────
function onBookSelect(book: BookOption) {
    addItem(book as Book);
}

function addItem(book: Book) {
    if (form.items.some((item) => item.book_id === book.id)) {
        toast.info('Buku sudah ada di daftar.');

        return;
    }

    if (!form.warehouse_kodes.length) {
        toast.error('Pilih minimal satu gudang tujuan terlebih dahulu.');

        return;
    }

    const edition = defaultEdition(book);
    const allocations: Record<string, number> = {};

    for (const kode of form.warehouse_kodes) {
        allocations[kode] = 1;
    }

    form.items.push({
        book_id: book.id,
        judul: book.judul,
        book_edition_id: edition ? String(edition.id) : null,
        price: edition ? edition.harga_beli : book.harga,
        allocations,
    });
}

const pendingDeleteIndex = ref<number | null>(null);
const pendingDeleteItem = computed<PurchaseItem | null>(() =>
    pendingDeleteIndex.value !== null
        ? (form.items[pendingDeleteIndex.value] ?? null)
        : null,
);
function confirmRemove(index: number) {
    pendingDeleteIndex.value = index;
}
function executeRemove() {
    if (pendingDeleteIndex.value === null) {
        return;
    }

    form.items.splice(pendingDeleteIndex.value, 1);
    pendingDeleteIndex.value = null;
    toast.success('Item dihapus dari daftar.');
}
function cancelRemove(open: boolean) {
    if (!open) {
        pendingDeleteIndex.value = null;
    }
}

// ── Qty & Total ───────────────────────────────────────────────────
function itemTotalQty(item: PurchaseItem): number {
    return Object.values(item.allocations).reduce(
        (sum, v) => sum + (Number(v) || 0),
        0,
    );
}
function itemSubtotal(item: PurchaseItem): number {
    return itemTotalQty(item) * item.price;
}
const totalBarang = () =>
    form.items.reduce((sum, item) => sum + itemSubtotal(item), 0);
const grandTotal = () => totalBarang() + (form.shipping_cost || 0);
const totalQty = computed(() =>
    form.items.reduce((sum, item) => sum + itemTotalQty(item), 0),
);
const shippingPerPcs = computed(() =>
    totalQty.value > 0 && form.shipping_cost > 0
        ? Math.floor(form.shipping_cost / totalQty.value)
        : 0,
);
function previewNewHpp(item: PurchaseItem): number | null {
    const editions = bookEditionsMap[item.book_id];

    if (!editions?.length) {
        return null;
    }

    const edition = editions.find(
        (e) => String(e.id) === String(item.book_edition_id),
    );

    if (!edition) {
        return null;
    }

    const oldStock = Number(edition.stok ?? 0);
    const oldHpp = Number(edition.harga_beli ?? 0);
    const landed = Number(item.price ?? 0) + shippingPerPcs.value;
    const qty = itemTotalQty(item);

    if (qty <= 0) {
        return oldHpp;
    }

    if (oldStock <= 0) {
        return landed;
    }

    return Math.round((oldStock * oldHpp + qty * landed) / (oldStock + qty));
}
const totalLabelColspan = computed(() => 5 + form.warehouse_kodes.length);

// ── Submit ────────────────────────────────────────────────────────
function submit() {
    if (!form.supplier_id) {
        toast.error('Pilih supplier terlebih dahulu.');

        return;
    }

    if (!form.warehouse_kodes.length) {
        toast.error('Pilih minimal satu gudang tujuan.');

        return;
    }

    if (!form.items.length) {
        toast.error('Minimal satu item barang.');

        return;
    }

    for (const item of form.items) {
        if (itemTotalQty(item) < 1) {
            toast.error(
                `Qty untuk "${item.judul}" minimal 1 di salah satu gudang.`,
            );

            return;
        }
    }

    processing.value = true;
    router.post(
        SupplierPurchaseController.store.url(),
        {
            supplier_id: form.supplier_id,
            ref_code: form.ref_code,
            purchase_date: form.purchase_date,
            warehouse_kodes: form.warehouse_kodes,
            warehouse_kode: form.warehouse_kodes[0],
            shipping_cost: form.shipping_cost || 0,
            paid_amount: form.paid_amount,
            notes: form.notes,
            items: form.items.map(
                ({ book_id, book_edition_id, price, allocations }) => ({
                    book_id,
                    book_edition_id: book_edition_id || null,
                    price,
                    allocations: Object.entries(allocations)
                        .filter(([, qty]) => Number(qty) > 0)
                        .map(([warehouse_kode, qty]) => ({
                            warehouse_kode,
                            qty: Number(qty),
                        })),
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

const bookEditionsMap = reactive<Record<string, BookEdition[]>>({});
function handleBookSelectWithEditions(book: BookOption) {
    const b = book as Book & { editions?: BookEdition[] };

    if (b.editions) {
        bookEditionsMap[b.id] = b.editions;
    }

    onBookSelect(book);
}
</script>

<template>
    <Head title="Catat Barang Masuk" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    as-child
                >
                    <Link :href="indexRoute().url"
                        ><ArrowLeft class="size-4"
                    /></Link>
                </Button>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        Catat Barang Masuk
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Mencatat pembelian buku dari supplier
                    </p>
                </div>
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
                        <SelectTrigger
                            ><SelectValue placeholder="Pilih supplier"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="supplier in suppliers"
                                :key="supplier.id"
                                :value="String(supplier.id)"
                                >{{ supplier.nama }}</SelectItem
                            >
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
                <div class="grid gap-2 md:col-span-3">
                    <Label>Gudang Tujuan *</Label>
                    <div
                        class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border border-input bg-transparent px-2 py-1.5"
                    >
                        <Badge
                            v-for="kode in form.warehouse_kodes"
                            :key="kode"
                            variant="secondary"
                            class="gap-1 pr-1"
                        >
                            {{ warehouseName(kode) }}
                            <button
                                type="button"
                                class="ml-1 rounded-full p-0.5 hover:bg-muted"
                                @click="removeWarehouse(kode)"
                            >
                                <X class="size-3" />
                            </button>
                        </Badge>
                        <Select
                            v-model="warehouseSelectValue"
                            @update:model-value="onWarehouseSelect"
                        >
                            <SelectTrigger
                                class="h-7 min-w-32 flex-1 border-0 bg-transparent px-2 shadow-none focus:ring-0"
                            >
                                <SelectValue placeholder="+ Tambah gudang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="w in availableWarehouses"
                                    :key="w.kode"
                                    :value="w.kode"
                                    >{{ w.nama }} ({{ w.kode }})</SelectItem
                                >
                                <div
                                    v-if="!availableWarehouses.length"
                                    class="px-2 py-1.5 text-sm text-muted-foreground"
                                >
                                    {{
                                        warehouses.length
                                            ? 'Semua gudang terpilih'
                                            : 'Tidak ada gudang tersedia'
                                    }}
                                </div>
                            </SelectContent>
                        </Select>
                    </div>
                    <p
                        v-if="!form.warehouse_kodes.length"
                        class="text-xs text-muted-foreground"
                    >
                        Pilih minimal satu gudang. Bisa pilih 2 atau lebih.
                    </p>
                    <p v-else class="text-xs text-muted-foreground">
                        {{ form.warehouse_kodes.length }} gudang dipilih
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label
                        for="shipping_cost"
                        class="inline-flex w-fit items-center gap-1"
                    >
                        Ongkos Kirim (Rp)
                        <FieldHint
                            text="Opsional, 0 jika gratis. Mempengaruhi HPP (landed cost)."
                        />
                    </Label>
                    <CurrencyInput
                        id="shipping_cost"
                        v-model="form.shipping_cost"
                        placeholder="0"
                    />
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
                <div class="grid gap-2 md:col-span-3">
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
            <CardContent class="flex min-h-[420px] flex-col gap-4">
                <BookPicker
                    :base-url="bookOptions().url"
                    class="max-w-sm"
                    placeholder="Cari judul buku / SKU..."
                    @select="handleBookSelectWithEditions"
                />
                <p
                    v-if="!form.warehouse_kodes.length"
                    class="text-sm text-amber-600 dark:text-amber-400"
                >
                    Pilih gudang tujuan dulu sebelum menambah item — kolom Qty
                    per gudang akan muncul sesuai pilihan.
                </p>

                <div
                    v-if="form.items.length"
                    class="overflow-x-auto rounded-md border bg-card"
                >
                    <Table class="min-w-[900px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead
                                    class="sticky left-0 z-10 w-64 bg-muted/50 whitespace-nowrap backdrop-blur"
                                    >Buku</TableHead
                                >
                                <TableHead
                                    class="w-24 bg-muted/50 whitespace-nowrap"
                                    >Cetakan</TableHead
                                >
                                <template v-if="form.warehouse_kodes.length">
                                    <TableHead
                                        v-for="kode in form.warehouse_kodes"
                                        :key="kode"
                                        class="w-20 bg-muted/50 px-2 text-center whitespace-nowrap"
                                    >
                                        <div
                                            class="flex flex-col items-center leading-tight"
                                        >
                                            <span class="text-xs font-medium">{{
                                                warehouseName(kode)
                                            }}</span>
                                            <span
                                                class="text-xs font-normal text-muted-foreground"
                                                >Qty</span
                                            >
                                        </div>
                                    </TableHead>
                                </template>
                                <TableHead v-else class="w-20 bg-muted/50"
                                    >Qty</TableHead
                                >
                                <TableHead
                                    class="w-28 bg-muted/50 whitespace-nowrap"
                                    >Harga Beli</TableHead
                                >
                                <TableHead
                                    class="w-20 bg-muted/50 px-2 text-right whitespace-nowrap"
                                    >HPP Lama</TableHead
                                >
                                <TableHead
                                    class="w-20 bg-muted/50 px-2 text-right whitespace-nowrap"
                                    >HPP Baru</TableHead
                                >
                                <TableHead
                                    class="sticky right-12 z-10 w-28 bg-muted/50 text-right whitespace-nowrap"
                                    >Subtotal</TableHead
                                >
                                <TableHead
                                    class="sticky right-0 z-10 w-12 bg-muted/50 text-right"
                                    ><span class="sr-only"
                                        >Aksi</span
                                    ></TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(item, index) in form.items"
                                :key="item.book_id"
                                class="group"
                            >
                                <TableCell
                                    class="sticky left-0 z-10 bg-card font-medium whitespace-nowrap transition-colors group-hover:bg-muted/50"
                                >
                                    <span class="block max-w-64 truncate">{{
                                        item.judul
                                    }}</span>
                                </TableCell>
                                <TableCell class="px-2">
                                    <template
                                        v-if="
                                            bookEditionsMap[item.book_id]
                                                ?.length
                                        "
                                    >
                                        <Select v-model="item.book_edition_id">
                                            <SelectTrigger
                                                class="h-8 w-20 px-2 text-xs"
                                                ><SelectValue
                                                    placeholder="Cet."
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="edition in bookEditionsMap[
                                                        item.book_id
                                                    ]"
                                                    :key="edition.id"
                                                    :value="String(edition.id)"
                                                    >Cet.
                                                    {{
                                                        edition.cetakan_ke
                                                    }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </template>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <template v-if="form.warehouse_kodes.length">
                                    <TableCell
                                        v-for="kode in form.warehouse_kodes"
                                        :key="kode"
                                        class="px-2 text-center"
                                    >
                                        <Input
                                            :model-value="
                                                item.allocations[kode] ?? 0
                                            "
                                            type="number"
                                            min="0"
                                            class="h-8 w-14 [appearance:textfield] px-1 text-center tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            @update:model-value="
                                                (val) => {
                                                    item.allocations[kode] =
                                                        Number(val) || 0;
                                                }
                                            "
                                        />
                                    </TableCell>
                                </template>
                                <TableCell v-else class="px-2">
                                    <Input
                                        :model-value="0"
                                        type="number"
                                        disabled
                                        class="h-8 w-14 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                    />
                                </TableCell>
                                <TableCell>
                                    <CurrencyInput
                                        v-model="item.price"
                                        input-class="h-8 w-24"
                                        placeholder="Harga beli"
                                    />
                                </TableCell>
                                <TableCell
                                    class="px-2 text-right text-xs tabular-nums"
                                >
                                    <template
                                        v-if="
                                            bookEditionsMap[item.book_id]?.find(
                                                (e) =>
                                                    String(e.id) ===
                                                    String(
                                                        item.book_edition_id,
                                                    ),
                                            )
                                        "
                                    >
                                        Rp
                                        {{
                                            (
                                                bookEditionsMap[
                                                    item.book_id
                                                ].find(
                                                    (e) =>
                                                        String(e.id) ===
                                                        String(
                                                            item.book_edition_id,
                                                        ),
                                                )?.harga_beli ?? 0
                                            ).toLocaleString('id-ID')
                                        }}
                                    </template>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell
                                    class="px-2 text-right text-xs font-medium tabular-nums"
                                >
                                    <template
                                        v-if="previewNewHpp(item) !== null"
                                        >Rp
                                        {{
                                            previewNewHpp(item)?.toLocaleString(
                                                'id-ID',
                                            )
                                        }}</template
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell
                                    class="sticky right-12 z-10 bg-card text-right whitespace-nowrap tabular-nums transition-colors group-hover:bg-muted/50"
                                >
                                    <Money :value="itemSubtotal(item)" />
                                </TableCell>
                                <TableCell
                                    class="sticky right-0 z-10 bg-card text-right transition-colors group-hover:bg-muted/50"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-destructive hover:text-destructive"
                                        @click="confirmRemove(index)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow class="bg-muted/50 font-semibold">
                                <TableCell
                                    :colspan="totalLabelColspan"
                                    class="text-right text-muted-foreground"
                                    >Total Barang:</TableCell
                                >
                                <TableCell class="text-right"
                                    ><Money :value="totalBarang()"
                                /></TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                            <TableRow
                                v-if="form.shipping_cost"
                                class="bg-muted/30"
                            >
                                <TableCell
                                    :colspan="totalLabelColspan"
                                    class="text-right text-muted-foreground"
                                    >Ongkir:</TableCell
                                >
                                <TableCell class="text-right"
                                    ><Money :value="form.shipping_cost"
                                /></TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                            <TableRow
                                v-if="form.shipping_cost"
                                class="font-semibold"
                            >
                                <TableCell
                                    :colspan="totalLabelColspan"
                                    class="text-right"
                                    >Grand Total:</TableCell
                                >
                                <TableCell class="text-right"
                                    ><Money :value="grandTotal()"
                                /></TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <EmptyState
                    v-else
                    title="Belum ada item barang"
                    description="Cari judul buku atau SKU di atas untuk menambahkannya ke pembelian."
                />

                <div
                    class="sticky bottom-0 z-10 -mx-4 mt-4 flex flex-wrap items-center justify-start gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6"
                >
                    <Button variant="outline" type="button" as-child>
                        <Link :href="indexRoute().url">Batal</Link>
                    </Button>
                    <Button :disabled="processing" @click="submit">
                        <Plus class="size-4" />
                        {{ processing ? 'Menyimpan...' : 'Simpan Pembelian' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>

    <ConfirmDeleteDialog
        :open="pendingDeleteIndex !== null"
        title="Hapus item?"
        :description="
            pendingDeleteItem
                ? `Buku '${pendingDeleteItem.judul}' akan dihapus dari daftar pembelian.`
                : 'Item akan dihapus dari daftar.'
        "
        confirm-label="Hapus"
        confirm-variant="destructive"
        @update:open="cancelRemove"
        @confirm="executeRemove"
    />
</template>
