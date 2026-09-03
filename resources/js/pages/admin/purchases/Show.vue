<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Barang Masuk', href: '/admin/purchases' },
            { title: 'Detail' },
        ],
    },
});

import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer, Undo2 } from '@lucide/vue';
import { computed } from 'vue';
import SupplierPurchaseController from '@/actions/App/Http/Controllers/Admin/SupplierPurchaseController';
import Money from '@/components/Money.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as indexRoute } from '@/routes/admin/purchases';
import { create as createRetur } from '@/routes/admin/supplier-returns';

type WarehouseOption = { kode: string; nama: string };

type Purchase = {
    id: string;
    ref_code: string;
    purchase_date: string;
    total: number;
    shipping_cost: number | null;
    notes: string | null;
    warehouse_kode: string | null;
    warehouse_kodes: string[] | null;
    warehouse: { kode: string; nama: string } | null;
    warehouses?: WarehouseOption[];
    supplier: {
        id: string;
        nama: string;
        telepon: string | null;
        alamat: string | null;
    } | null;
    items: Array<{
        id: string;
        qty: number;
        price: number;
        subtotal: number;
        stock_before: number | null;
        hpp_old: number | null;
        hpp_new: number | null;
        landed_cost: number | null;
        book: { id: string; judul: string; kode_sku: string | null } | null;
        edition: { id: string; cetakan_ke: number; harga_beli: number } | null;
        allocations: Array<{ warehouse_kode: string; qty: number }>;
    }>;
    payments: Array<{ id: string; amount: number; payment_date: string }>;
    returns: Array<{ id: string; total: number; return_date: string }>;
};

const props = defineProps<{
    purchase: Purchase;
    warehouses?: WarehouseOption[];
}>();

const warehouseKodes = computed<string[]>(() => {
    if (
        props.purchase.warehouse_kodes &&
        props.purchase.warehouse_kodes.length
    ) {
        return props.purchase.warehouse_kodes;
    }

    if (props.purchase.warehouse_kode) {
        return [props.purchase.warehouse_kode];
    }

    return [];
});

function warehouseName(kode: string): string {
    const fromPurchase = props.purchase.warehouses?.find(
        (w) => w.kode === kode,
    )?.nama;

    if (fromPurchase) {
        return fromPurchase;
    }

    const fromProp = props.warehouses?.find((w) => w.kode === kode)?.nama;

    if (fromProp) {
        return fromProp;
    }

    if (props.purchase.warehouse?.kode === kode) {
        return props.purchase.warehouse.nama;
    }

    return kode;
}

const paidTotal = computed(() =>
    props.purchase.payments.reduce((sum, p) => sum + (p.amount ?? 0), 0),
);
const grandTotal = computed(
    () => props.purchase.total + (props.purchase.shipping_cost ?? 0),
);
const totalLabelColspan = computed(
    () => 5 + (warehouseKodes.value.length || 1),
);

function allocationQty(item: Purchase['items'][number], kode: string): number {
    return item.allocations.find((a) => a.warehouse_kode === kode)?.qty ?? 0;
}
</script>

