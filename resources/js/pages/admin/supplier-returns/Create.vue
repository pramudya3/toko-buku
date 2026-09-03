<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Retur Supplier', href: '/admin/supplier-returns' },
            { title: 'Buat' },
        ],
    },
});

import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Trash2, Undo2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import SupplierReturnController from '@/actions/App/Http/Controllers/Admin/SupplierReturnController';
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
    warehouse_kodes?: string[];
    warehouse_kode?: string;
};

type PurchaseItem = {
    book_id: string;
    book_edition_id: string | null;
    judul: string;
    cetakan_ke?: number | null;
    qty: number;
    remaining: number;
    price: number;
    allocations?: Record<string, number>;
};

type ReturnItem = {
    book_id: string;
    book_edition_id: string | null;
    judul: string;
    cetakan_ke?: number | null;
    price: number;
    maxQty: number;
    allocations: Record<string, number>;
};

type WarehouseOption = { kode: string; nama: string };

type ReturnReasonOption = {
    id: string;
    code: string;
    name: string;
    category: string;
};

const props = defineProps<{
    suppliers: SupplierOption[];
    warehouses: WarehouseOption[];
    prefilledSupplierId?: string | null;
    prefilledPurchaseId?: string | null;
    prefilledPurchase?: {
        id: string;
        ref_code: string;
        purchase_date: string;
        warehouse_kode: string;
        warehouse_kodes?: string[] | null;
        warehouse_nama: string;
        items: Array<{
            book_id: string;
            book_edition_id: string | null;
            judul: string;
            qty: number;
            price: number;
            cetakan_ke?: number | null;
            allocations?: Array<{ warehouse_kode: string; qty: number }>;
        }>;
    } | null;
    returnReasons: ReturnReasonOption[];
}>();

const form = reactive<{
    supplier_id: string;
    return_date: string;
    purchase_id: string;
    reason: string;
    source: 'defect' | 'normal';
    shipping_cost: number;
    notes: string;
    items: ReturnItem[];
}>({
    supplier_id: props.prefilledSupplierId ?? '',
    return_date: todayWIB(),
    purchase_id: props.prefilledPurchaseId ?? '',
    reason: '',
    source: 'normal',
    shipping_cost: 0,
    notes: '',
    items: [],
});

const processing = ref(false);
const serverErrors = computed(
    () => (usePage().props.errors ?? {}) as Record<string, string>,
);

const purchases = ref<PurchaseOption[]>([]);
const purchaseItems = ref<Record<string, PurchaseItem>>({});

const selectedPurchase = computed(
    () =>
        purchases.value.find((p) => String(p.id) === form.purchase_id) ?? null,
);

// Gudang dari faktur terpilih — untuk header Qty per gudang (mirror pembelian multi)
const purchaseWarehouseKodes = computed<string[]>(() => {
    const fromPrefilled =
        props.prefilledPurchase?.warehouse_kodes ??
        (props.prefilledPurchase?.warehouse_kode
            ? [props.prefilledPurchase.warehouse_kode]
            : []);

    if (fromPrefilled.length) {
        return [...new Set(fromPrefilled)];
    }

    const fromPurchase =
        selectedPurchase.value?.warehouse_kodes ??
        (selectedPurchase.value?.warehouse_kode
            ? [selectedPurchase.value.warehouse_kode]
            : []);

    if (fromPurchase.length) {
        return [...new Set(fromPurchase)];
    }

    const fromAlloc = Object.values(purchaseItems.value).flatMap((pi) =>
        Object.keys(pi.allocations ?? {}),
    );

    if (fromAlloc.length) {
        return [...new Set(fromAlloc)];
    }

    return [];
});

function warehouseName(kode: string): string {
    if (kode === 'default') {
        return 'Gudang';
    }

    return (props.warehouses ?? []).find((w) => w.kode === kode)?.nama ?? kode;
}

