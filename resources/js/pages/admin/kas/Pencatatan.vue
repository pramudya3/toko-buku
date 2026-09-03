<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Kas', href: '/admin/kas' },
        ],
    },
});

import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Plus,
    SquareArrowOutUpRight,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CashFlowController from '@/actions/App/Http/Controllers/Admin/CashFlowController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { todayWIB } from '@/lib/date';
import { detail as detailRoute } from '@/routes/admin/kas';

type MonthRow = {
    key: string;
    label: string;
    count: number;
    masuk: number;
    keluar: number;
};

const props = defineProps<{
    months: MonthRow[];
    summary: { masuk: number; keluar: number };
}>();

const columns: DataTableColumn[] = [
    { key: 'label', header: 'Periode', cellClass: 'font-medium' },
    {
        key: 'masuk',
        header: 'Pemasukan',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'keluar',
        header: 'Pengeluaran',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'aksi', header: '', cellClass: 'text-center w-[60px]' },
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

function getCurrentWIB(): { year: string; month: string } {
    const [y, m] = todayWIB().split('-');

    return { year: y, month: m };
}

const initWIB = getCurrentWIB();
// 7 opsi: tahun berjalan ± (terbaru dulu) berbasis WIB.
const yearOptions = Array.from(
    { length: 7 },
    (_, i) => Number(initWIB.year) + 1 - i,
);

const bulan = ref(initWIB.month);
const tahun = ref(initWIB.year);

const bulanValue = computed(() => `${tahun.value}-${bulan.value}`);

const existingKeys = computed(() => new Set(props.months.map((m) => m.key)));
const isDuplicate = computed(() => existingKeys.value.has(bulanValue.value));

function openDialog() {
    const { year, month } = getCurrentWIB();
    tahun.value = year;
    bulan.value = month;
    dialogOpen.value = true;
}

function onFormError() {
    toast.error('Gagal membuka periode — mohon periksa kembali isian.');
}
</script>

<template>
    <Head title="Pencatatan Kas" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Pencatatan Kas
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rekapitulasi pemasukan dan pengeluaran kas per periode
                    bulanan.
                </p>
            </div>
            <Button @click="openDialog">
                <Plus class="size-4" />
                Tambah Periode
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <ArrowDownCircle class="size-5 shrink-0 text-green-600" />
                    <div>
                        <p class="text-muted-foreground">Pemasukan</p>
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
                        <p class="text-muted-foreground">Pengeluaran</p>
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
            empty-title="Belum Ada Pencatatan Kas"
            empty-description="Belum terdapat pencatatan pada periode ini. Silakan buka periode baru atau lakukan pencatatan manual."
        >
            <template #cell-label="{ row }">
                {{ row.label }}
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
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7"
                                as-child
                            >
                                <Link
                                    :href="
                                        detailRoute((row as MonthRow).key).url
                                    "
                                    aria-label="Lihat detail periode"
                                >
                                    <SquareArrowOutUpRight class="size-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Lihat Detail Periode</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </template>
        </DataTable>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-xs">
            <DialogHeader>
                <DialogTitle>Tambah Periode</DialogTitle>
                <DialogDescription>
                    Buka periode pencatatan baru. Periode yang telah memiliki
                    pencatatan tidak dapat dibuka kembali.
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

                <p
                    v-if="isDuplicate"
                    class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    Periode {{ bulanValue }} telah tersedia. Silakan pilih
                    periode lain.
                </p>

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
                    <Button type="submit" :disabled="processing || isDuplicate">
                        {{ processing ? 'Menyimpan…' : 'Tambah Periode' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
