<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as indexRoute } from '@/routes/admin/customers';

type Customer = {
    id: number;
    name: string;
    email: string;
    whatsapp_number: string | null;
    status_pelanggan: string;
    is_active: boolean;
    alamat: string | null;
    provinsi: string | null;
    kabupaten_kota: string | null;
    kecamatan: string | null;
    kode_pos: string | null;
};

const props = defineProps<{
    customer: Customer | null;
    tierOptions: Record<string, string>;
}>();

const isEdit = Boolean(props.customer);
const action = isEdit
    ? CustomerController.update.form(props.customer!.id)
    : CustomerController.store.form();

const tierVariant = computed(() => {
    const tier = props.customer?.status_pelanggan ?? '';

    if (tier === 'reseller') {
        return 'warning';
    }

    if (tier === 'bazaf') {
        return 'info';
    }

    if (tier === 'guru') {
        return 'success';
    }

    return 'neutral';
});
</script>

<template>
    <Head :title="isEdit ? `Edit Pelanggan: ${customer?.name}` : 'Buat Pelanggan'" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ isEdit ? 'Edit Pelanggan' : 'Buat Pelanggan' }}
                </h1>
                <p v-if="customer" class="text-sm text-muted-foreground">
                    <StatusBadge
                        :variant="tierVariant"
                        :label="
                            tierOptions[customer.status_pelanggan] ??
                            customer.status_pelanggan
                        "
                    />
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Menambahkan pelanggan baru ke toko
                </p>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="indexRoute().url">← Kembali</Link>
            </Button>
        </div>

        <Form
            v-bind="action"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Data Dasar</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Nama *</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="customer?.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email *</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            :default-value="customer?.email"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">Password *</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            :required="!isEdit"
                        />
                        <p v-if="isEdit" class="text-xs text-muted-foreground">
                            Kosongkan untuk tidak mengubah password.
                        </p>
                        <InputError :message="errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="whatsapp_number">WhatsApp</Label>
                        <Input
                            id="whatsapp_number"
                            name="whatsapp_number"
                            :default-value="customer?.whatsapp_number ?? undefined"
                            placeholder="08xxxxxxxxxx"
                        />
                        <InputError :message="errors.whatsapp_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status_pelanggan">Status Tier *</Label>
                        <Select
                            name="status_pelanggan"
                            :default-value="customer?.status_pelanggan ?? 'reguler'"
                        >
                            <SelectTrigger id="status_pelanggan">
                                <SelectValue placeholder="Pilih tier" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in tierOptions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.status_pelanggan" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="is_active">Status</Label>
                        <Select
                            name="is_active"
                            :default-value="
                                customer
                                    ? customer.is_active
                                        ? '1'
                                        : '0'
                                    : '1'
                            "
                        >
                            <SelectTrigger id="is_active">
                                <SelectValue placeholder="Pilih status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Aktif</SelectItem>
                                <SelectItem value="0">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.is_active" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium">Alamat</CardTitle>
                </CardHeader>
                <CardContent>
                    <AddressFields
                        :model-value="{
                            provinsi: customer?.provinsi ?? '',
                            kabupaten_kota: customer?.kabupaten_kota ?? '',
                            kecamatan: customer?.kecamatan ?? '',
                            kode_pos: customer?.kode_pos ?? '',
                            alamat: customer?.alamat ?? '',
                        }"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{
                        processing
                            ? 'Menyimpan...'
                            : isEdit
                              ? 'Simpan Perubahan'
                              : 'Buat Pelanggan'
                    }}
                </Button>
                <Button variant="outline" type="button" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