// Prefill dari query (retur via Barang Masuk → Aksi → Retur)
if (props.prefilledPurchase) {
    purchases.value = [
        {
            id: props.prefilledPurchase.id,
            ref_code: props.prefilledPurchase.ref_code,
            purchase_date: props.prefilledPurchase.purchase_date,
            warehouse_nama: props.prefilledPurchase.warehouse_nama,
            warehouse_kodes:
                props.prefilledPurchase.warehouse_kodes ??
                (props.prefilledPurchase.warehouse_kode
                    ? [props.prefilledPurchase.warehouse_kode]
                    : []),
        },
    ];
    const map: Record<string, PurchaseItem> = {};

    for (const it of props.prefilledPurchase.items) {
        const key = `${it.book_id}:${it.book_edition_id ?? 'null'}`;
        map[key] = {
            book_id: it.book_id,
            book_edition_id: it.book_edition_id,
            judul: it.judul,
            cetakan_ke: it.cetakan_ke ?? null,
            qty: it.qty,
            remaining: it.qty,
            price: it.price,
        };

        if (!map[it.book_id]) {
            map[it.book_id] = map[key];
        }
    }

    purchaseItems.value = map;

    if (!form.items.length) {
        for (const it of props.prefilledPurchase.items) {
            const key = `${it.book_id}:${it.book_edition_id ?? 'null'}`;
            const pi = map[key];
            // Untuk retur, alokasi per gudang dari faktur — jika faktur multi, bagi rata sisa
            const allocations: Record<string, number> = {};

            if (purchaseWarehouseKodes.value.length) {
                for (const k of purchaseWarehouseKodes.value) {
                    allocations[k] = 1;
                }

                if (
                    (pi?.remaining ?? it.qty) === 1 &&
                    purchaseWarehouseKodes.value.length > 1
                ) {
                    for (
                        let i = 1;
                        i < purchaseWarehouseKodes.value.length;
                        i++
                    ) {
                        allocations[purchaseWarehouseKodes.value[i]] = 0;
                    }
                }
            } else {
                allocations['default'] = pi?.remaining ?? it.qty;
            }

            form.items.push({
                book_id: it.book_id,
                book_edition_id: it.book_edition_id,
                judul: it.judul,
                cetakan_ke: it.cetakan_ke ?? null,
                price: it.price,
                maxQty: pi?.remaining ?? it.qty,
                allocations,
            });
        }
    }
}

