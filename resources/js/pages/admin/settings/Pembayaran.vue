<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import PaymentMethodController from '@/actions/App/Http/Controllers/Admin/PaymentMethodController';
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

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">
                                <span class="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="method in paymentMethods"
                            :key="method.id"
                        >
                            <TableCell class="font-medium">
                                {{ method.name }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :class="
                                        method.is_active
                                            ? 'border-green-200 bg-green-100 text-green-800'
                                            : 'border-gray-200 bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{
                                        method.is_active ? 'Aktif' : 'Nonaktif'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            onClick: () => openEdit(method),
                                        },
                                        {
                                            label: 'Hapus',
                                            variant: 'destructive',
                                            onClick: () =>
                                                confirmDelete(method),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!paymentMethods.length"
                    title="Belum ada metode pembayaran"
                    description="Tambahkan metode yang diterima toko."
                />
            </CardContent>
        </Card>
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
