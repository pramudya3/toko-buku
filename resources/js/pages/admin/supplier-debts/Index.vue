<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Hutang Supplier', href: '/admin/supplier-debts' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { Banknote } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
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
import { todayWIB } from '@/lib/date';

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
}>();

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

    <div class="flex flex-col gap-4 p-4 md:p-6">
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

        <Card>
            <CardHeader>
                <CardTitle class="text-base font-medium"
                    >Faktur Belum Lunas</CardTitle
                >
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Ref Code</TableHead>
                            <TableHead>Tanggal</TableHead>
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
                            <TableCell class="font-mono text-xs">
                                {{ invoice.ref_code }}
                            </TableCell>
                            <TableCell class="tabular-nums">
                                {{ invoice.purchase_date }}
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

        <Card>
            <CardHeader>
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

        <Card>
            <CardHeader>
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
