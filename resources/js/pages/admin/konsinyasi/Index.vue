<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Konsinyasi', href: '/admin/konsinyasi' },
        ],
    },
});

import { Head, router, useHttp } from '@inertiajs/vue3';
import { HandCoins, Plus, Search, Undo2, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConsignmentController from '@/actions/App/Http/Controllers/Admin/ConsignmentController';
import BookPicker from '@/components/BookPicker.vue';
import type { BookOption } from '@/components/BookPicker.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CurrencyInput from '@/components/CurrencyInput.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateID, todayWIB } from '@/lib/date';
import { books as booksOptionsRoute } from '@/routes/admin/konsinyasi/options';

type Partner = {
    id: string;
    name: string;
    whatsapp_number: string | null;
    status_pelanggan?: string;
};

type TierDiscount = {
    tier: string;
    min_qty: number;
    discount_percent: number;
};

type DeliveryItem = {
    id: string;
    qty: number;
    harga_asli: number;
    harga_titip: number;
    book: { id: string; judul: string; harga: number } | null;
};

type SaleItem = {
    id: string;
    qty: number;
    price: number;
    book: { id: string; judul: string } | null;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

type Delivery = {
    id: string;
    kode: string | null;
    delivery_date: string;
    notes: string | null;
    customer: Partner | null;
    items: DeliveryItem[];
};

type Sale = {
    id: string;
    kode: string | null;
    sale_date: string;
    notes: string | null;
    customer: Partner | null;
    items: SaleItem[];
    receivable: { id: string; amount: number; paid_amount: number } | null;
};

type ConsignmentReturnRow = {
    id: string;
    kode: string | null;
    return_date: string;
    qty: number;
    notes: string | null;
    customer: Partner | null;
    book: { id: string; judul: string; kode_sku: string | null } | null;
};

const props = defineProps<{
    partners: Partner[];
    tierDiscounts: TierDiscount[];
    warehouses: Array<{ id: string; kode: string; nama: string }>;
    deliveries: {
        data: Delivery[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: PaginatorLink[];
    };
    sales: {
        data: Sale[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: PaginatorLink[];
    };
    returns: {
        data: ConsignmentReturnRow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: PaginatorLink[];
    };
}>();

const activeTab = ref<'serah_terima' | 'laku' | 'retur'>('serah_terima');

const tabs = computed(
    () =>
        [
            {
                key: 'serah_terima',
                label: 'Serah Terima',
                count: props.deliveries.total,
            },
            { key: 'laku', label: 'Laporan Laku', count: props.sales.total },
            { key: 'retur', label: 'Retur Sisa', count: props.returns.total },
        ] as const,
);

const expandedDeliveryIds = ref<Set<string | number>>(new Set());
const expandedSaleIds = ref<Set<string | number>>(new Set());

// ── Filter ─────────────────────────────────────────────────────────
const filterMitra = ref('all');
const mitraSearch = ref('');
const mitraListOpen = ref(false);
const availablePartners = ref<Partner[]>(props.partners);
const filterFrom = ref('');
const filterTo = ref('');

const hasActiveFilter = computed(
    () =>
        filterMitra.value !== 'all' ||
        filterFrom.value !== '' ||
        filterTo.value !== '',
);

function resetFilter() {
    filterMitra.value = 'all';
    mitraSearch.value = '';
    availablePartners.value = props.partners;
    filterFrom.value = '';
    filterTo.value = '';
}

function searchMitraPartners() {
    mitraListOpen.value = true;
    const q = mitraSearch.value.trim().toLowerCase();

    if (!q) {
        availablePartners.value = props.partners;

        return;
    }

    // Filter lokal biar 1 huruf langsung muncul — tanpa tunggu API
    availablePartners.value = props.partners.filter((p) =>
        p.name.toLowerCase().includes(q),
    );
}

function selectFilterMitra(partner: Partner | null) {
    if (partner) {
        filterMitra.value = partner.id;
        mitraSearch.value = partner.name;
    } else {
        filterMitra.value = 'all';
        mitraSearch.value = '';
        availablePartners.value = props.partners;
    }

    mitraListOpen.value = false;
}

watch(mitraSearch, (val) => {
    if (!val.trim() && filterMitra.value !== 'all') {
        filterMitra.value = 'all';
        availablePartners.value = props.partners;
    }
});

function onMitraSearchBlur() {
    setTimeout(() => {
        mitraListOpen.value = false;
    }, 150);
}

const filteredDeliveries = computed(() => {
    let data = props.deliveries.data;

    if (filterMitra.value !== 'all') {
        data = data.filter((d) => d.customer?.id === filterMitra.value);
    }

    if (filterFrom.value) {
        data = data.filter((d) => d.delivery_date >= filterFrom.value);
    }

    if (filterTo.value) {
        data = data.filter((d) => d.delivery_date <= filterTo.value);
    }

    return data;
});
const filteredSales = computed(() => {
    let data = props.sales.data;

    if (filterMitra.value !== 'all') {
        data = data.filter((s) => s.customer?.id === filterMitra.value);
    }

    if (filterFrom.value) {
        data = data.filter((s) => s.sale_date >= filterFrom.value);
    }

    if (filterTo.value) {
        data = data.filter((s) => s.sale_date <= filterTo.value);
    }

    return data;
});
const filteredReturns = computed(() => {
    let data = props.returns.data;

    if (filterMitra.value !== 'all') {
        data = data.filter((r) => r.customer?.id === filterMitra.value);
    }

    if (filterFrom.value) {
        data = data.filter((r) => r.return_date >= filterFrom.value);
    }

    if (filterTo.value) {
        data = data.filter((r) => r.return_date <= filterTo.value);
    }

    return data;
});

// ── Bayar piutang dari Laporan Laku ─────────────────────────────────
const payOpen = ref(false);
const paySale = ref<Sale | null>(null);
const payAmount = ref<number>(0);
const payMetode = ref('transfer');
const payDate = ref(todayWIB());

function openPayDialog(sale: Sale) {
    paySale.value = sale;
    const remaining =
        (sale.receivable?.amount ?? 0) - (sale.receivable?.paid_amount ?? 0);
    payAmount.value = remaining;
    payMetode.value = 'transfer';
    payDate.value = todayWIB();
    payOpen.value = true;
}

function submitPay() {
    if (!paySale.value?.receivable?.id) {
        return;
    }

    const remaining =
        paySale.value.receivable.amount - paySale.value.receivable.paid_amount;

    if (payAmount.value <= 0 || payAmount.value > remaining) {
        toast.error(`Nominal harus 1 - ${remaining.toLocaleString('id-ID')}`);

        return;
    }

    router.post(
        `/admin/receivables/${paySale.value.receivable.id}/pay`,
        {
            amount: payAmount.value,
            metode: payMetode.value,
            paid_at: payDate.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                payOpen.value = false;
                toast.success('Pembayaran piutang tercatat.');
            },
            onError: (e) =>
                toast.error(firstErrorMessage(e, 'Gagal bayar piutang.')),
        },
    );
}

// ── Columns ────────────────────────────────────────────────────────
const deliveryColumns: DataTableColumn[] = [
    { key: 'expand', header: '', expandable: true, cellClass: 'w-10' },
    { key: 'kode', header: 'Kode', cellClass: 'font-mono text-xs' },
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-sm',
    },
    {
        key: 'mitra',
        header: 'Mitra',
        cellClass: 'font-medium text-sm max-w-32 truncate',
    },
    { key: 'qty', header: 'Qty', cellClass: 'text-right tabular-nums text-sm' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const saleColumns: DataTableColumn[] = [
    { key: 'expand', header: '', expandable: true, cellClass: 'w-10' },
    { key: 'kode', header: 'Kode', cellClass: 'font-mono text-xs' },
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-sm',
    },
    {
        key: 'mitra',
        header: 'Mitra',
        cellClass: 'font-medium text-sm max-w-32 truncate',
    },
    { key: 'qty', header: 'Qty', cellClass: 'text-right tabular-nums text-sm' },
    {
        key: 'total',
        header: 'Piutang',
        cellClass: 'text-right tabular-nums text-sm font-medium',
    },
    { key: 'status', header: 'Status', cellClass: 'text-sm' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const returnColumns: DataTableColumn[] = [
    { key: 'kode', header: 'Kode', cellClass: 'font-mono text-xs' },
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-sm',
    },
    {
        key: 'mitra',
        header: 'Mitra',
        cellClass: 'font-medium text-sm max-w-32 truncate',
    },
    { key: 'buku', header: 'Buku', cellClass: 'text-sm max-w-44 truncate' },
    { key: 'qty', header: 'Qty', cellClass: 'text-right tabular-nums text-sm' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

function formatDate(dateStr: string): string {
    // Samakan dengan Laporan Penjualan (d/m/Y) — konsisten semua tabel
    return formatDateID(dateStr, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function saleTotal(sale: Sale): number {
    return sale.items.reduce((sum, item) => sum + item.qty * item.price, 0);
}
function saleQty(sale: Sale): number {
    return sale.items.reduce((sum, item) => sum + item.qty, 0);
}

// ── Trigger lapor laku dari serah terima ──────────────────────────
function triggerLaporLaku(delivery: Delivery) {
    const partnerId = delivery.customer?.id;

    if (!partnerId) {
        toast.error('Mitra tidak ditemukan.');

        return;
    }

    activeTab.value = 'laku';
    saleOpen.value = true;
    salePartnerId.value = partnerId;
    saleDeliveryKode.value = (delivery as any).kode ?? '';
    saleDate.value = todayWIB();
    loadTitipan(partnerId, saleDeliveryKode.value || undefined);
}

function openInvoice(delivery: Delivery) {
    window.open(
        `/admin/konsinyasi/deliveries/${delivery.id}/invoice`,
        '_blank',
    );
}
function openSaleInvoice(sale: Sale) {
    window.open(`/admin/konsinyasi/sales/${sale.id}/invoice`, '_blank');
}

// ── Dialog serah terima ───────────────────────────────────────────
const deliverOpen = ref(false);

type DeliverForm = {
    book_id: string;
    judul: string;
    harga: number;
    harga_asli: number;
    harga_titip: number;
    diskon_percent: number;
    stok: number;
    qty: number;
};

function getTierDiscount(tier: string | undefined, qty: number): number {
    if (!tier) {
        return 0;
    }

    const candidates = props.tierDiscounts
        .filter((t) => t.tier === tier && t.min_qty <= qty)
        .sort((a, b) => b.min_qty - a.min_qty);

    return candidates[0]?.discount_percent ?? 0;
}

function hargaTitipFor(
    harga: number,
    tier: string | undefined,
    qty: number = 1,
): { titip: number; diskon: number; percent: number } {
    const p = getTierDiscount(tier, qty);
    const diskon = Math.floor((harga * p) / 100);

    return { titip: Math.max(0, harga - diskon), diskon, percent: p };
}

function updateDeliverItemHarga(item: DeliverForm) {
    const partner = props.partners.find((p) => p.id === deliverPartnerId.value);
    const tier = (partner as any)?.status_pelanggan ?? 'bazaf';
    const { titip, percent } = hargaTitipFor(item.harga_asli, tier, item.qty);
    item.harga = titip;
    item.harga_titip = titip;
    item.diskon_percent = percent;
}

const deliverPartnerId = ref('');
const deliverWarehouseId = ref('');
const pendingWarehouseId = ref<string | null>(null);
const warehouseConfirmOpen = ref(false);
const deliverDate = ref(todayWIB());
const deliverNotes = ref('');
const deliverItems = ref<DeliverForm[]>([]);

// Set default warehouse ke pertama
watch(
    () => props.warehouses,
    (list) => {
        if (!deliverWarehouseId.value && list.length) {
            deliverWarehouseId.value = (list[0] as any).id;
        }
    },
    { immediate: true },
);

watch(deliverPartnerId, () => {
    for (const item of deliverItems.value) {
        updateDeliverItemHarga(item);
    }
});

function onWarehouseChange(newId: unknown) {
    if (typeof newId !== 'string') {
        return;
    }

    if (!deliverItems.value.length) {
        deliverWarehouseId.value = newId;

        return;
    }

    pendingWarehouseId.value = newId;
    warehouseConfirmOpen.value = true;
}

function confirmWarehouseChange() {
    if (pendingWarehouseId.value) {
        deliverWarehouseId.value = pendingWarehouseId.value;
    }

    deliverItems.value = [];
    pendingWarehouseId.value = null;
    warehouseConfirmOpen.value = false;
}

function cancelWarehouseChange() {
    pendingWarehouseId.value = null;
    warehouseConfirmOpen.value = false;
}

function addDeliverBook(bookOption: BookOption) {
    const book = bookOption as BookOption & { harga?: number; stok?: number };

    if (deliverItems.value.some((item) => item.book_id === book.id)) {
        toast.info('Buku sudah ada di daftar.');

        return;
    }

    const partner = props.partners.find((p) => p.id === deliverPartnerId.value);
    const tier = (partner as any)?.status_pelanggan ?? 'bazaf';
    const hargaAsli = book.harga ?? 0;
    const { titip, percent } = hargaTitipFor(hargaAsli, tier);
    deliverItems.value.push({
        book_id: book.id,
        judul: book.judul,
        harga: titip,
        harga_asli: hargaAsli,
        harga_titip: titip,
        diskon_percent: percent,
        stok: book.stok ?? 0,
        qty: 1,
    });
}

const deliverTotalQty = computed(() =>
    deliverItems.value.reduce((sum, item) => sum + (Number(item.qty) || 0), 0),
);
const deliverTotalValue = computed(() =>
    deliverItems.value.reduce(
        (sum, item) => sum + (Number(item.qty) || 0) * item.harga,
        0,
    ),
);

function submitDeliver() {
    if (!deliverPartnerId.value) {
        toast.error('Pilih mitra terlebih dahulu.');

        return;
    }

    if (!deliverItems.value.length) {
        toast.error('Minimal satu judul buku.');

        return;
    }

    const items = deliverItems.value.filter((item) => Number(item.qty) > 0);

    if (!items.length) {
        toast.error('Qty minimal 1 untuk salah satu buku.');

        return;
    }

    if (!deliverWarehouseId.value) {
        toast.error('Pilih gudang asal terlebih dahulu.');

        return;
    }

    router.post(
        ConsignmentController.storeDelivery.url(),
        {
            customer_id: deliverPartnerId.value,
            warehouse_id: deliverWarehouseId.value,
            delivery_date: deliverDate.value,
            notes: deliverNotes.value || null,
            items: items.map(({ book_id, qty, harga_asli, harga_titip }) => ({
                book_id,
                qty: Number(qty),
                harga_asli,
                harga_titip,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                resetDeliverForm();
                toast.success(
                    'Serah terima tercatat — invoice tersedia di aksi.',
                );
            },
            onError: (errors) =>
                toast.error(
                    firstErrorMessage(errors, 'Gagal menyimpan serah terima.'),
                ),
        },
    );
}

function resetDeliverForm() {
    deliverOpen.value = false;
    deliverPartnerId.value = '';
    deliverDate.value = todayWIB();
    deliverNotes.value = '';
    deliverItems.value = [];
}

// ── Dialog lapor laku & retur ─────────────────────────────────────
type TitipanOption = {
    book_id: string;
    judul: string;
    kode_sku: string | null;
    harga: number;
    harga_asli: number;
    harga_titip: number;
    diskon: number;
    diskon_percent: number;
    sisa: number;
};

const saleOpen = ref(false);
const salePartnerId = ref('');
const saleDeliveryKode = ref('all');
const saleDate = ref(todayWIB());
const saleNotes = ref('');
const titipanList = ref<TitipanOption[]>([]);
const titipanLoading = ref(false);
const saleQtys = reactive<Record<string, number>>({});
const salePrices = reactive<Record<string, number>>({});

const deliveryOptionsForSale = computed(() => {
    if (!salePartnerId.value) {
        return [];
    }

    return props.deliveries.data
        .filter((d) => d.customer?.id === salePartnerId.value)
        .map((d) => ({
            kode: (d as any).kode ?? d.id,
            label: `${(d as any).kode ?? d.id.slice(0, 8)} — ${d.delivery_date} (${d.items.length} judul)`,
        }));
});

const titipanRequest = useHttp({});

function loadTitipan(customerId: string, kode?: string) {
    titipanList.value = [];

    if (!customerId) {
        return;
    }

    titipanLoading.value = true;
    const url = kode
        ? `${ConsignmentController.titipanOptions(customerId).url}?kode=${encodeURIComponent(kode)}`
        : ConsignmentController.titipanOptions(customerId).url;
    titipanRequest.get(url, {
        onSuccess: (data) => {
            titipanList.value = data as TitipanOption[];

            for (const option of titipanList.value) {
                saleQtys[option.book_id] ??= 0;
                salePrices[option.book_id] ??=
                    option.harga_titip ?? option.harga;
            }
        },
        onFinish: () => {
            titipanLoading.value = false;
        },
    });
}

function openSaleDialog() {
    saleOpen.value = true;
    salePartnerId.value = props.partners[0]?.id ?? '';
    saleDeliveryKode.value = 'all';

    if (salePartnerId.value) {
        loadTitipan(salePartnerId.value);
    }
}

function onSalePartnerChange() {
    Object.keys(saleQtys).forEach((key) => delete saleQtys[key]);
    Object.keys(salePrices).forEach((key) => delete salePrices[key]);
    saleDeliveryKode.value = 'all';

    if (salePartnerId.value) {
        loadTitipan(salePartnerId.value);
    }
}

function onSaleKodeChange() {
    Object.keys(saleQtys).forEach((key) => delete saleQtys[key]);
    Object.keys(salePrices).forEach((key) => delete salePrices[key]);

    if (salePartnerId.value) {
        loadTitipan(
            salePartnerId.value,
            saleDeliveryKode.value === 'all'
                ? undefined
                : saleDeliveryKode.value,
        );
    }
}

function onSaleQtyChange(bookId: string) {
    const line = titipanList.value.find((t) => t.book_id === bookId);

    if (!line) {
        return;
    }

    const qty = Number(saleQtys[bookId] ?? 0);
    const partner = props.partners.find((p) => p.id === salePartnerId.value);
    const tier = (partner as any)?.status_pelanggan ?? 'bazaf';
    const { titip } = hargaTitipFor(line.harga_asli, tier, qty || 1);
    salePrices[bookId] = titip;
}

const saleLines = computed(() =>
    titipanList.value.map((t) => {
        const qty = Math.max(
            0,
            Math.min(Number(saleQtys[t.book_id] ?? 0), t.sisa),
        );
        // Update harga titip jika qty berubah (tier qty)
        const partner = props.partners.find(
            (p) => p.id === salePartnerId.value,
        );
        const tier = (partner as any)?.status_pelanggan ?? 'bazaf';
        const { titip } = hargaTitipFor(t.harga_asli, tier, qty || 1);

        // Sinkronkan salePrices jika tier berubah dan user belum edit manual
        if (
            qty > 0 &&
            salePrices[t.book_id] !== titip &&
            saleQtys[t.book_id] > 0
        ) {
            // Jangan override jika user sudah edit manual (biarkan), tapi untuk demo auto-sync
        }

        return {
            ...t,
            qty,
            price: Math.max(0, Number(salePrices[t.book_id] ?? t.harga_titip)),
        };
    }),
);
const saleGrandTotal = computed(() =>
    saleLines.value.reduce((sum, line) => sum + line.qty * line.harga, 0),
);

function submitSale() {
    const items = saleLines.value
        .filter((line) => line.qty > 0)
        .map(({ book_id, qty, price }) => ({ book_id, qty, price }));

    if (!items.length) {
        toast.error('Isi qty laku minimal untuk satu buku.');

        return;
    }

    router.post(
        ConsignmentController.storeSale.url(),
        {
            customer_id: salePartnerId.value,
            sale_date: saleDate.value,
            notes: saleNotes.value || null,
            items,
        },
        {
            preserveScroll: true,
            onSuccess: () => resetSaleForm(),
            onError: (errors) =>
                toast.error(
                    firstErrorMessage(errors, 'Gagal menyimpan laporan laku.'),
                ),
        },
    );
}

function resetSaleForm() {
    saleOpen.value = false;
    salePartnerId.value = '';
    saleDeliveryKode.value = 'all';
    saleDate.value = todayWIB();
    saleNotes.value = '';
    titipanList.value = [];
    Object.keys(saleQtys).forEach((key) => delete saleQtys[key]);
    Object.keys(salePrices).forEach((key) => delete salePrices[key]);
}

watch(saleOpen, (open) => {
    if (!open) {
        resetSaleForm();
    }
});
watch(deliverOpen, (open) => {
    if (!open) {
        deliverPartnerId.value = '';
        deliverWarehouseId.value = props.warehouses[0]?.id ?? '';
        deliverDate.value = todayWIB();
        deliverNotes.value = '';
        deliverItems.value = [];
        pendingWarehouseId.value = null;
        warehouseConfirmOpen.value = false;
    }
});

const returnOpen = ref(false);
const returnPartnerId = ref('');
const returnBookId = ref('');
const returnQty = ref<number>(1);
const returnDate = ref(todayWIB());
const returnNotes = ref('');

function openReturnDialog() {
    returnOpen.value = true;
    returnPartnerId.value = props.partners[0]?.id ?? '';

    if (returnPartnerId.value) {
        loadTitipan(returnPartnerId.value);
    }
}

function onReturnPartnerChange() {
    returnBookId.value = '';
    returnQty.value = 1;

    if (returnPartnerId.value) {
        loadTitipan(returnPartnerId.value);
    }
}

const selectedReturnOption = computed(() =>
    titipanList.value.find((option) => option.book_id === returnBookId.value),
);

function submitReturn() {
    if (!returnPartnerId.value || !returnBookId.value) {
        toast.error('Pilih mitra dan buku yang diretur.');

        return;
    }

    const maxQty = selectedReturnOption.value?.sisa ?? 0;

    if (Number(returnQty.value) < 1 || Number(returnQty.value) > maxQty) {
        toast.error(`Qty retur harus antara 1 dan ${maxQty}.`);

        return;
    }

    router.post(
        ConsignmentController.storeReturn.url(),
        {
            customer_id: returnPartnerId.value,
            book_id: returnBookId.value,
            qty: Number(returnQty.value),
            return_date: returnDate.value,
            notes: returnNotes.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => resetReturnForm(),
            onError: (errors) =>
                toast.error(
                    firstErrorMessage(errors, 'Gagal menyimpan retur.'),
                ),
        },
    );
}

function resetReturnForm() {
    returnOpen.value = false;
    returnPartnerId.value = '';
    returnBookId.value = '';
    returnQty.value = 1;
    returnDate.value = todayWIB();
    returnNotes.value = '';
    titipanList.value = [];
}

const deletingTarget = ref<{
    type: 'delivery' | 'sale' | 'return';
    id: string;
    label: string;
} | null>(null);

function confirmDelete(
    type: 'delivery' | 'sale' | 'return',
    id: string,
    label: string,
) {
    deletingTarget.value = { type, id, label };
}

function executeDelete() {
    const target = deletingTarget.value;
    deletingTarget.value = null;

    if (!target) {
        return;
    }

    if (target.type === 'delivery') {
        router.delete(ConsignmentController.destroyDelivery(target.id).url, {
            preserveScroll: true,
        });
    } else if (target.type === 'sale') {
        router.delete(ConsignmentController.destroySale(target.id).url, {
            preserveScroll: true,
        });
    } else {
        router.delete(ConsignmentController.destroyReturn(target.id).url, {
            preserveScroll: true,
        });
    }
}

function firstErrorMessage(
    errors: Record<string, string>,
    fallback: string,
): string {
    const first = Object.values(errors ?? {})[0];

    return typeof first === 'string' && first ? first : fallback;
}
</script>

<template>
    <Head title="Konsinyasi" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Konsinyasi</h1>
                <p class="text-sm text-muted-foreground">
                    Titipan ke mitra Bazaf — bayar sesuai laku
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="activeTab === 'serah_terima'"
                    @click="deliverOpen = true"
                >
                    <Plus class="size-4" /> Serah Terima
                </Button>
                <Button v-if="activeTab === 'laku'" @click="openSaleDialog">
                    <HandCoins class="size-4" /> Lapor Laku
                </Button>
                <Button v-if="activeTab === 'retur'" @click="openReturnDialog">
                    <Undo2 class="size-4" /> Retur Sisa
                </Button>
            </div>
        </div>

        <div class="flex gap-1 overflow-x-auto border-b">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="
                    activeTab === tab.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count"
                    class="ml-1 rounded-full bg-muted px-1.5 py-0.5 text-xs"
                    >{{ tab.count }}</span
                >
            </button>
        </div>

        <!-- Filter -->
        <div
            class="flex w-full flex-col divide-y divide-border overflow-visible rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
            <div class="relative flex items-center overflow-visible md:w-56">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="mitraSearch"
                    placeholder="Cari mitra..."
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9"
                    @focus="
                        () => {
                            mitraListOpen = true;
                            searchMitraPartners();
                        }
                    "
                    @input="searchMitraPartners"
                    @blur="onMitraSearchBlur"
                />
                <div
                    v-if="mitraListOpen"
                    class="absolute top-full left-0 z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
                >
                    <button
                        type="button"
                        class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
                        :class="filterMitra === 'all' ? 'bg-accent' : ''"
                        @mousedown.prevent="selectFilterMitra(null)"
                    >
                        Semua mitra
                    </button>
                    <button
                        v-for="p in availablePartners"
                        :key="p.id"
                        type="button"
                        class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
                        :class="filterMitra === p.id ? 'bg-accent' : ''"
                        @mousedown.prevent="selectFilterMitra(p)"
                    >
                        {{ p.name }}
                    </button>
                    <p
                        v-if="!availablePartners.length"
                        class="px-2 py-1.5 text-sm text-muted-foreground"
                    >
                        Tidak ada mitra
                    </p>
                </div>
            </div>
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Dari
                </p>
                <Input
                    v-model="filterFrom"
                    type="date"
                    class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-36"
                    aria-label="Dari tanggal"
                />
            </div>
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Sampai
                </p>
                <Input
                    v-model="filterTo"
                    type="date"
                    class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-36"
                    aria-label="Sampai tanggal"
                />
            </div>
            <button
                v-if="hasActiveFilter"
                type="button"
                class="flex h-11 w-full items-center justify-center gap-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-destructive md:h-9 md:w-9"
                title="Hapus filter"
                @click="resetFilter"
            >
                <X class="size-4" /><span class="md:hidden">Hapus filter</span>
            </button>
        </div>

        <!-- Serah Terima -->
        <DataTable
            v-show="activeTab === 'serah_terima'"
            :data="filteredDeliveries"
            :columns="deliveryColumns"
            :paginator="deliveries"
            key-field="id"
            expandable
            :expanded-ids="expandedDeliveryIds"
            empty-title="Belum ada serah terima"
            empty-description="Penyerahan barang ke mitra tampil di sini."
            @update:expanded-ids="expandedDeliveryIds = $event"
        >
            <template #cell-kode="{ row }">{{ row.kode ?? '—' }}</template>
            <template #cell-tanggal="{ row }">{{
                formatDate(row.delivery_date)
            }}</template>
            <template #cell-mitra="{ row }">{{
                row.customer?.name ?? '—'
            }}</template>
            <template #cell-qty="{ row }">{{
                row.items.reduce((s: number, i: DeliveryItem) => s + i.qty, 0)
            }}</template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Lapor Laku',
                            onClick: () => triggerLaporLaku(row),
                        },
                        { label: 'Invoice', onClick: () => openInvoice(row) },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () =>
                                confirmDelete(
                                    'delivery',
                                    row.id,
                                    `serah terima ${formatDate(row.delivery_date)}`,
                                ),
                        },
                    ]"
                />
            </template>
            <template #expanded-row="{ row }">
                <div class="p-3">
                    <div class="overflow-hidden rounded-md border">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Buku
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Qty
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Asli
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Titip
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in row.items"
                                    :key="item.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2 text-sm">
                                        {{ item.book?.judul ?? '—' }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-left text-sm tabular-nums"
                                    >
                                        {{ item.qty }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-xs text-muted-foreground tabular-nums line-through"
                                    >
                                        <Money
                                            :value="
                                                (item as any).harga_asli ??
                                                item.book?.harga ??
                                                0
                                            "
                                        />
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-sm font-medium tabular-nums"
                                    >
                                        <Money
                                            :value="
                                                (item as any).harga_titip ??
                                                item.book?.harga ??
                                                0
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </DataTable>

        <!-- Lapor Laku -->
        <DataTable
            v-show="activeTab === 'laku'"
            :data="filteredSales"
            :columns="saleColumns"
            :paginator="sales"
            key-field="id"
            expandable
            :expanded-ids="expandedSaleIds"
            empty-title="Belum ada laporan laku"
            empty-description="Rekap laku mitra tampil di sini."
            @update:expanded-ids="expandedSaleIds = $event"
        >
            <template #cell-kode="{ row }">{{ row.kode ?? '—' }}</template>
            <template #cell-tanggal="{ row }">{{
                formatDate(row.sale_date)
            }}</template>
            <template #cell-mitra="{ row }">{{
                row.customer?.name ?? '—'
            }}</template>
            <template #cell-qty="{ row }">{{ saleQty(row) }}</template>
            <template #cell-total="{ row }"
                ><Money :value="saleTotal(row)"
            /></template>
            <template #cell-status="{ row }">
                <StatusBadge
                    v-if="row.receivable"
                    :variant="
                        row.receivable.paid_amount >= row.receivable.amount
                            ? 'success'
                            : 'warning'
                    "
                    :label="
                        row.receivable.paid_amount >= row.receivable.amount
                            ? 'Lunas'
                            : 'Belum Lunas'
                    "
                />
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Invoice',
                            onClick: () => openSaleInvoice(row),
                        },
                        ...(row.receivable &&
                        row.receivable.paid_amount < row.receivable.amount
                            ? [
                                  {
                                      label: 'Bayar',
                                      onClick: () => openPayDialog(row),
                                  },
                              ]
                            : []),
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () =>
                                confirmDelete(
                                    'sale',
                                    row.id,
                                    `laporan laku ${formatDate(row.sale_date)}`,
                                ),
                        },
                    ]"
                />
            </template>
            <template #expanded-row="{ row }">
                <div class="p-3">
                    <div class="overflow-hidden rounded-md border">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Buku
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Qty
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Harga
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Subtotal
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in row.items"
                                    :key="item.id"
                                    class="border-t"
                                >
                                    <td
                                        class="max-w-56 truncate px-3 py-2 text-sm"
                                    >
                                        {{ item.book?.judul ?? '—' }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-left text-sm tabular-nums"
                                    >
                                        {{ item.qty }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-sm tabular-nums"
                                    >
                                        <Money :value="item.price" />
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-sm font-medium tabular-nums"
                                    >
                                        <Money :value="item.qty * item.price" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </DataTable>

        <!-- Retur -->
        <DataTable
            v-show="activeTab === 'retur'"
            :data="filteredReturns"
            :columns="returnColumns"
            :paginator="returns"
            empty-title="Belum ada retur"
            empty-description="Barang sisa yang dikembalikan tampil di sini."
        >
            <template #cell-kode="{ row }">{{ row.kode ?? '—' }}</template>
            <template #cell-tanggal="{ row }">{{
                formatDate(row.return_date)
            }}</template>
            <template #cell-mitra="{ row }">{{
                row.customer?.name ?? '—'
            }}</template>
            <template #cell-buku="{ row }">
                <p class="text-sm">{{ row.book?.judul ?? '—' }}</p>
                <p
                    v-if="row.book?.kode_sku"
                    class="font-mono text-xs text-muted-foreground"
                >
                    {{ row.book.kode_sku }}
                </p>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () =>
                                confirmDelete(
                                    'return',
                                    row.id,
                                    `retur ${formatDate(row.return_date)}`,
                                ),
                        },
                    ]"
                />
            </template>
        </DataTable>

        <!-- Dialog: Serah Terima -->
        <Dialog v-model:open="deliverOpen">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Catat Serah Terima</DialogTitle>
                    <DialogDescription
                        >Barang diserahkan ke mitra — stok toko berkurang
                        menjadi titipan.</DialogDescription
                    >
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Mitra *</Label>
                        <Select v-model="deliverPartnerId">
                            <SelectTrigger
                                ><SelectValue
                                    placeholder="Pilih mitra (tier Bazaf)"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="partner in partners"
                                    :key="partner.id"
                                    :value="partner.id"
                                    >{{ partner.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Gudang Asal *</Label>
                        <Select
                            :model-value="deliverWarehouseId"
                            @update:model-value="onWarehouseChange"
                        >
                            <SelectTrigger
                                ><SelectValue placeholder="Pilih gudang"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="wh in warehouses"
                                    :key="wh.id"
                                    :value="wh.id"
                                    >{{ wh.nama }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="deliver_date">Tanggal *</Label>
                        <Input
                            id="deliver_date"
                            v-model="deliverDate"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Tambah Buku *</Label>
                        <BookPicker
                            :base-url="
                                deliverWarehouseId
                                    ? `${booksOptionsRoute().url}?warehouse_id=${deliverWarehouseId}`
                                    : booksOptionsRoute().url
                            "
                            :key="deliverWarehouseId"
                            placeholder="Cari judul / SKU buku..."
                            @select="addDeliverBook"
                        />
                        <p class="text-xs text-muted-foreground">
                            Stok tampil per gudang terpilih
                        </p>
                    </div>
                    <div
                        v-if="deliverItems.length"
                        class="overflow-hidden rounded-md border"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b bg-muted/50 text-left text-xs font-medium text-muted-foreground"
                                >
                                    <th class="px-3 py-2">Buku</th>
                                    <th class="w-32 px-3 py-2 text-right">
                                        Harga
                                    </th>
                                    <th class="w-24 px-3 py-2">Qty</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in deliverItems"
                                    :key="item.book_id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-3 py-2 text-sm">
                                        {{ item.judul }}
                                        <span
                                            class="block text-xs text-muted-foreground"
                                            >Stok: {{ item.stok }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <span
                                            v-if="item.diskon_percent"
                                            class="text-xs text-muted-foreground line-through"
                                            ><Money :value="item.harga_asli"
                                        /></span>
                                        <span class="ml-1 text-sm font-medium"
                                            ><Money :value="item.harga_titip"
                                        /></span>
                                        <span
                                            v-if="item.diskon_percent"
                                            class="ml-1 rounded bg-muted px-1 py-0.5 text-xs"
                                            >-{{ item.diskon_percent }}%</span
                                        >
                                    </td>
                                    <td class="px-3 py-2">
                                        <Input
                                            v-model.number="item.qty"
                                            type="number"
                                            min="1"
                                            class="h-8 w-20"
                                            @update:model-value="
                                                updateDeliverItemHarga(item)
                                            "
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="size-7 text-muted-foreground hover:text-destructive"
                                            @click="
                                                deliverItems.splice(index, 1)
                                            "
                                            ><Trash2 class="size-3.5"
                                        /></Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p
                            class="border-t bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
                        >
                            Total <Money :value="deliverTotalValue" /> •
                            {{ deliverTotalQty }} eks
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="deliver_notes">Catatan (opsional)</Label>
                        <Input
                            id="deliver_notes"
                            v-model="deliverNotes"
                            placeholder="Contoh: kiriman via ojek"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" @click="submitDeliver"
                            >Simpan Serah Terima</Button
                        >
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Lapor Laku -->
        <Dialog v-model:open="saleOpen">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Lapor Laku</DialogTitle>
                    <DialogDescription
                        >Barang laku otomatis menjadi piutang
                        mitra.</DialogDescription
                    >
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Mitra *</Label>
                        <Select
                            v-model="salePartnerId"
                            :disabled="!partners.length"
                            @update:model-value="onSalePartnerChange"
                        >
                            <SelectTrigger
                                ><SelectValue
                                    placeholder="Pilih mitra (tier Bazaf)"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="partner in partners"
                                    :key="partner.id"
                                    :value="partner.id"
                                    >{{ partner.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Kode Serah Terima (opsional)</Label>
                        <Select
                            v-model="saleDeliveryKode"
                            @update:model-value="onSaleKodeChange"
                        >
                            <SelectTrigger
                                ><SelectValue
                                    placeholder="Semua kode (akumulasi)"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >Semua kode (akumulasi)</SelectItem
                                >
                                <SelectItem
                                    v-for="opt in deliveryOptionsForSale"
                                    :key="opt.kode"
                                    :value="opt.kode"
                                    >{{ opt.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Pilih kode biar tidak akumulasi — harga diskon ikut
                            tier per kode
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sale_date">Tanggal *</Label>
                        <Input
                            id="sale_date"
                            v-model="saleDate"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Barang Laku *</Label>
                        <p
                            v-if="titipanLoading"
                            class="text-sm text-muted-foreground"
                        >
                            Memuat sisa titipan...
                        </p>
                        <p
                            v-else-if="!titipanList.length"
                            class="text-sm text-muted-foreground"
                        >
                            Tidak ada sisa titipan untuk mitra ini.
                        </p>
                        <div v-else class="overflow-hidden rounded-md border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b bg-muted/50 text-left text-xs font-medium text-muted-foreground"
                                    >
                                        <th class="px-3 py-2">Buku</th>
                                        <th class="w-16 px-3 py-2 text-right">
                                            Sisa
                                        </th>
                                        <th class="w-40 px-3 py-2">Harga</th>
                                        <th class="w-20 px-3 py-2">Laku</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="line in saleLines"
                                        :key="line.book_id"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="px-3 py-2 text-sm">
                                            {{ line.judul
                                            }}<span
                                                class="block text-xs text-muted-foreground"
                                                >Kode:
                                                {{ line.kode_sku ?? '—' }}</span
                                            >
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right text-sm tabular-nums"
                                        >
                                            {{ line.sisa }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col gap-0.5">
                                                <span
                                                    class="text-xs text-muted-foreground tabular-nums line-through"
                                                    ><Money
                                                        :value="
                                                            line.harga_asli
                                                        "
                                                /></span>
                                                <CurrencyInput
                                                    v-model="
                                                        salePrices[line.book_id]
                                                    "
                                                    :input-class="'h-8 w-full'"
                                                />
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <Input
                                                v-model.number="
                                                    saleQtys[line.book_id]
                                                "
                                                type="number"
                                                min="0"
                                                :max="line.sisa"
                                                class="h-8 w-20"
                                                @update:model-value="
                                                    onSaleQtyChange(
                                                        line.book_id,
                                                    )
                                                "
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p
                                class="border-t bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
                            >
                                Total piutang: <Money :value="saleGrandTotal" />
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sale_notes">Catatan (opsional)</Label>
                        <Input
                            id="sale_notes"
                            v-model="saleNotes"
                            placeholder="Contoh: laporan mingguan"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            :disabled="titipanLoading || !titipanList.length"
                            @click="submitSale"
                            >Simpan Laporan Laku</Button
                        >
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Retur Sisa -->
        <Dialog v-model:open="returnOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Retur Sisa Barang</DialogTitle>
                    <DialogDescription
                        >Barang sisa kembali — stok toko
                        bertambah.</DialogDescription
                    >
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Mitra *</Label>
                        <Select
                            v-model="returnPartnerId"
                            :disabled="!partners.length"
                            @update:model-value="onReturnPartnerChange"
                        >
                            <SelectTrigger
                                ><SelectValue
                                    placeholder="Pilih mitra (tier Bazaf)"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="partner in partners"
                                    :key="partner.id"
                                    :value="partner.id"
                                    >{{ partner.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Buku *</Label>
                        <Select
                            v-model="returnBookId"
                            :disabled="!titipanList.length"
                        >
                            <SelectTrigger
                                ><SelectValue
                                    :placeholder="
                                        titipanLoading
                                            ? 'Memuat...'
                                            : 'Pilih buku titipan'
                                    "
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in titipanList"
                                    :key="option.book_id"
                                    :value="option.book_id"
                                    >{{ option.judul }} (sisa
                                    {{ option.sisa }})</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="return_qty">Qty *</Label>
                            <Input
                                id="return_qty"
                                v-model.number="returnQty"
                                type="number"
                                min="1"
                                :max="selectedReturnOption?.sisa"
                                required
                            />
                            <p
                                v-if="selectedReturnOption"
                                class="text-xs text-muted-foreground"
                            >
                                Sisa titipan:
                                {{ selectedReturnOption.sisa }} eks
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="return_date">Tanggal *</Label>
                            <Input
                                id="return_date"
                                v-model="returnDate"
                                type="date"
                                required
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="return_notes">Catatan (opsional)</Label>
                        <Input
                            id="return_notes"
                            v-model="returnNotes"
                            placeholder="Contoh: sampai sedikit rusak"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            :disabled="titipanLoading || !titipanList.length"
                            @click="submitReturn"
                            >Simpan Retur</Button
                        >
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteDialog
            :open="deletingTarget !== null"
            @update:open="
                (open) => {
                    if (!open) deletingTarget = null;
                }
            "
            title="Hapus Transaksi?"
            :description="
                deletingTarget
                    ? `Transaksi '${deletingTarget.label}' akan dihapus dan efek stok/piutangnya dibatalkan.`
                    : ''
            "
            @confirm="executeDelete"
        />

        <Dialog v-model:open="warehouseConfirmOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ganti Gudang?</DialogTitle>
                    <DialogDescription
                        >List buku akan direset karena stok per gudang berbeda.
                        Apakah Anda yakin?</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelWarehouseChange"
                        >Batal</Button
                    >
                    <Button @click="confirmWarehouseChange">Ya, Ganti</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog Bayar Piutang -->
        <Dialog v-model:open="payOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Bayar Piutang</DialogTitle>
                    <DialogDescription
                        >Mitra {{ paySale?.customer?.name }} — sisa Rp
                        {{
                            (
                                (paySale?.receivable?.amount ?? 0) -
                                (paySale?.receivable?.paid_amount ?? 0)
                            ).toLocaleString('id-ID')
                        }}</DialogDescription
                    >
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Nominal *</Label>
                        <CurrencyInput
                            v-model="payAmount"
                            :input-class="'h-9 w-full'"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Metode *</Label>
                            <Select v-model="payMetode">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="transfer"
                                        >Transfer</SelectItem
                                    >
                                    <SelectItem value="cash">Cash</SelectItem>
                                    <SelectItem value="cod">COD</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Tanggal *</Label>
                            <Input v-model="payDate" type="date" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" @click="submitPay"
                            >Simpan Pembayaran</Button
                        >
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
