<script setup lang="ts">
import { Printer, X } from '@lucide/vue';
import { computed } from 'vue';
import InvoiceFooter from '@/components/invoice/InvoiceFooter.vue';
import InvoiceHeader from '@/components/invoice/InvoiceHeader.vue';
import type { InvoiceStore } from '@/components/invoice/InvoiceHeader.vue';
import InvoiceItemsTable from '@/components/invoice/InvoiceItemsTable.vue';
import type { InvoiceColumn } from '@/components/invoice/InvoiceItemsTable.vue';
import InvoiceParty from '@/components/invoice/InvoiceParty.vue';
import InvoiceSheet from '@/components/invoice/InvoiceSheet.vue';
import InvoiceSummary from '@/components/invoice/InvoiceSummary.vue';
import { Button } from '@/components/ui/button';
import { useInvoicePrint } from '@/composables/useInvoicePrint';
import { formatDateID } from '@/lib/date';

type PurchaseProps = {
    ref_code: string;
    purchase_date: string;
    total: number;
    notes: string | null;
    warehouse_kode: string | null;
    warehouse_kodes?: string[] | null;
    supplier: {
        nama: string;
        telepon: string | null;
        alamat: string | null;
    } | null;
    items: Array<{
        qty: number;
        price: number;
        subtotal: number;
        allocations?: Array<{ warehouse_kode: string; qty: number }>;
        book: { judul: string; kode_sku: string | null } | null;
    }>;
};

const props = defineProps<{
    purchase: PurchaseProps;
    store: InvoiceStore;
}>();

useInvoicePrint();

function printInvoice() {
    window.print();
}

function closeWindow() {
    window.close();
}

const idr = (value: number): string =>
    Math.round(value).toLocaleString('id-ID');

const columns: InvoiceColumn[] = [
    { key: 'no', label: 'No', align: 'center' },
    { key: 'buku', label: 'Buku' },
    { key: 'sku', label: 'SKU' },
    { key: 'qty', label: 'Qty', align: 'center' },
    { key: 'harga', label: 'Harga Beli', align: 'right' },
    { key: 'jumlah', label: 'Jumlah', align: 'right' },
];

const warehouseLabel = computed(() => {
    const kodes = props.purchase.warehouse_kodes;

    if (Array.isArray(kodes) && kodes.length > 0) {
        return kodes.join(', ');
    }

    return props.purchase.warehouse_kode ?? '—';
});

const rows = computed(() =>
    props.purchase.items.map((item, i) => {
        const qtyText =
            item.allocations && item.allocations.length > 1
                ? `${item.qty} (${item.allocations.map((a) => `${a.warehouse_kode}: ${a.qty}`).join(', ')})`
                : String(item.qty);

        return {
            no: String(i + 1),
            buku: item.book?.judul ?? '—',
            sku: item.book?.kode_sku ?? '—',
            qty: qtyText,
            harga: idr(item.price),
            jumlah: idr(item.subtotal),
        };
    }),
);

const supplierLines = computed(() => [
    { label: 'Nama', value: props.purchase.supplier?.nama ?? '—' },
    { label: 'Alamat', value: props.purchase.supplier?.alamat ?? '' },
    { label: 'Telepon', value: props.purchase.supplier?.telepon ?? '' },
]);
</script>

<template>
    <div>
        <!-- Toolbar — tidak ikut tercetak -->
        <div
            class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4 print:hidden"
        >
            <p class="text-sm text-muted-foreground">
                Nota pembelian dari supplier — arsip internal toko.
            </p>
            <div class="flex gap-2">
                <Button size="sm" @click="printInvoice">
                    <Printer class="size-4" />
                    Cetak
                </Button>
                <Button size="sm" variant="outline" @click="closeWindow">
                    <X class="size-4" />
                    Tutup
                </Button>
            </div>
        </div>

        <InvoiceSheet>
            <InvoiceHeader
                :store="store"
                title="Nota Pembelian"
                :no="purchase.ref_code"
                :tanggal="formatDateID(purchase.purchase_date)"
                subtitle="Barang masuk dari supplier"
            />

            <div class="mt-4 grid grid-cols-2 gap-6">
                <InvoiceParty title="Supplier" :lines="supplierLines" />
                <InvoiceParty
                    title="Penerimaan"
                    :lines="[
                        {
                            label: 'Gudang',
                            value: warehouseLabel,
                        },
                    ]"
                />
            </div>

            <div class="mt-5">
                <InvoiceItemsTable :columns="columns" :rows="rows" />
            </div>

            <div class="mt-4">
                <InvoiceSummary
                    :lines="[]"
                    total-label="TOTAL"
                    :total="idr(purchase.total)"
                />
            </div>

            <InvoiceFooter
                :notes="purchase.notes"
                thanks="Jazakumullah Khoiron"
            />
        </InvoiceSheet>
    </div>
</template>