watch(
    () => form.supplier_id,
    async (supplierId) => {
        if (
            props.prefilledPurchaseId &&
            supplierId === props.prefilledSupplierId
        ) {
            return;
        }

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

watch(
    () => form.purchase_id,
    async (purchaseId) => {
        if (
            props.prefilledPurchase &&
            purchaseId === props.prefilledPurchase.id
        ) {
            return;
        }

        purchaseItems.value = {};

        if (!purchaseId || purchaseId === 'none') {
            return;
        }

        const response = await fetch(
            purchaseDetail({ supplierPurchase: purchaseId }).url,
        );
        const items = (await response.json()) as PurchaseItem[];
        const map: Record<string, PurchaseItem> = {};

        for (const item of items) {
            const key = `${item.book_id}:${item.book_edition_id ?? 'null'}`;
            map[key] = item;

            if (!map[item.book_id]) {
                map[item.book_id] = item;
            }
        }

        purchaseItems.value = map;

        if (!form.items.length && items.length) {
            for (const it of items) {
                if (it.remaining <= 0) {
                    continue;
                }

                const kodes = purchaseWarehouseKodes.value.length
                    ? purchaseWarehouseKodes.value
                    : [];
                const allocations: Record<string, number> = {};

                if (kodes.length) {
                    for (const k of kodes) {
                        allocations[k] = 1;
                    }

                    if (it.remaining === 1 && kodes.length > 1) {
                        for (let i = 1; i < kodes.length; i++) {
                            allocations[kodes[i]] = 0;
                        }
                    }
                } else {
                    allocations['default'] = it.remaining;
                }

                form.items.push({
                    book_id: it.book_id,
                    book_edition_id: it.book_edition_id,
                    judul: it.judul,
                    cetakan_ke: it.cetakan_ke ?? null,
                    price: it.price,
                    maxQty: it.remaining,
                    allocations,
                });
            }
        }
    },
);

const bookPickerUrl = computed(() => {
    if (form.purchase_id && form.purchase_id !== 'none') {
        return bookOptions({ query: { purchase_id: form.purchase_id } }).url;
    }

    return bookOptions().url;
});

function itemTotalQty(item: ReturnItem): number {
    return Object.values(item.allocations).reduce(
        (sum, v) => sum + (Number(v) || 0),
        0,
    );
}
function itemSubtotal(item: ReturnItem): number {
    return itemTotalQty(item) * item.price;
}
const total = () =>
    form.items.reduce((sum, item) => sum + itemSubtotal(item), 0);

function onBookSelect(book: BookOption) {
    const extra = book as unknown as { book_edition_id?: string | null };
    addItem({
        ...(book as ReturnBook),
        book_edition_id: extra.book_edition_id ?? null,
    } as ReturnBook & { book_edition_id?: string | null });
}

function addItem(book: ReturnBook & { book_edition_id?: string | null }) {
    if (form.purchase_id && form.purchase_id !== 'none') {
        const found = Object.values(purchaseItems.value).some(
            (pi) => pi.book_id === book.id,
        );

        if (!found) {
            toast.error('Buku tidak ada di faktur terpilih.');

            return;
        }
    }

    const purchaseItem =
        purchaseItems.value[book.id] ??
        Object.values(purchaseItems.value).find((pi) => pi.book_id === book.id);
    const maxQty = purchaseItem
        ? (purchaseItem.remaining ?? purchaseItem.qty)
        : 9999;

    if (purchaseItem && maxQty <= 0) {
        toast.error('Sisa qty faktur sudah habis, tidak bisa diretur lagi.');

        return;
    }

    // Cek duplikat per book+edition
    const key = `${book.id}:${(book as unknown as { book_edition_id?: string | null }).book_edition_id ?? purchaseItem?.book_edition_id ?? 'null'}`;

    if (
        form.items.some(
            (it) => `${it.book_id}:${it.book_edition_id ?? 'null'}` === key,
        )
    ) {
        toast.info('Buku sudah ada di daftar.');

        return;
    }

    const kodes = purchaseWarehouseKodes.value.length
        ? purchaseWarehouseKodes.value
        : [];
    const allocations: Record<string, number> = {};

    if (kodes.length) {
        for (const k of kodes) {
            allocations[k] = 1;
        }

        if (maxQty === 1 && kodes.length > 1) {
            for (let i = 1; i < kodes.length; i++) {
                allocations[kodes[i]] = 0;
            }
        }
    } else {
        allocations['default'] = 1;
    }

    form.items.push({
        book_id: book.id,
        book_edition_id:
            (book as unknown as { book_edition_id?: string | null })
                .book_edition_id ??
            purchaseItem?.book_edition_id ??
            null,
        judul: book.judul,
        cetakan_ke: purchaseItem?.cetakan_ke ?? null,
        price: purchaseItem?.price ?? 0,
        maxQty,
        allocations,
    });
}

const pendingDeleteIndex = ref<number | null>(null);
const pendingDeleteItem = computed<ReturnItem | null>(() =>
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

function submit() {
    if (!form.supplier_id) {
        toast.error('Pilih supplier terlebih dahulu.');

        return;
    }

    if (!form.items.length) {
        toast.error('Minimal satu item barang.');

        return;
    }

    if (!form.reason.trim()) {
        toast.error('Alasan retur wajib diisi.');

        return;
    }

    for (const item of form.items) {
        if (itemTotalQty(item) > item.maxQty) {
            toast.error(
                `Qty retur untuk "${item.judul}" melebihi sisa faktur (max ${item.maxQty}).`,
            );

            return;
        }

        if (itemTotalQty(item) < 1) {
            toast.error(`Qty untuk "${item.judul}" minimal 1.`);

            return;
        }
    }

    processing.value = true;
    router.post(
        SupplierReturnController.store.url(),
        {
            supplier_id: form.supplier_id,
            return_date: form.return_date,
            purchase_id:
                form.purchase_id === 'none'
                    ? undefined
                    : form.purchase_id || undefined,
            reason: form.reason,
            source: form.source,
            shipping_cost: form.shipping_cost || 0,
            notes: form.notes,
            items: form.items.map(
                ({ book_id, book_edition_id, allocations, price }) => ({
                    book_id,
                    book_edition_id: book_edition_id || null,
                    qty: itemTotalQty({
                        book_id,
                        book_edition_id,
                        judul: '',
                        price,
                        maxQty: 0,
                        allocations,
                    } as ReturnItem),
                    price,
                    reason: form.reason,
                    source: form.source,
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

const totalLabelColspan = computed(
    () => 3 + (purchaseWarehouseKodes.value.length || 1),
);
</script>

<template>
    <Head title="Catat Retur Supplier" />

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
                        Catat Retur Supplier
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Mencatat pengembalian barang ke supplier — retur dibuat
                        dari faktur Barang Masuk
                    </p>
                </div>
            </div>
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
                    <Select
                        v-model="form.supplier_id"
                        :disabled="!!prefilledPurchaseId"
                    >
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
                        Faktur Pembelian *
                        <FieldHint
                            text="Retur harus terhubung ke faktur. Pilih faktur untuk membatasi item & qty."
                        />
                    </Label>
                    <Select
                        v-model="form.purchase_id"
                        :disabled="!form.supplier_id || !!prefilledPurchaseId"
                    >
                        <SelectTrigger
                            ><SelectValue placeholder="Pilih faktur"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="purchase in purchases"
                                :key="purchase.id"
                                :value="String(purchase.id)"
                                >{{ purchase.ref_code }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:col-span-3">
                    <Label>Gudang Asal Retur</Label>
                    <div
                        class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border border-input bg-transparent px-2 py-1.5"
                    >
                        <Badge
                            v-for="kode in purchaseWarehouseKodes"
                            :key="kode"
                            variant="secondary"
                            class="gap-1"
                        >
                            {{ warehouseName(kode) }}
                        </Badge>
                        <span
                            v-if="!purchaseWarehouseKodes.length"
                            class="text-sm text-muted-foreground"
                            >— Pilih faktur untuk melihat gudang</span
                        >
                        <span
                            v-else
                            class="ml-auto text-xs text-muted-foreground"
                            >otomatis dari faktur</span
                        >
                    </div>
                    <p
                        v-if="!purchaseWarehouseKodes.length"
                        class="text-xs text-muted-foreground"
                    >
                        Pilih faktur terlebih dahulu. Kolom Qty per gudang akan
                        muncul sesuai faktur.
                    </p>
                    <p v-else class="text-xs text-muted-foreground">
                        {{ purchaseWarehouseKodes.length }} gudang dari faktur
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label
                        for="shipping_cost"
                        class="inline-flex w-fit items-center gap-1"
                    >
                        Ongkos Kirim Retur (Rp)
                        <FieldHint
                            text="Opsional, 0 jika ditanggung supplier."
                        />
                    </Label>
                    <CurrencyInput
                        id="shipping_cost"
                        v-model="form.shipping_cost"
                        placeholder="0"
                    />
                </div>
                <div class="grid gap-2 md:col-span-3">
                    <Label for="reason">Alasan Retur *</Label>
                    <Select v-model="form.reason" required>
                        <SelectTrigger id="reason"
                            ><SelectValue placeholder="Pilih alasan retur"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="reason in returnReasons"
                                :key="reason.id"
                                :value="reason.name"
                                >{{ reason.name }}
                                <span class="text-xs text-muted-foreground"
                                    >({{ reason.category }})</span
                                ></SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Template dari Pengaturan → Alasan Retur. Ubah di
                        <Link
                            href="/admin/settings/alasan-retur"
                            class="underline"
                            >Pengaturan</Link
                        >.
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
            <CardContent class="flex min-h-[420px] flex-col gap-4">
                <BookPicker
                    :key="form.purchase_id"
                    :base-url="bookPickerUrl"
                    class="max-w-sm"
                    placeholder="Cari judul buku / SKU..."
                    @select="onBookSelect"
                />
                <p
                    v-if="
                        form.purchase_id &&
                        form.purchase_id !== 'none' &&
                        !Object.keys(purchaseItems).length
                    "
                    class="text-sm text-muted-foreground"
                >
                    Memuat item faktur...
                </p>
                <p
                    v-else-if="form.purchase_id && form.purchase_id !== 'none'"
                    class="text-xs text-muted-foreground"
                >
                    Hanya buku yang ada di faktur terpilih yang bisa diretur.
                    Max qty sesuai sisa faktur.
                    <template v-if="purchaseWarehouseKodes.length > 1">
                        Qty per gudang.</template
                    >
                </p>
                <p
                    v-else-if="!form.purchase_id"
                    class="text-sm text-amber-600 dark:text-amber-400"
                >
                    Pilih faktur terlebih dahulu — kolom Qty per gudang akan
                    muncul sesuai pilihan.
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
                                <template v-if="purchaseWarehouseKodes.length">
                                    <TableHead
                                        v-for="kode in purchaseWarehouseKodes"
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
                                <TableHead
                                    v-else
                                    class="w-20 bg-muted/50 px-2 text-center whitespace-nowrap"
                                    >Qty</TableHead
                                >
                                <TableHead
                                    class="w-28 bg-muted/50 whitespace-nowrap"
                                    >Harga</TableHead
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
                                :key="`${item.book_id}:${item.book_edition_id ?? 'null'}`"
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
                                    <span v-if="item.cetakan_ke" class="text-xs"
                                        >Cet. {{ item.cetakan_ke }}</span
                                    >
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <template v-if="purchaseWarehouseKodes.length">
                                    <TableCell
                                        v-for="kode in purchaseWarehouseKodes"
                                        :key="kode"
                                        class="px-2 text-center"
                                    >
                                        <Input
                                            :model-value="
                                                item.allocations[kode] ?? 0
                                            "
                                            type="number"
                                            min="0"
                                            :max="item.maxQty"
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
                                <TableCell v-else class="px-2 text-center">
                                    <Input
                                        :model-value="itemTotalQty(item)"
                                        type="number"
                                        min="1"
                                        :max="item.maxQty"
                                        class="h-8 w-14 [appearance:textfield] px-1 text-center tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                        @update:model-value="
                                            (val) => {
                                                const v = Number(val) || 0;
                                                const k =
                                                    Object.keys(
                                                        item.allocations,
                                                    )[0] ?? 'default';
                                                item.allocations[k] = v;
                                            }
                                        "
                                    />
                                </TableCell>
                                <TableCell>
                                    <CurrencyInput
                                        v-model="item.price"
                                        input-class="h-8 w-24"
                                        placeholder="Harga"
                                    />
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
                                    >Total retur:</TableCell
                                >
                                <TableCell class="text-right"
                                    ><Money :value="total()"
                                /></TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <EmptyState
                    v-else
                    title="Belum ada item barang"
                    description="Cari judul buku atau SKU di atas untuk menambahkannya ke retur."
                />

                <div
                    class="sticky bottom-0 z-10 -mx-4 mt-4 flex flex-wrap items-center justify-start gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6"
                >
                    <Button variant="outline" type="button" as-child>
                        <Link :href="indexRoute().url">Batal</Link>
                    </Button>
                    <Button :disabled="processing" @click="submit">
                        <Undo2 class="size-4" />
                        {{ processing ? 'Menyimpan...' : 'Simpan Retur' }}
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
                ? `Buku '${pendingDeleteItem.judul}' akan dihapus dari daftar retur.`
                : 'Item akan dihapus dari daftar.'
        "
        confirm-label="Hapus"
        confirm-variant="destructive"
        @update:open="cancelRemove"
        @confirm="executeRemove"
    />
</template>
