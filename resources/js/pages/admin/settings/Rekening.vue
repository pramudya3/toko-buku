<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Rekening Bank', href: '/admin/settings/rekening' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import BankAccountController from '@/actions/App/Http/Controllers/Admin/BankAccountController';
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

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
    is_active: boolean;
};

defineProps<{
    accounts: BankAccount[];
}>();

const dialogOpen = ref(false);
const editing = ref<BankAccount | null>(null);
const deleting = ref<BankAccount | null>(null);

const columns: DataTableColumn[] = [
    { key: 'bank_name', header: 'Bank', cellClass: 'font-medium' },
    { key: 'account_number', header: 'No. Rekening', cellClass: 'font-mono' },
    { key: 'account_holder', header: 'Atas Nama' },
    { key: 'status', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(account: BankAccount): void {
    editing.value = account;
    dialogOpen.value = true;
}

function toggleActive(account: BankAccount): void {
    router.put(
        BankAccountController.update(account.id).url,
        {
            bank_name: account.bank_name,
            account_number: account.account_number,
            account_holder: account.account_holder,
            is_active: account.is_active ? '0' : '1',
        },
        { preserveScroll: true },
    );
}

function confirmDelete(account: BankAccount): void {
    deleting.value = account;
}

function executeDelete(): void {
    if (!deleting.value) {
        return;
    }

    const account = deleting.value;
    deleting.value = null;

    router.delete(BankAccountController.destroy(account.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan — Rekening Bank" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Pengaturan Rekening
            </h1>
            <p class="text-sm text-muted-foreground">
                Rekening bank untuk menerima pembayaran pelanggan
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-medium">Rekening Bank</h2>
                <p class="text-sm text-muted-foreground">
                    Tampil sebagai tujuan transfer di checkout
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Tambah Rekening
            </Button>
        </div>

        <DataTable
            :data="accounts"
            :columns="columns"
            empty-title="Belum ada rekening"
            empty-description="Tambahkan rekening bank untuk tujuan transfer pembeli."
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
                    {{ editing ? 'Edit Rekening' : 'Tambah Rekening' }}
                </DialogTitle>
                <DialogDescription>
                    Rekening bank tujuan transfer pembeli.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="editing"
                :key="editing.id"
                v-bind="BankAccountController.update.form(editing.id)"
                class="grid gap-4"
                v-slot="{ processing }"
                @success="dialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="bank_name">Bank *</Label>
                    <Input
                        id="bank_name"
                        name="bank_name"
                        required
                        :default-value="editing.bank_name"
                        placeholder="BCA"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="account_number">No. Rekening *</Label>
                    <Input
                        id="account_number"
                        name="account_number"
                        required
                        :default-value="editing.account_number"
                        placeholder="1234567890"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="account_holder">Atas Nama *</Label>
                    <Input
                        id="account_holder"
                        name="account_holder"
                        required
                        :default-value="editing.account_holder"
                        placeholder="Nama Pemilik Rekening"
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
                    Rekening aktif
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
                v-bind="BankAccountController.store.form()"
                class="grid gap-4"
                v-slot="{ processing }"
                @success="dialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="bank_name">Bank *</Label>
                    <Input
                        id="bank_name"
                        name="bank_name"
                        required
                        placeholder="BCA"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="account_number">No. Rekening *</Label>
                    <Input
                        id="account_number"
                        name="account_number"
                        required
                        placeholder="1234567890"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="account_holder">Atas Nama *</Label>
                    <Input
                        id="account_holder"
                        name="account_holder"
                        required
                        placeholder="Nama Pemilik Rekening"
                    />
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
        title="Hapus Rekening?"
        :description="
            deleting
                ? `Rekening ${deleting.bank_name} ${deleting.account_number} akan dihapus.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>
