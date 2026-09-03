<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Alasan Retur', href: '/admin/settings/alasan-retur' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { ChevronDown, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { computed } from 'vue';
import SupplierReturnReasonController from '@/actions/App/Http/Controllers/Admin/SupplierReturnReasonController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Reason = {
    id: string;
    code: string;
    name: string;
    category: string;
    type: string;
    is_active: boolean;
    sort_order: number;
};

const props = defineProps<{
    reasons: Reason[];
}>();

const initialType = new URLSearchParams(window.location.search).get('type') as
    'supplier' | 'sales' | 'adjustment' | null;
const activeTypeTab = ref<'supplier' | 'sales' | 'adjustment'>(
    initialType && ['supplier', 'sales', 'adjustment'].includes(initialType)
        ? (initialType as 'supplier' | 'sales' | 'adjustment')
        : 'supplier',
);
const typeLabels: Record<string, string> = {
    supplier: 'Retur Supplier',
    sales: 'Retur Penjualan',
    adjustment: 'Adjustment',
};
const filteredReasons = computed(() =>
    props.reasons.filter((r) => r.type === activeTypeTab.value),
);

const dialogOpen = ref(false);
const editing = ref<Reason | null>(null);
const deleting = ref<Reason | null>(null);
const selectedIds = ref<Set<string>>(new Set());

