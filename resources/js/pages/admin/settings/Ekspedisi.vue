<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Ekspedisi', href: '/admin/settings/ekspedisi' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { ChevronDown, Plus } from '@lucide/vue';
import { ref } from 'vue';
import CourierController from '@/actions/App/Http/Controllers/Admin/CourierController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Switch } from '@/components/ui/switch';

type Courier = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
};

defineProps<{
    couriers: Courier[];
}>();

const dialogOpen = ref(false);
const editing = ref<Courier | null>(null);
const deleting = ref<Courier | null>(null);
const selectedIds = ref<Set<string>>(new Set());

const columns: DataTableColumn[] = [
    { key: 'name', header: 'Nama', cellClass: 'font-medium' },
    { key: 'status', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(courier: Courier): void {
    editing.value = courier;
    dialogOpen.value = true;
}

function toggleActive(courier: Courier): void {
    router.put(
        CourierController.update(courier.id).url,
        {
            name: courier.name,
            is_active: courier.is_active ? '0' : '1',
        },
        { preserveScroll: true },
    );
}

function bulkSetActive(isActive: boolean): void {
    if (selectedIds.value.size === 0) {
        return;
    }

    router.put(
        CourierController.bulkUpdate().url,
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

function confirmDelete(courier: Courier): void {
    deleting.value = courier;
}

function executeDelete(): void {
    if (!deleting.value) {
        return;
    }

    const courier = deleting.value;
    deleting.value = null;

    router.delete(CourierController.destroy(courier.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan — Ekspedisi" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Pengaturan Ekspedisi
            </h1>
            <p class="text-sm text-muted-foreground">
                Ekspedisi aktif tampil sebagai pilihan saat input ongkir di
                pesanan
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-4">
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
                Tambah Ekspedisi
            </Button>
        </div>

        <DataTable
            :data="couriers"
            :columns="columns"
            selectable
            v-model:selected-ids="selectedIds"
            empty-title="Belum ada ekspedisi"
            empty-description="Tambahkan ekspedisi yang tersedia untuk pengiriman."
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
                        {
                            label: 'Edit',
                            onClick: () => openEdit(row),
                        },
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
                <DialogTitle>
                    {{ editing ? 'Edit Ekspedisi' : 'Tambah Ekspedisi' }}
                </DialogTitle>
                <DialogDescription>
                    Ekspedisi yang tersedia untuk pengiriman pesanan.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="editing"
                :key="editing.id"
                v-bind="CourierController.update.form(editing.id)"
                class="grid gap-4"
                v-slot="{ processing }"
                @success="dialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="name">Nama *</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        :default-value="editing.name"
                        placeholder="JNE"
                    />
                </div>
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <input
                        type="hidden"
                        name="is_active"
                        :value="editing.is_active ? '1' : '0'"
                    />
                    <Switch v-model="editing.is_active" />
                    Ekspedisi aktif
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Simpan' }}
                    </Button>
                </DialogFooter>
            </Form>

            <Form
                v-else
                v-bind="CourierController.store.form()"
                class="grid gap-4"
                v-slot="{ processing }"
                @success="dialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="code">Kode *</Label>
                    <Input
                        id="code"
                        name="code"
                        required
                        pattern="[a-z0-9_-]+"
                        placeholder="jne (huruf kecil, tanpa spasi)"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="name">Nama *</Label>
                    <Input id="name" name="name" required placeholder="JNE" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Tambah' }}
                    </Button>
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
        title="Hapus Ekspedisi?"
        :description="
            deleting
                ? `Ekspedisi ${deleting.name} (${deleting.code}) akan dihapus.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>
