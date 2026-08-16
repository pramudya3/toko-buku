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

type OrderItemRow = {
    judul_snapshot: string;
    edition_snapshot: string | null;
    qty: number;
    price_original: number;
    promo_discount_amount: number;
    tier_discount_amount: number;
    price_final: number;
};

type OrderProps = {
    no_order: string;
    nama_pembeli: string;
    no_hp: string | null;
    alamat: string | null;
    provinsi: string | null;
    kabupaten_kota: string | null;
    kecamatan: string | null;
    kode_pos: string | null;
    ekspedisi: string | null;
    shipping_cost: number;
    voucher_code_snapshot: string | null;
    voucher_scope_snapshot: string;
    voucher_discount_amount: number;
    metode_bayar: string;
    payment_status: string;
    status: string;
    is_dropship: boolean;
    created_at: string;
    items: OrderItemRow[];
    dropshipper: {
        end_customer_name: string | null;
        end_customer_whatsapp: string | null;
        end_customer_address: string | null;
    } | null;
};

const props = defineProps<{
    order: OrderProps;
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

const statusLabel: Record<string, string> = {
    menunggu_konfirmasi: 'Menunggu Konfirmasi',
    diproses: 'Diproses',
    dikirim: 'Dikirim',
    selesai: 'Selesai',
    batal: 'Batal',
};

const paymentLabel: Record<string, string> = {
    cash: 'Cash',
    transfer: 'Transfer',
    cod: 'COD',
};

const alamatLengkap = computed(() =>
    [
        props.order.alamat,
        props.order.kecamatan,
        props.order.kabupaten_kota,
        props.order.provinsi,
        props.order.kode_pos,
    ]
        .filter(Boolean)
        .join(', '),
);

const subtotal = computed(() =>
    props.order.items.reduce(
        (sum, item) => sum + item.price_final * item.qty,
        0,
    ),
);

const totalDiskon = computed(() =>
    props.order.items.reduce(
        (sum, item) =>
            sum +
            (item.promo_discount_amount + item.tier_discount_amount) * item.qty,
        0,
    ),
);

const total = computed(
    () =>
        subtotal.value +
        props.order.shipping_cost -
        props.order.voucher_discount_amount,
);

const columns: InvoiceColumn[] = [
    { key: 'no', label: 'No', align: 'center' },
    { key: 'buku', label: 'Buku' },
    { key: 'cetakan', label: 'Cetakan' },
    { key: 'qty', label: 'Qty', align: 'center' },
    { key: 'harga', label: 'Harga', align: 'right' },
    { key: 'diskon', label: 'Diskon', align: 'right' },
    { key: 'jumlah', label: 'Jumlah', align: 'right' },
];

const rows = computed(() =>
    props.order.items.map((item, i) => ({
        no: String(i + 1),
        buku: item.judul_snapshot,
        cetakan: item.edition_snapshot ?? '—',
        qty: String(item.qty),
        harga: idr(item.price_final),
        diskon: idr(
            (item.promo_discount_amount + item.tier_discount_amount) * item.qty,
        ),
        jumlah: idr(item.price_final * item.qty),
    })),
);

const kepadaLines = computed(() => [
    { label: 'Nama', value: props.order.nama_pembeli },
    { label: 'Alamat', value: alamatLengkap.value },
    { label: 'No. HP', value: props.order.no_hp ?? '' },
    ...(props.order.is_dropship && props.order.dropshipper
        ? [
              {
                  label: 'Penerima',
                  value:
                      props.order.dropshipper.end_customer_name ??
                      props.order.nama_pembeli,
              },
              {
                  label: 'Alamat Kirim',
                  value: props.order.dropshipper.end_customer_address ?? '',
              },
          ]
        : []),
]);

const kirimLines = computed(() => [
    {
        label: 'Ekspedisi',
        value: props.order.ekspedisi ? String(props.order.ekspedisi) : '—',
    },
    { label: 'Ongkir', value: idr(props.order.shipping_cost) },
    {
        label: 'Metode Bayar',
        value:
            paymentLabel[props.order.metode_bayar] ?? props.order.metode_bayar,
    },
    {
        label: 'Status Bayar',
        value: props.order.payment_status === 'lunas' ? 'Lunas' : 'Menunggu',
    },
]);
</script>

<template>
    <div>
        <!-- Toolbar — tidak ikut tercetak -->
        <div
            class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4 print:hidden"
        >
            <p class="text-sm text-muted-foreground">
                Nota penjualan — gunakan tombol Cetak lalu pilih "Save as PDF"
                bila perlu.
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
                title="Invoice"
                :no="order.no_order"
                :tanggal="formatDateID(order.created_at)"
                :subtitle="statusLabel[order.status] ?? order.status"
            />

            <div class="mt-4 grid grid-cols-2 gap-6">
                <InvoiceParty title="Kepada" :lines="kepadaLines" />
                <InvoiceParty
                    title="Pengiriman & Pembayaran"
                    :lines="kirimLines"
                />
            </div>

            <div class="mt-5">
                <InvoiceItemsTable :columns="columns" :rows="rows" />
            </div>

            <div class="mt-4">
                <InvoiceSummary
                    :lines="[
                        { label: 'Subtotal', value: idr(subtotal) },
                        {
                            label: 'Total Diskon',
                            value: `-${idr(totalDiskon)}`,
                        },
                        ...(order.voucher_discount_amount > 0
                            ? [
                                  {
                                      label: `Voucher ${
                                          order.voucher_code_snapshot ?? ''
                                      }${
                                          order.voucher_scope_snapshot ===
                                          'ongkir'
                                              ? ' (ongkir)'
                                              : ''
                                      }`,
                                      value: `-${idr(
                                          order.voucher_discount_amount,
                                      )}`,
                                  },
                              ]
                            : []),
                        { label: 'Ongkir', value: idr(order.shipping_cost) },
                    ]"
                    total-label="TOTAL"
                    :total="idr(total)"
                />
            </div>

            <InvoiceFooter
                :signatures="[{ label: 'Penerima' }, { label: 'Hormat kami' }]"
                thanks="Terima kasih sudah berbelanja di toko kami!"
            />
        </InvoiceSheet>
    </div>
</template>
