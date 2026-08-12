<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Kas', href: '/admin/kas' },
        ],
    },
});

import { Form, Head } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CashFlowController from '@/actions/App/Http/Controllers/Admin/CashFlowController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { detail as detailRoute } from '@/routes/admin/kas';

type MonthRow = {
    key: string;
    label: string;
    count: number;
    masuk: number;
    keluar: number;
};

defineProps<{
    months: MonthRow[];
    summary: { masuk: number; keluar: number };
}>();

const columns: DataTableColumn[] = [
    { key: 'label', header: 'Bulan', cellClass: 'font-medium' },
    {
        key: 'masuk',
        header: 'Total Uang Masuk',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'keluar',
        header: 'Total Uang Keluar',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

// --- Dialog tambah bulan ---
const dialogOpen = ref(false);
const monthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const currentYear = new Date().getFullYear();
// 7 opsi: tahun berjalan ± (terbaru dulu).
const yearOptions = Array.from(
    { length: 7 },
    (_, i) => currentYear + 1 - i,
);

const bulan = ref('01');
const tahun = ref(String(currentYear));

const bulanValue = computed(() => `${tahun.value}-${bulan.value}`);

function openDialog() {
    bulan.value = '01';
    tahun.value = String(currentYear);
    dialogOpen.value = true;
}

function onFormError() {
    toast.error('Gagal membuka bulan — periksa kembali isian.');
}
</script>

<template>
    <Head title="Pencatatan Kas" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Pencatatan Kas
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rekap uang masuk & uang keluar per bulan
                </p>
            </div>
            <Button @click="openDialog">
                <Plus class="size-4" />
                Tambah Bulan
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <ArrowDownCircle class="size-5 shrink-0 text-green-600" />
                    <div>
                        <p class="text-muted-foreground">Total Uang Masuk</p>
                        <p class="text-lg font-semibold">
                            <Money :value="summary.masuk" />
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <ArrowUpCircle class="size-5 shrink-0 text-destructive" />
                    <div>
                        <p class="text-muted-foreground">Total Uang Keluar</p>
                        <p class="text-lg font-semibold">
                            <Money :value="summary.keluar" />
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <div class="size-5 shrink-0" />
                    <div>
                        <p class="text-muted-foreground">Saldo Bersih</p>
                        <p
                            class="text-lg font-semibold"
                            :class="
                                summary.masuk - summary.keluar >= 0
                                    ? 'text-green-600'
                                    : 'text-destructive'
                            "
                        >
                            <Money :value="summary.masuk - summary.keluar" />
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <DataTable
            :data="months"
            :columns="columns"
            key-field="key"
            empty-title="Belum ada pencatatan kas"
            empty-description="Pencatatan muncul setelah ada transaksi penjualan, entri kas manual, atau bulan dibuka manual."
        >
            <template #cell-label="{ row }">
                {{ row.label }}
                <span class="ml-2 text-xs text-muted-foreground">
                    {{ row.count }} entri
                </span>
            </template>
            <template #cell-masuk="{ row }">
                <span class="text-green-600">
                    <Money :value="row.masuk" />
                </span>
            </template>
            <template #cell-keluar="{ row }">
                <span class="text-destructive">
                    <Money :value="row.keluar" />
                </span>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Lihat Detail',
                            href: detailRoute(row.key).url,
                        },
                    ]"
                />
            </template>
        </DataTable>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-xs">
            <DialogHeader>
                <DialogTitle>Tambah Bulan</DialogTitle>
                <DialogDescription>
                    Buka bulan baru untuk pencatatan kas — bulan yang sudah
                    punya entri tidak bisa dibuka lagi.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="CashFlowController.storeMonth.form()"
                class="grid gap-4"
                v-slot="{ processing }"
                @error="onFormError"
                @success="dialogOpen = false"
            >
                <input type="hidden" name="bulan" :value="bulanValue" />

                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="bulan">Bulan *</Label>
                        <Select v-model="bulan">
                            <SelectTrigger id="bulan">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(name, index) in monthNames"
                                    :key="name"
                                    :value="String(index + 1).padStart(2, '0')"
                                >
                                    {{ name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="tahun">Tahun *</Label>
                        <Select v-model="tahun">
                            <SelectTrigger id="tahun">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="year in yearOptions"
                                    :key="year"
                                    :value="String(year)"
                                >
                                    {{ year }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Tambah Bulan' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
