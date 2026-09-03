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
import { Button } from '@/components/ui/button';
import { useInvoicePrint } from '@/composables/useInvoicePrint';
import { formatDateID } from '@/lib/date';

type DeliveryItem = {
    qty: number;
    book: { judul: string; kode_sku: string | null; harga: number } | null;
};

type Delivery = {
    id: string;
    delivery_date: string;
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
    items: DeliveryItem[];
};

const props = defineProps<{
    delivery: Delivery;
    store: InvoiceStore;
}>();

useInvoicePrint();

function printInvoice() {
    window.print();
}
function closeWindow() {
    window.close();
}

const alamatMitra = computed(() =>
    [
        props.delivery.customer?.alamat,
        props.delivery.customer?.kelurahan,
        props.delivery.customer?.kecamatan,
        props.delivery.customer?.kabupaten_kota,
        props.delivery.customer?.provinsi,
        props.delivery.customer?.kode_pos,
    ]
        .filter(Boolean)
        .join(', '),
);

const totalQty = computed(() =>
    props.delivery.items.reduce((s, i) => s + i.qty, 0),
);
const totalHarga = computed(() =>
    props.delivery.items.reduce(
        (s, i) =>
            s + (i as any).harga_titip * i.qty || (i.book?.harga ?? 0) * i.qty,
        0,
    ),
);

const columns: InvoiceColumn[] = [
    { key: 'no', label: 'No', align: 'center' },
    { key: 'buku', label: 'Buku' },
    { key: 'qty', label: 'Qty', align: 'center' },
    { key: 'harga', label: 'Harga', align: 'right' },
    { key: 'jumlah', label: 'Jumlah', align: 'right' },
];

const idr = (v: number) => Math.round(v).toLocaleString('id-ID');

const rows = computed(() =>
    props.delivery.items.map((item: any, i) => ({
        no: String(i + 1),
        buku: item.book?.judul ?? '—',
        qty: String(item.qty),
        harga: idr(item.harga_titip ?? item.book?.harga ?? 0),
        jumlah: idr((item.harga_titip ?? item.book?.harga ?? 0) * item.qty),
    })),
);
</script>

<template>
    <div>
        <div
            class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4 print:hidden"
        >
            <p class="text-sm text-muted-foreground">
                Surat jalan konsinyasi — cetak untuk serah terima
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
                title="Surat Jalan Konsinyasi"
                :no="delivery.id.slice(0, 8).toUpperCase()"
                :tanggal="formatDateID(delivery.delivery_date)"
                subtitle="Titip Jual"
            />

            <div class="mt-4 grid grid-cols-2 gap-6">
                <InvoiceParty
                    title="Mitra Penerima"
                    :lines="[
                        {
                            label: 'Nama',
                            value: delivery.customer?.name ?? '—',
                        },
                        { label: 'Alamat', value: alamatMitra || '—' },
                        {
                            label: 'No. HP',
                            value: delivery.customer?.whatsapp_number ?? '—',
                        },
                    ]"
                />
                <InvoiceParty
                    title="Pengirim"
                    :lines="[
                        { label: 'Gudang', value: 'Malang' },
                        {
                            label: 'Tanggal',
                            value: formatDateID(delivery.delivery_date),
                        },
                        { label: 'Catatan', value: delivery.notes ?? '—' },
                    ]"
                />
            </div>

            <div class="mt-5">
                <InvoiceItemsTable :columns="columns" :rows="rows" />
            </div>

            <div class="mt-4 flex justify-between text-sm">
                <span class="text-muted-foreground">Total</span>
                <span class="font-semibold"
                    >Rp {{ totalHarga.toLocaleString('id-ID') }} •
                    {{ totalQty }} eks — {{ delivery.items.length }} judul</span
                >
            </div>

            <InvoiceFooter thanks="terima kasih, jazakumullah khoiron" />
        </InvoiceSheet>
    </div>
</template>
