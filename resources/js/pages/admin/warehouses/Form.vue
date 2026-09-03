<script setup lang="ts">
defineOptions({
    layout: (pageProps: any) => ({
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Gudang', href: '/admin/warehouses' },
            { title: pageProps.warehouse ? 'Edit' : 'Tambah' },
        ],
    }),
});

import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import WarehouseController from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as indexRoute } from '@/routes/admin/warehouses';

type Warehouse = {
    id: string;
    kode: string;
    nama: string;
    alamat: string | null;
    is_defect: boolean;
    is_active: boolean;
};

const props = defineProps<{
    warehouse: Warehouse | null;
}>();

const isEdit = Boolean(props.warehouse);
const submitUrl = isEdit
    ? WarehouseController.update(props.warehouse!.id).url
    : WarehouseController.store().url;
const submitMethod = isEdit ? 'put' : 'post';

const kode = ref(props.warehouse?.kode ?? '');
const nama = ref(props.warehouse?.nama ?? '');
const alamat = ref(props.warehouse?.alamat ?? '');
const isDefect = ref(props.warehouse?.is_defect ?? false);
const isActive = ref(props.warehouse?.is_active ?? true);

function onFormError() {
    toast.error('Gagal menyimpan — periksa kembali isian yang wajib diisi.');
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Gudang' : 'Buat Gudang'" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button
                variant="ghost"
                size="icon"
                class="size-8 shrink-0"
                as-child
            >
                <Link :href="indexRoute().url"
                    ><ArrowLeft class="size-4"
                /></Link>
            </Button>
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{
                        isEdit
                            ? `Edit Gudang: ${warehouse?.nama}`
                            : 'Buat Gudang Baru'
                    }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Lokasi penyimpanan stok buku
                </p>
            </div>
        </div>

        <Form
            :action="submitUrl"
            :method="submitMethod"
            class="max-w-xl"
            @error="onFormError"
        >
            <FormErrorAlert
                :errors="
                    (usePage().props.errors ?? {}) as Record<string, string>
                "
            />
            <Card>
                <CardContent class="flex flex-col gap-4">
                    <div class="grid gap-2">
                        <Label for="nama">Nama Gudang *</Label>
                        <Input
                            id="nama"
                            name="nama"
                            v-model="nama"
                            placeholder="Surabaya"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="kode">
                            Kode *
                            <span
                                v-if="isEdit"
                                class="text-xs font-normal text-muted-foreground"
                            >
                                (tidak bisa diubah setelah dibuat)
                            </span>
                        </Label>
                        <Input
                            id="kode"
                            name="kode"
                            v-model="kode"
                            :disabled="isEdit"
                            placeholder="surabaya"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="alamat">Alamat</Label>
                        <Textarea
                            id="alamat"
                            name="alamat"
                            v-model="alamat"
                            rows="2"
                        />
                    </div>

                    <div class="flex flex-col gap-3">
                        <Label class="flex items-center gap-2">
                            <Checkbox
                                name="is_defect"
                                v-model="isDefect"
                                :disabled="isEdit && warehouse?.is_defect"
                            />
                            <span>
                                Gudang defect
                                <span
                                    class="block text-xs font-normal text-muted-foreground"
                                >
                                    Khusus barang cacat — stoknya tidak pernah
                                    dijual (hanya boleh satu)
                                </span>
                            </span>
                        </Label>

                        <Label class="flex items-center gap-2">
                            <Checkbox name="is_active" v-model="isActive" />
                            <span
                                >Aktif (bisa dipakai mutasi & pengiriman)</span
                            >
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <!-- Action bar — sticky di bawah agar selalu terlihat -->
            <div
                class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6"
            >
                <Button type="submit">
                    {{ isEdit ? 'Simpan Perubahan' : 'Buat Gudang' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
