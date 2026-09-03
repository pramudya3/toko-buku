<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Hutang Supplier', href: '/admin/supplier-debts' },
        ],
    },
});

import { Head, router, usePage } from '@inertiajs/vue3';
import { Banknote } from '@lucide/vue';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import SupplierDebtController from '@/actions/App/Http/Controllers/Admin/SupplierDebtController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateID, todayWIB } from '@/lib/date';

type SupplierSummary = {
    id: string;
    nama: string;
    purchase_total: number;
    return_total: number;
    payment_total: number;
    saldo_hutang: number;
};

type Invoice = {
    id: string;
    ref_code: string;
    purchase_date: string;
    supplier_id: string;
    supplier_nama: string;
    total: number;
    paid: number;
    sisa: number;
};

type Payment = {
    id: string;
    payment_date: string;
    amount: number;
    notes: string | null;
    supplier: { id: string; nama: string };
    purchase: { id: string; ref_code: string } | null;
};

const props = defineProps<{
    suppliers: SupplierSummary[];
    invoices: Invoice[];
    payments: Payment[];
    filters?: { supplier_id?: string; purchase_id?: string };
}>();

const activeTab = ref<'faktur' | 'supplier' | 'riwayat'>('faktur');

const totalHutang = computed(() =>
    props.suppliers.reduce((sum, s) => sum + s.saldo_hutang, 0),
);
const totalFaktur = computed(() => props.invoices.length);
const totalSupplierHutang = computed(() => props.suppliers.length);

// ── Dialog pembayaran ─────────────────────────────────────────────
const paymentOpen = ref(false);
const paymentForm = reactive<{
    supplier_id: string;
    purchase_id: string;
    amount: number;
    payment_date: string;
    notes: string;
}>({
    supplier_id: '',
    purchase_id: '',
    amount: 0,
    payment_date: todayWIB(),
    notes: '',
});

const supplierInvoices = computed(() =>
    props.invoices.filter(
        (invoice) => invoice.supplier_id === paymentForm.supplier_id,
    ),
);

function openPayment(invoice?: Invoice, supplier?: SupplierSummary) {
    if (invoice) {
        paymentForm.supplier_id = String(invoice.supplier_id);
        paymentForm.purchase_id = String(invoice.id);
        paymentForm.amount = Number(invoice.sisa);
    } else if (supplier) {
        paymentForm.supplier_id = String(supplier.id);
        paymentForm.purchase_id = '';
        paymentForm.amount = Number(supplier.saldo_hutang);
    } else {
        paymentForm.supplier_id = '';
        paymentForm.purchase_id = '';
        paymentForm.amount = 0;
    }

    paymentForm.payment_date = todayWIB();
    paymentForm.notes = '';
    paymentOpen.value = true;
}

function onPurchaseChange() {
    const invoice = supplierInvoices.value.find(
        (item) => item.id === paymentForm.purchase_id,
    );

    paymentForm.amount = invoice ? Number(invoice.sisa) : 0;
}

onMounted(() => {
    const queryPurchaseId =
        (usePage().props as unknown as { filters?: { purchase_id?: string } })
            .filters?.purchase_id ??
        new URLSearchParams(window.location.search).get('purchase_id');
    const querySupplierId =
        (usePage().props as unknown as { filters?: { supplier_id?: string } })
            .filters?.supplier_id ??
        new URLSearchParams(window.location.search).get('supplier_id');

    if (queryPurchaseId) {
        const invoice = props.invoices.find(
            (inv) => String(inv.id) === String(queryPurchaseId),
        );

        if (invoice) {
            openPayment(invoice);

            return;
        }
    }

    if (querySupplierId) {
        const supplier = props.suppliers.find(
            (s) => String(s.id) === String(querySupplierId),
        );

        if (supplier) {
            const inv = props.invoices.find(
                (i) => String(i.supplier_id) === String(querySupplierId),
            );

            if (inv) {
                openPayment(inv);
            } else {
                openPayment(undefined, supplier);
            }
        }
    }
});

function submitPayment() {
    router.post(
        SupplierDebtController.store.url(),
        {
            supplier_id: paymentForm.supplier_id,
            purchase_id:
                paymentForm.purchase_id === 'none'
                    ? undefined
                    : paymentForm.purchase_id,
            amount: paymentForm.amount,
            payment_date: paymentForm.payment_date,
            notes: paymentForm.notes,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                paymentOpen.value = false;
            },
            onError: () => {
                toast.error(
                    'Gagal menyimpan pembayaran — periksa kembali isian.',
                );
            },
        },
    );
}
</script>

