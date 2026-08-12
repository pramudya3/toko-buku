<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Pembayaran', href: '/admin/settings/pembayaran' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import PaymentMethodController from '@/actions/App/Http/Controllers/Admin/PaymentMethodController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

type PaymentMethodItem = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
};

defineProps<{
    paymentMethods: PaymentMethodItem[];
}>();

const dialogOpen = ref(false);
const editing = ref<PaymentMethodItem | null>(null);
const deleting = ref<PaymentMethodItem | null>(null);

const columns: DataTableColumn[] = [
    { key: 'name', header: 'Nama', cellClass: 'font-medium' },
    { key: 'status', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(method: PaymentMethodItem): void {
    editing.value = method;
    dialogOpen.value = true;
}

function confirmDelete(method: PaymentMethodItem): void {
    deleting.value = method;
}

function executeDelete(): void {
    if (!deleting.value) {
        return;
    }

    const method = deleting.value;
    deleting.value = null;

    router.delete(PaymentMethodController.destroy(method.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan — Metode Pembayaran" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Pengaturan Pembayaran
            </h1>
            <p class="text-sm text-muted-foreground">
                Metode pembayaran yang tersedia di checkout
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-medium">Metode Pembayaran</h2>
                <p class="text-sm text-muted-foreground">
                    Metode aktif tampil di checkout storefront dan form pesanan
                    admin
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Tambah Metode
            </Button>
        </div>

        <DataTable
            :data="paymentMethods"
            :columns="columns"
            empty-title="Belum ada metode pembayaran"
            empty-description="Tambahkan metode yang diterima toko."
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
                    {{ editing ? 'Edit Metode' : 'Tambah Metode' }}
                </DialogTitle>
                <DialogDescription>
                    Metode pembayaran yang diterima toko.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="editing"
                :key="editing.id"
                v-bind="PaymentMethodController.update.form(editing.id)"
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
                        placeholder="QRIS"
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
                    Metode aktif
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
                v-bind="PaymentMethodController.store.form()"
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
                        placeholder="qris (huruf kecil, tanpa spasi)"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="name">Nama *</Label>
                    <Input id="name" name="name" required placeholder="QRIS" />
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
        title="Hapus Metode Pembayaran?"
        :description="
            deleting
                ? `Metode ${deleting.name} (${deleting.code}) akan dihapus.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>
