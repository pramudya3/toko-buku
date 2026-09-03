<script setup lang="ts">
defineOptions({
    layout: (pageProps: any) => ({
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Supplier', href: '/admin/suppliers' },
            { title: pageProps.supplier ? 'Edit' : 'Tambah' },
        ],
    }),
});

import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import SupplierController from '@/actions/App/Http/Controllers/Admin/SupplierController';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as indexRoute } from '@/routes/admin/suppliers';

type Supplier = {
    id: string;
    nama: string;
    telepon: string | null;
    alamat: string | null;
    catatan: string | null;
};

const props = defineProps<{
    supplier: Supplier | null;
}>();

const isEdit = Boolean(props.supplier);
const submitUrl = isEdit
    ? SupplierController.update(props.supplier!.id).url
    : SupplierController.store().url;
const submitMethod = isEdit ? 'put' : 'post';

const nama = ref(props.supplier?.nama ?? '');
const telepon = ref(props.supplier?.telepon ?? '');
const alamat = ref(props.supplier?.alamat ?? '');
const catatan = ref(props.supplier?.catatan ?? '');

function onFormError() {
    toast.error('Gagal menyimpan — periksa kembali isian yang wajib diisi.');
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Supplier' : 'Buat Supplier'" />

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
                            ? `Edit Supplier: ${supplier?.nama}`
                            : 'Buat Supplier Baru'
                    }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Supplier pemasok buku untuk pembelian & retur
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
                        <Label for="nama">Nama Supplier *</Label>
                        <Input
                            id="nama"
                            name="nama"
                            v-model="nama"
                            placeholder="PT Penerbit Gramedia"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="telepon">Telepon / WhatsApp</Label>
                        <Input
                            id="telepon"
                            name="telepon"
                            v-model="telepon"
                            placeholder="0812-3456-7890"
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

                    <div class="grid gap-2">
                        <Label for="catatan">Catatan</Label>
                        <Textarea
                            id="catatan"
                            name="catatan"
                            v-model="catatan"
                            rows="2"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Action bar — sticky di bawah agar selalu terlihat -->
            <div
                class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6"
            >
                <Button type="submit">
                    {{ isEdit ? 'Simpan Perubahan' : 'Buat Supplier' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