const columns: DataTableColumn[] = [
    { key: 'name', header: 'Nama Alasan', cellClass: 'font-medium' },
    { key: 'code', header: 'Kode', cellClass: 'font-mono text-xs' },
    { key: 'status', header: 'Aktif', cellClass: 'w-20 text-center' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(reason: Reason): void {
    editing.value = reason;
    dialogOpen.value = true;
}

function toggleActive(reason: Reason): void {
    router.put(
        SupplierReturnReasonController.toggle(reason.id).url,
        {},
        { preserveScroll: true },
    );
}

function bulkSetActive(isActive: boolean): void {
    if (selectedIds.value.size === 0) {
        return;
    }

    router.put(
        SupplierReturnReasonController.bulkUpdate().url,
        {
            ids: [...selectedIds.value],
            is_active: isActive ? '1' : '0',
        },
        {
            preserveScroll: true,
            onSuccess: () => selectedIds.value.clear(),
        },
    );
}

function confirmDelete(reason: Reason): void {
    deleting.value = reason;
}

function executeDelete(): void {
    if (!deleting.value) {
        return;
    }

    const reason = deleting.value;
    deleting.value = null;
    router.delete(SupplierReturnReasonController.destroy(reason.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan — Alasan Retur" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Alasan Retur Supplier
            </h1>
            <p class="text-sm text-muted-foreground">
                Template alasan retur — dipakai sebagai pilihan di form Retur
                Supplier agar konsisten
            </p>
        </div>

        <!-- Tabs untuk tipe -->
        <div class="flex gap-1 border-b">
            <button
                v-for="t in ['supplier', 'sales', 'adjustment']"
                :key="t"
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTypeTab === t
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTypeTab = t as typeof activeTypeTab"
            >
                {{ typeLabels[t] }}
                <span class="ml-1 rounded-full bg-muted px-1.5 py-0.5 text-xs">
                    {{ reasons.filter((r) => r.type === t).length }}
                </span>
            </button>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <DropdownMenu v-if="selectedIds.size > 0">
                <DropdownMenuTrigger as-child>
                    <Button variant="outline">
                        Ubah Status ({{ selectedIds.size }})
                        <ChevronDown class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem @click="bulkSetActive(true)">
                        Aktifkan ({{ selectedIds.size }})
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="bulkSetActive(false)">
                        Nonaktifkan ({{ selectedIds.size }})
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Tambah Alasan
            </Button>
        </div>

        <DataTable
            :data="filteredReasons"
            :columns="columns"
            selectable
            v-model:selected-ids="selectedIds"
            empty-title="Belum ada alasan"
            empty-description="Tambahkan template alasan untuk tipe ini."
        >
            <template #cell-status="{ row }">
                <Badge
                    variant="outline"
                    :class="
                        row.is_active
                            ? 'border-green-200 bg-green-100 text-green-800'
                            : 'border-gray-200 bg-gray-100 text-gray-600'
                    "
                >
                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                </Badge>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: row.is_active ? 'Nonaktifkan' : 'Aktifkan',
                            onClick: () => toggleActive(row),
                        },
                        { label: 'Edit', onClick: () => openEdit(row) },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () => confirmDelete(row),
                        },
                    ]"
                />
            </template>
        </DataTable>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Edit Alasan' : 'Tambah Alasan'
                }}</DialogTitle>
                <DialogDescription
                    >Template alasan yang akan muncul di form
                    Retur.</DialogDescription
                >
            </DialogHeader>

            <Form
                v-if="editing"
                :key="editing.id"
                v-bind="SupplierReturnReasonController.update.form(editing.id)"
                class="grid gap-4"
                v-slot="{ processing }"
                @success="dialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="edit_code">Kode *</Label>
                    <Input
                        id="edit_code"
                        name="code"
                        required
                        :default-value="editing.code"
                        pattern="[a-z0-9-]+"
                        placeholder="cacat-halaman-rusak"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="edit_name">Nama *</Label>
                    <Input
                        id="edit_name"
                        name="name"
                        required
                        :default-value="editing.name"
                        placeholder="Halaman Rusak / Sobek"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Tipe *</Label>
                    <Select name="type" :default-value="editing.type">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="supplier"
                                >Retur Supplier</SelectItem
                            >
                            <SelectItem value="sales"
                                >Retur Penjualan</SelectItem
                            >
                            <SelectItem value="adjustment"
                                >Adjustment</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>Kategori *</Label>
                    <Select name="category" :default-value="editing.category">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="cacat"
                                >Cacat Produksi</SelectItem
                            >
                            <SelectItem value="salah_kirim"
                                >Salah Kirim</SelectItem
                            >
                            <SelectItem value="umum">Umum</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="editing.is_active" />
                    Aktif
                    <input
                        type="hidden"
                        name="is_active"
                        :value="editing.is_active ? '1' : '0'"
                    />
                </label>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                        >Batal</Button
                    >
                    <Button type="submit" :disabled="processing">{{
                        processing ? 'Menyimpan...' : 'Simpan'
                    }}</Button>
                </DialogFooter>
            </Form>

            <Form
                v-else
                v-bind="SupplierReturnReasonController.store.form()"
                class="grid gap-4"
                v-slot="{ processing }"
                @success="dialogOpen = false"
            >
                <input type="hidden" name="type" :value="activeTypeTab" />
                <div class="grid gap-2">
                    <Label for="code">Kode *</Label>
                    <Input
                        id="code"
                        name="code"
                        required
                        pattern="[a-z0-9-]+"
                        placeholder="cacat-baru"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="name">Nama *</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="Cacat Baru"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Kategori *</Label>
                    <Select name="category" default-value="umum">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="cacat"
                                >Cacat Produksi</SelectItem
                            >
                            <SelectItem value="salah_kirim"
                                >Salah Kirim</SelectItem
                            >
                            <SelectItem value="umum">Umum</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <p class="text-xs text-muted-foreground">
                    Tipe otomatis:
                    <span class="font-medium">{{
                        typeLabels[activeTypeTab]
                    }}</span>
                    (sesuai tab aktif)
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                        >Batal</Button
                    >
                    <Button type="submit" :disabled="processing">{{
                        processing ? 'Menyimpan...' : 'Tambah'
                    }}</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
        :open="!!deleting"
        @update:open="
            (open) => {
                if (!open) deleting = null;
            }
        "
        title="Hapus Alasan?"
        :description="
            deleting
                ? `Alasan '${deleting.name}' (${deleting.code}) akan dihapus.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>
