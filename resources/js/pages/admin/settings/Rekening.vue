<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import BankAccountController from '@/actions/App/Http/Controllers/Admin/BankAccountController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

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

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(account: BankAccount): void {
    editing.value = account;
    dialogOpen.value = true;
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

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Bank</TableHead>
                            <TableHead>No. Rekening</TableHead>
                            <TableHead>Atas Nama</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">
                                <span class="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="account in accounts" :key="account.id">
                            <TableCell class="font-medium">
                                {{ account.bank_name }}
                            </TableCell>
                            <TableCell class="font-mono">
                                {{ account.account_number }}
                            </TableCell>
                            <TableCell>{{ account.account_holder }}</TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :class="
                                        account.is_active
                                            ? 'border-green-200 bg-green-100 text-green-800'
                                            : 'border-gray-200 bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{
                                        account.is_active ? 'Aktif' : 'Nonaktif'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            onClick: () => openEdit(account),
                                        },
                                        {
                                            label: 'Hapus',
                                            variant: 'destructive',
                                            onClick: () =>
                                                confirmDelete(account),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!accounts.length"
                    title="Belum ada rekening"
                    description="Tambahkan rekening bank untuk tujuan transfer pembeli."
                />
            </CardContent>
        </Card>
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
