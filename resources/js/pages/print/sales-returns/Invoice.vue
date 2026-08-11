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

type ReturnProps = {
    id: string;
    return_date: string;
    total_refund: number;
    notes: string | null;
    order: { no_order: string; nama_pembeli: string } | null;
    items: Array<{
        qty: number;
        price_refund: number;
        condition: string;
        reason: string | null;
        order_item: {
            judul_snapshot: string;
            edition_snapshot: string | null;
        } | null;
    }>;
};

const props = defineProps<{
    retur: ReturnProps;
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

const conditionLabel: Record<string, string> = {
    baik: 'Baik',
    rusak: 'Rusak',
};

const columns: InvoiceColumn[] = [
    { key: 'no', label: 'No', align: 'center' },
    { key: 'buku', label: 'Buku' },
    { key: 'cetakan', label: 'Cetakan' },
    { key: 'qty', label: 'Qty', align: 'center' },
    { key: 'kondisi', label: 'Kondisi', align: 'center' },
    { key: 'harga', label: 'Harga Refund', align: 'right' },
    { key: 'jumlah', label: 'Jumlah', align: 'right' },
];

const rows = computed(() =>
    props.retur.items.map((item, i) => ({
        no: String(i + 1),
        buku: item.order_item?.judul_snapshot ?? '—',
        cetakan: item.order_item?.edition_snapshot ?? '—',
        qty: String(item.qty),
        kondisi: conditionLabel[item.condition] ?? item.condition,
        harga: idr(item.price_refund),
        jumlah: idr(item.price_refund * item.qty),
    })),
);

const alasan = computed(() =>
    props.retur.items
        .map((item) => item.reason)
        .filter(Boolean)
        .join('; '),
);
</script>

<template>
    <div>
        <!-- Toolbar — tidak ikut tercetak -->
        <div
            class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4 print:hidden"
        >
            <p class="text-sm text-muted-foreground">
                Nota retur penjualan — barang dikembalikan pembeli.
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
                title="Nota Retur"
                :no="`RET-${retur.order?.no_order ?? retur.id}`"
                :tanggal="formatDateID(retur.return_date)"
                subtitle="Barang dikembalikan pembeli"
            />

            <div class="mt-4 grid grid-cols-2 gap-6">
                <InvoiceParty
                    title="Dari Pembeli"
                    :lines="[
                        {
                            label: 'Nama',
                            value: retur.order?.nama_pembeli ?? '—',
                        },
                        {
                            label: 'No. Order',
                            value: retur.order?.no_order ?? '—',
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
                    total-label="TOTAL REFUND"
                    :total="idr(retur.total_refund)"
                />
            </div>

            <InvoiceFooter
                :notes="retur.notes || alasan || null"
                :signatures="[{ label: 'Penerima' }, { label: 'Petugas' }]"
            />
        </InvoiceSheet>
    </div>
</template>