<template>
    <Head title="Hutang Supplier" />

    <div class="flex flex-col gap-6 p-6 md:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Hutang Supplier
                </h1>
                <p class="text-sm text-muted-foreground">
                    Hutang pembelian ke supplier beserta pembayarannya
                </p>
            </div>
            <Button @click="openPayment()">
                <Banknote class="size-4" />
                Catat Pembayaran
            </Button>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">Total Hutang</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        <Money :value="totalHutang" />
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ totalSupplierHutang }} supplier ·
                        {{ totalFaktur }} faktur belum lunas
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">
                        Faktur Belum Lunas
                    </p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ totalFaktur }}
                    </p>
                    <p class="text-xs text-muted-foreground">Perlu dibayar</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">
                        Pembayaran Terakhir
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ payments[0] ? payments[0].payment_date : '—' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            payments[0]
                                ? `Rp ${payments[0].amount.toLocaleString('id-ID')}`
                                : 'Belum ada'
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 border-b">
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'faktur'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'faktur'"
            >
                Faktur Belum Lunas
                <span
                    v-if="invoices.length"
                    class="ml-1 rounded-full bg-muted px-1.5 py-0.5 text-xs"
                    >{{ invoices.length }}</span
                >
            </button>
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'supplier'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'supplier'"
            >
                Per Supplier
            </button>
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'riwayat'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'riwayat'"
            >
                Riwayat
            </button>
        </div>

        <Card v-show="activeTab === 'faktur'">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium"
                    >Faktur Belum Lunas</CardTitle
                >
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="whitespace-nowrap"
                                >Tanggal</TableHead
                            >
                            <TableHead>Ref Code</TableHead>
                            <TableHead>Supplier</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Dibayar</TableHead>
                            <TableHead class="text-right">Sisa</TableHead>
                            <TableHead class="text-right">
                                <span class="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="invoice in invoices" :key="invoice.id">
                            <TableCell class="whitespace-nowrap tabular-nums">
                                {{ formatDateID(invoice.purchase_date) }}
                            </TableCell>
                            <TableCell class="font-mono text-xs">
                                {{ invoice.ref_code }}
                            </TableCell>
                            <TableCell>
                                {{ invoice.supplier_nama }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Money :value="invoice.total" />
                            </TableCell>
                            <TableCell class="text-right">
                                <Money :value="invoice.paid" />
                            </TableCell>
                            <TableCell class="text-right">
                                <span class="font-semibold text-destructive">
                                    <Money :value="invoice.sisa" />
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openPayment(invoice)"
                                >
                                    <Banknote class="size-3.5" />
                                    Bayar
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!invoices.length"
                    title="Tidak ada faktur belum lunas"
                    description="Semua faktur pembelian sudah lunas. 🎉"
                />
            </CardContent>
        </Card>

        <Card v-show="activeTab === 'supplier'">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium"
                    >Ringkasan per Supplier</CardTitle
                >
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Supplier</TableHead>
                            <TableHead class="text-right">Total Beli</TableHead>
                            <TableHead class="text-right">Retur</TableHead>
                            <TableHead class="text-right">Bayar</TableHead>
                            <TableHead class="text-right"
                                >Sisa Hutang</TableHead
                            >
                            <TableHead class="text-right">
                                <span class="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                        >
                            <TableCell class="font-medium">
                                {{ supplier.nama }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Money :value="supplier.purchase_total" />
                            </TableCell>
                            <TableCell class="text-right">
                                <Money :value="supplier.return_total" />
                            </TableCell>
                            <TableCell class="text-right">
                                <Money :value="supplier.payment_total" />
                            </TableCell>
                            <TableCell class="text-right">
                                <span class="font-semibold text-destructive">
                                    <Money :value="supplier.saldo_hutang" />
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openPayment(undefined, supplier)"
                                >
                                    <Banknote class="size-3.5" />
                                    Bayar
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!suppliers.length"
                    title="Tidak ada hutang"
                    description="Belum ada saldo hutang ke supplier."
                />
            </CardContent>
        </Card>

        <Card v-show="activeTab === 'riwayat'">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium"
                    >Pembayaran Terakhir</CardTitle
                >
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Tanggal</TableHead>
                            <TableHead>Supplier</TableHead>
                            <TableHead>Faktur</TableHead>
                            <TableHead class="text-right">Jumlah</TableHead>
                            <TableHead>Catatan</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="payment in payments" :key="payment.id">
                            <TableCell class="tabular-nums">
                                {{ payment.payment_date }}
                            </TableCell>
                            <TableCell>
                                {{ payment.supplier.nama }}
                            </TableCell>
                            <TableCell class="font-mono text-xs">
                                {{ payment.purchase?.ref_code ?? '—' }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Money :value="payment.amount" />
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ payment.notes ?? '—' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!payments.length"
                    title="Belum ada pembayaran"
                    description="Riwayat pembayaran hutang akan muncul di sini."
                />
            </CardContent>
        </Card>
    </div>

    <!-- Dialog pembayaran -->
    <Dialog v-model:open="paymentOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Catat Pembayaran Hutang</DialogTitle>
                <DialogDescription>
                    Pilih supplier & faktur, lalu isi jumlah bayar.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>Supplier *</Label>
                    <Select v-model="paymentForm.supplier_id">
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
                    <Label>Faktur</Label>
                    <Select
                        v-model="paymentForm.purchase_id"
                        :disabled="!paymentForm.supplier_id"
                        @update:model-value="onPurchaseChange"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Bayar tanpa faktur" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Tanpa faktur</SelectItem>
                            <SelectItem
                                v-for="invoice in supplierInvoices"
                                :key="invoice.id"
                                :value="String(invoice.id)"
                            >
                                {{ invoice.ref_code }} — sisa
                                {{ invoice.sisa.toLocaleString('id-ID') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="amount">Jumlah Bayar (Rp) *</Label>
                        <CurrencyInput
                            id="amount"
                            v-model="paymentForm.amount"
                            placeholder="0"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="payment_date">Tanggal *</Label>
                        <Input
                            id="payment_date"
                            v-model="paymentForm.payment_date"
                            type="date"
                        />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="payment-notes">Catatan</Label>
                    <Input
                        id="payment-notes"
                        v-model="paymentForm.notes"
                        placeholder="mis. Transfer BCA"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="paymentOpen = false"
                    >Batal</Button
                >
                <Button @click="submitPayment">
                    <Banknote class="size-4" />
                    Simpan Pembayaran
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
