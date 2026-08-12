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
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import CourierController from '@/actions/App/Http/Controllers/Admin/CourierController';
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

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(courier: Courier): void {
    editing.value = courier;
    dialogOpen.value = true;
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
                Daftar ekspedisi dan tarif ongkos kirim
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-medium">Ekspedisi</h2>
                <p class="text-sm text-muted-foreground">
                    Ekspedisi aktif tampil sebagai pilihan saat input ongkir di
                    pesanan
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Tambah Ekspedisi
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
                        <TableRow v-for="courier in couriers" :key="courier.id">
                            <TableCell class="font-medium">
                                {{ courier.name }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :class="
                                        courier.is_active
                                            ? 'border-green-200 bg-green-100 text-green-800'
                                            : 'border-gray-200 bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{
                                        courier.is_active ? 'Aktif' : 'Nonaktif'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            onClick: () => openEdit(courier),
                                        },
                                        {
                                            label: 'Hapus',
                                            variant: 'destructive',
                                            onClick: () =>
                                                confirmDelete(courier),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!couriers.length"
                    title="Belum ada ekspedisi"
                    description="Tambahkan ekspedisi yang tersedia untuk pengiriman."
                />
            </CardContent>
        </Card>
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
