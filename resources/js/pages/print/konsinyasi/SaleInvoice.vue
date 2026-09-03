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

type SaleItem = {
    qty: number;
    price: number;
    book: { judul: string; kode_sku: string | null } | null;
};

type Sale = {
    id: string;
    sale_date: string;
    notes: string | null;
    customer: {
        name: string;
        whatsapp_number: string | null;
        alamat: string | null;
        provinsi: string | null;
        kabupaten_kota: string | null;
        kecamatan: string | null;
        kelurahan: string | null;
        kode_pos: string | null;
    } | null;
    items: SaleItem[];
    receivable: { amount: number; paid_amount: number } | null;
};

const props = defineProps<{
    sale: Sale;
    store: InvoiceStore;
}>();

useInvoicePrint();

function printInvoice() {
    window.print();
}
function closeWindow() {
    window.close();
}

const idr = (v: number) => Math.round(v).toLocaleString('id-ID');

const isLunas = computed(() =>
    props.sale.receivable
        ? props.sale.receivable.paid_amount >= props.sale.receivable.amount
        : false,
);

const alamatMitra = computed(() =>
    [
        props.sale.customer?.alamat,
        props.sale.customer?.kelurahan,
        props.sale.customer?.kecamatan,
        props.sale.customer?.kabupaten_kota,
        props.sale.customer?.provinsi,
        props.sale.customer?.kode_pos,
    ]
        .filter(Boolean)
        .join(', '),
);

const subtotal = computed(() =>
    props.sale.items.reduce((s, i) => s + i.qty * i.price, 0),
);
const sisa = computed(() =>
    props.sale.receivable
        ? props.sale.receivable.amount - props.sale.receivable.paid_amount
        : 0,
);

const columns: InvoiceColumn[] = [
    { key: 'no', label: 'No', align: 'center' },
    { key: 'buku', label: 'Buku' },
    { key: 'qty', label: 'Qty', align: 'center' },
    { key: 'harga', label: 'Harga', align: 'right' },
    { key: 'jumlah', label: 'Jumlah', align: 'right' },
];

const rows = computed(() =>
    props.sale.items.map((item, i) => ({
        no: String(i + 1),
        buku: item.book?.judul ?? '—',
        qty: String(item.qty),
        harga: idr(item.price),
        jumlah: idr(item.qty * item.price),
    })),
);
</script>

<template>
    <div>
        <div
            class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4 print:hidden"
        >
            <p class="text-sm text-muted-foreground">
                {{ isLunas ? 'Kwitansi lunas' : 'Tagihan piutang konsinyasi' }}
            </p>
            <div class="flex gap-2">
                <Button size="sm" @click="printInvoice"
                    ><Printer class="size-4" /> Cetak</Button
                >
                <Button size="sm" variant="outline" @click="closeWindow"
                    ><X class="size-4" /> Tutup</Button
                >
            </div>
        </div>

        <InvoiceSheet>
            <InvoiceHeader
                :store="store"
                :title="isLunas ? 'Kwitansi' : 'Invoice Tagihan'"
                :no="sale.id.slice(0, 8).toUpperCase()"
                :tanggal="formatDateID(sale.sale_date)"
                :subtitle="isLunas ? 'Lunas' : 'Belum Lunas'"
            />

            <div class="mt-4 grid grid-cols-2 gap-6">
                <InvoiceParty
                    title="Mitra"
                    :lines="[
                        { label: 'Nama', value: sale.customer?.name ?? '—' },
                        { label: 'Alamat', value: alamatMitra || '—' },
                        {
                            label: 'No. HP',
                            value: sale.customer?.whatsapp_number ?? '—',
                        },
                    ]"
                />
                <InvoiceParty
                    title="Tagihan"
                    :lines="[
                        {
                            label: 'Tanggal Laku',
                            value: formatDateID(sale.sale_date),
                        },
                        { label: 'Catatan', value: sale.notes ?? '—' },
                        {
                            label: 'Status',
                            value: isLunas
                                ? 'Lunas'
                                : `Belum Lunas — sisa Rp ${idr(sisa)}`,
                        },
                    ]"
                />
            </div>

            <div class="mt-5">
                <InvoiceItemsTable :columns="columns" :rows="rows" />
            </div>

            <div class="mt-4">
                <InvoiceSummary
                    :lines="[{ label: 'Subtotal', value: idr(subtotal) }]"
                    total-label="TOTAL TAGIHAN"
                    :total="idr(subtotal)"
                />
                <p v-if="!isLunas" class="mt-2 text-xs text-muted-foreground">
                    Sisa piutang Rp {{ idr(sisa) }} — bayar via menu Piutang
                    atau tombol Bayar di Laporan Laku
                </p>
                <p v-else class="mt-2 text-xs text-green-600">
                    Sudah lunas — terima kasih
                </p>
            </div>

            <InvoiceFooter thanks="Jazakumullah Khoiron" />
        </InvoiceSheet>
    </div>
</template>