<template>
    <Head :title="`Detail ${purchase.ref_code}`" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    as-child
                >
                    <Link href="/admin/purchases"
                        ><ArrowLeft class="size-4"
                    /></Link>
                </Button>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        Detail Barang Masuk — {{ purchase.ref_code }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ purchase.purchase_date }} ·
                        {{ purchase.supplier?.nama ?? '—' }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="indexRoute().url">Kembali</Link>
                </Button>
                <Button variant="outline" as-child>
                    <a
                        :href="
                            SupplierPurchaseController.invoice(purchase.id).url
                        "
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer class="size-4" />
                        Print Nota
                    </a>
                </Button>
                <Button as-child>
                    <Link
                        :href="
                            createRetur({
                                query: {
                                    purchase_id: purchase.id,
                                    supplier_id: purchase.supplier?.id,
                                },
                            }).url
                        "
                    >
                        <Undo2 class="size-4" />
                        Retur
                    </Link>
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base font-medium"
                    >Informasi Pembelian</CardTitle
                >
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label>Supplier</Label>
                    <div
                        class="flex min-h-9 flex-col justify-center rounded-md border border-input bg-muted px-3 py-2"
                    >
                        <p class="text-sm leading-none font-medium">
                            {{ purchase.supplier?.nama ?? '—' }}
                        </p>
                        <p
                            v-if="purchase.supplier?.telepon"
                            class="text-xs text-muted-foreground"
                        >
                            {{ purchase.supplier.telepon }}
                        </p>
                        <p
                            v-if="purchase.supplier?.alamat"
                            class="line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ purchase.supplier.alamat }}
                        </p>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>Tanggal</Label>
                    <div
                        class="flex h-9 items-center rounded-md border border-input bg-muted px-3 py-2 text-sm"
                    >
                        {{ purchase.purchase_date }}
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>Ref Code</Label>
                    <div
                        class="flex h-9 items-center rounded-md border border-input bg-muted px-3 py-2 font-mono text-sm"
                    >
                        {{ purchase.ref_code }}
                    </div>
                </div>
                <div class="grid gap-2 md:col-span-3">
                    <Label>Gudang Tujuan</Label>
                    <div
                        class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border border-input bg-transparent px-2 py-1.5"
                    >
                        <template v-if="warehouseKodes.length">
                            <Badge
                                v-for="kode in warehouseKodes"
                                :key="kode"
                                variant="secondary"
                                class="gap-1"
                            >
                                {{ warehouseName(kode) }}
                            </Badge>
                        </template>
                        <span v-else class="text-sm text-muted-foreground"
                            >—</span
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="warehouseKodes.length"
                            >{{ warehouseKodes.length }} gudang tujuan</template
                        >
                        <template v-else>Tidak ada gudang</template>
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label>Ongkos Kirim</Label>
                    <div
                        class="flex h-9 items-center rounded-md border border-input bg-muted px-3 py-2 text-sm tabular-nums"
                    >
                        <Money :value="purchase.shipping_cost ?? 0" />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Mempengaruhi HPP (landed cost)
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label>Sudah Dibayar</Label>
                    <div
                        class="flex h-9 items-center rounded-md border border-input bg-muted px-3 py-2 text-sm tabular-nums"
                    >
                        <Money :value="paidTotal" />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="paidTotal > 0">Lunas sebagian</template>
                        <template v-else
                            >Belum ada pembayaran (hutang)</template
                        >
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label>Grand Total</Label>
                    <div
                        class="flex h-9 items-center rounded-md border border-input bg-muted px-3 py-2 text-sm font-semibold tabular-nums"
                    >
                        <Money :value="grandTotal" />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Total barang + ongkir
                    </p>
                </div>
                <div class="grid gap-2 md:col-span-3">
                    <Label>Catatan</Label>
                    <div
                        class="min-h-9 rounded-md border border-input bg-muted px-3 py-2 text-sm"
                    >
                        {{ purchase.notes || '—' }}
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base font-medium">Item Barang</CardTitle>
            </CardHeader>
            <CardContent class="flex min-h-[420px] flex-col gap-4">
                <div class="overflow-x-auto rounded-md border bg-card">
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
                                <template v-if="warehouseKodes.length > 1">
                                    <TableHead
                                        v-for="kode in warehouseKodes"
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
                                v-for="item in purchase.items"
                                :key="item.id"
                                class="group"
                            >
                                <TableCell
                                    class="sticky left-0 z-10 bg-card font-medium whitespace-nowrap transition-colors group-hover:bg-muted/50"
                                >
                                    <span class="block max-w-64 truncate">{{
                                        item.book?.judul ?? '—'
                                    }}</span>
                                    <span
                                        class="block max-w-64 truncate text-xs text-muted-foreground"
                                        >{{ item.book?.kode_sku ?? '—' }}</span
                                    >
                                </TableCell>
                                <TableCell class="px-2">
                                    <span v-if="item.edition" class="text-xs"
                                        >Cet.
                                        {{ item.edition.cetakan_ke }}</span
                                    >
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <template v-if="warehouseKodes.length > 1">
                                    <TableCell
                                        v-for="kode in warehouseKodes"
                                        :key="kode"
                                        class="px-2 text-center text-sm tabular-nums"
                                    >
                                        {{ allocationQty(item, kode) }}
                                    </TableCell>
                                </template>
                                <TableCell
                                    v-else
                                    class="px-2 text-center text-sm tabular-nums"
                                    >{{ item.qty }}</TableCell
                                >
                                <TableCell class="tabular-nums"
                                    ><Money :value="item.price"
                                /></TableCell>
                                <TableCell
                                    class="px-2 text-right text-xs tabular-nums"
                                >
                                    <template v-if="item.hpp_old != null"
                                        >Rp
                                        {{
                                            item.hpp_old.toLocaleString('id-ID')
                                        }}</template
                                    >
                                    <template v-else-if="item.edition"
                                        >Rp
                                        {{
                                            item.edition.harga_beli.toLocaleString(
                                                'id-ID',
                                            )
                                        }}</template
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell
                                    class="px-2 text-right text-xs font-medium tabular-nums"
                                >
                                    <template v-if="item.hpp_new != null"
                                        >Rp
                                        {{
                                            item.hpp_new.toLocaleString('id-ID')
                                        }}</template
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell
                                    class="sticky right-12 z-10 bg-card text-right whitespace-nowrap tabular-nums transition-colors group-hover:bg-muted/50"
                                >
                                    <Money :value="item.subtotal" />
                                </TableCell>
                                <TableCell
                                    class="sticky right-0 z-10 bg-card text-right transition-colors group-hover:bg-muted/50"
                                ></TableCell>
                            </TableRow>
                            <TableRow class="bg-muted/50 font-semibold">
                                <TableCell
                                    :colspan="totalLabelColspan"
                                    class="text-right text-muted-foreground"
                                    >Total Barang:</TableCell
                                >
                                <TableCell class="text-right"
                                    ><Money :value="purchase.total"
                                /></TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                            <TableRow
                                v-if="purchase.shipping_cost"
                                class="bg-muted/30"
                            >
                                <TableCell
                                    :colspan="totalLabelColspan"
                                    class="text-right text-muted-foreground"
                                    >Ongkir:</TableCell
                                >
                                <TableCell class="text-right"
                                    ><Money :value="purchase.shipping_cost"
                                /></TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                            <TableRow
                                v-if="purchase.shipping_cost"
                                class="font-semibold"
                            >
                                <TableCell
                                    :colspan="totalLabelColspan"
                                    class="text-right"
                                    >Grand Total:</TableCell
                                >
                                <TableCell class="text-right"
                                    ><Money :value="grandTotal"
                                /></TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card v-if="purchase.payments.length || purchase.returns.length">
            <CardHeader>
                <CardTitle class="text-base font-medium"
                    >Riwayat Pembayaran & Retur</CardTitle
                >
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium">Pembayaran</p>
                    <p
                        v-if="!purchase.payments.length"
                        class="text-sm text-muted-foreground"
                    >
                        Belum ada pembayaran.
                    </p>
                    <ul v-else class="mt-2 space-y-1 text-sm">
                        <li
                            v-for="p in purchase.payments"
                            :key="p.id"
                            class="flex justify-between"
                        >
                            <span>{{ p.payment_date }}</span
                            ><Money :value="p.amount" />
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-medium">Retur</p>
                    <p
                        v-if="!purchase.returns.length"
                        class="text-sm text-muted-foreground"
                    >
                        Belum ada retur.
                    </p>
                    <ul v-else class="mt-2 space-y-1 text-sm">
                        <li
                            v-for="r in purchase.returns"
                            :key="r.id"
                            class="flex justify-between"
                        >
                            <span>{{ r.return_date }}</span
                            ><Money :value="r.total" />
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
