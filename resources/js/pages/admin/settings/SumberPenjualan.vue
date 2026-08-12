<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Sumber Penjualan' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import SalesChannelController from '@/actions/App/Http/Controllers/Admin/SalesChannelController';
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

type SalesChannelItem = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
};

defineProps<{
    salesChannels: SalesChannelItem[];
}>();

const dialogOpen = ref(false);
const editing = ref<SalesChannelItem | null>(null);
const deleting = ref<SalesChannelItem | null>(null);

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(channel: SalesChannelItem): void {
    editing.value = channel;
    dialogOpen.value = true;
}

function confirmDelete(channel: SalesChannelItem): void {
    deleting.value = channel;
}

function executeDelete(): void {
    if (!deleting.value) {
        return;
    }

    const channel = deleting.value;
    deleting.value = null;

    router.delete(SalesChannelController.destroy(channel.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan — Sumber Penjualan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Pengaturan Sumber Penjualan
            </h1>
            <p class="text-sm text-muted-foreground">
                Channel tempat pembelian terjadi — toko, marketplace, dll.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-medium">Sumber Penjualan</h2>
                <p class="text-sm text-muted-foreground">
                    Sumber aktif tampil di form pesanan admin
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Tambah Sumber
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
                            v-for="channel in salesChannels"
                            :key="channel.id"
                        >
                            <TableCell class="font-medium">
                                {{ channel.name }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :class="
                                        channel.is_active
                                            ? 'border-green-200 bg-green-100 text-green-800'
                                            : 'border-gray-200 bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{
                                        channel.is_active ? 'Aktif' : 'Nonaktif'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            onClick: () => openEdit(channel),
                                        },
                                        {
                                            label: 'Hapus',
                                            variant: 'destructive',
                                            onClick: () =>
                                                confirmDelete(channel),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!salesChannels.length"
                    title="Belum ada sumber penjualan"
                    description="Tambahkan channel tempat toko menjual."
                />
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? 'Edit Sumber' : 'Tambah Sumber' }}
                </DialogTitle>
                <DialogDescription>
                    Channel tempat pembelian terjadi (toko, marketplace, dll).
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="editing"
                :key="editing.id"
                v-bind="SalesChannelController.update.form(editing.id)"
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
                        placeholder="Shopee"
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
                    Sumber aktif
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
                v-bind="SalesChannelController.store.form()"
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
                        placeholder="shopee (huruf kecil, tanpa spasi)"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="name">Nama *</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="Shopee"
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
        title="Hapus Sumber Penjualan?"
        :description="
            deleting
                ? `Sumber ${deleting.name} (${deleting.code}) akan dihapus.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>
