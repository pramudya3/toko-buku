<script setup lang="ts">
defineOptions({
    layout: (pageProps: any) => ({
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Voucher', href: '/admin/vouchers' },
            { title: pageProps.voucher ? 'Edit' : 'Tambah' },
        ],
    }),
});

import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import VoucherController from '@/actions/App/Http/Controllers/Admin/VoucherController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
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
import { Switch } from '@/components/ui/switch';
import { index as indexRoute } from '@/routes/admin/vouchers';

type Voucher = {
    id: string;
    nama: string;
    kode: string | null;
    voucher_type: string;
    discount_scope: string;
    discount_percentage: number | null;
    discount_value: number | null;
    min_order_amount: number;
    max_uses: number | null;
    max_uses_per_user: number | null;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

const props = defineProps<{
    voucher: Voucher | null;
    typeOptions: Record<string, string>;
    scopeOptions: Record<string, string>;
}>();

const isEdit = Boolean(props.voucher);
const action = isEdit ? VoucherController.update : VoucherController.store;
const submitArgs = isEdit ? props.voucher?.id : undefined;

const isActive = ref(props.voucher ? props.voucher.is_active : true);

const voucherType = ref<'percentage' | 'fixed'>(
    props.voucher?.voucher_type === 'fixed' ? 'fixed' : 'percentage',
);

const discountScope = ref<'item' | 'ongkir'>(
    props.voucher?.discount_scope === 'ongkir' ? 'ongkir' : 'item',
);

const voucherTypeValue = computed(() => voucherType.value);
const discountScopeValue = computed(() => discountScope.value);
</script>

<template>
    <Head :title="isEdit ? 'Edit Voucher' : 'Buat Voucher'" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{
                    isEdit
                        ? `Edit Voucher: ${voucher?.nama}`
                        : 'Buat Voucher Baru'
                }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Atur diskon voucher yang bisa dipakai customer di checkout
            </p>
        </div>

        <Form
            v-bind="action.form(submitArgs as string)"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <FormErrorAlert :errors="errors" />
            <input
                type="hidden"
                name="voucher_type"
                :value="voucherTypeValue"
            />
            <input
                type="hidden"
                name="discount_scope"
                :value="discountScopeValue"
            />
            <input
                type="hidden"
                name="is_active"
                :value="isActive ? '1' : '0'"
            />

            <div class="grid items-start gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-medium">
                            Informasi Voucher
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="nama">Nama Voucher *</Label>
                            <Input
                                id="nama"
                                name="nama"
                                :default-value="voucher?.nama ?? undefined"
                                placeholder="Contoh: Diskon Ramadan"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="kode">Kode (opsional)</Label>
                            <Input
                                id="kode"
                                name="kode"
                                :default-value="voucher?.kode ?? undefined"
                                placeholder="Kosongkan untuk generate otomatis"
                            />
                            <p class="text-xs text-muted-foreground">
                                Kode untuk referensi di invoice & aktivitas —
                                diisi otomatis dari nama bila dikosongkan.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="voucher_type">Jenis Diskon *</Label>
                            <Select v-model="voucherType">
                                <SelectTrigger id="voucher_type">
                                    <SelectValue placeholder="Pilih jenis" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage"
                                        >Persentase (%)</SelectItem
                                    >
                                    <SelectItem value="fixed"
                                        >Nominal (Rp)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="discount_scope">Target Diskon *</Label>
                            <Select v-model="discountScope">
                                <SelectTrigger id="discount_scope">
                                    <SelectValue placeholder="Pilih target" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="item"
                                        >Harga Item — dipotong dari subtotal
                                        produk</SelectItem
                                    >
                                    <SelectItem value="ongkir"
                                        >Ongkos Kirim — dipotong dari biaya
                                        kirim</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <p
                                v-if="discountScope === 'ongkir'"
                                class="text-xs text-muted-foreground"
                            >
                                Minimal belanja tetap dihitung dari subtotal
                                item. Voucher hanya berlaku untuk pesanan yang
                                dikirim (bukan ambil sendiri).
                            </p>
                        </div>
                        <div
                            v-if="voucherType === 'percentage'"
                            class="grid gap-2"
                        >
                            <Label for="discount_percentage"
                                >Diskon (%) *</Label
                            >
                            <Input
                                id="discount_percentage"
                                name="discount_percentage"
                                type="number"
                                min="1"
                                max="100"
                                :default-value="
                                    voucher?.discount_percentage ?? undefined
                                "
                            />
                        </div>
                        <div v-else class="grid gap-2">
                            <Label for="discount_value">Nominal Diskon *</Label>
                            <CurrencyInput
                                id="discount_value"
                                name="discount_value"
                                :default-value="
                                    voucher?.discount_value ?? undefined
                                "
                                placeholder="25.000"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="start_date">Tanggal Mulai *</Label>
                                <Input
                                    id="start_date"
                                    name="start_date"
                                    type="date"
                                    :default-value="
                                        voucher?.start_date ?? undefined
                                    "
                                    required
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="end_date">Tanggal Selesai *</Label>
                                <Input
                                    id="end_date"
                                    name="end_date"
                                    type="date"
                                    :default-value="
                                        voucher?.end_date ?? undefined
                                    "
                                    required
                                />
                            </div>
                        </div>
                        <Label class="flex h-9 items-center gap-2 text-sm">
                            <Switch v-model="isActive" />
                            Aktif
                        </Label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-medium">
                            Batasan Pemakaian
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="min_order_amount"
                                >Minimal Belanja</Label
                            >
                            <CurrencyInput
                                id="min_order_amount"
                                name="min_order_amount"
                                :default-value="
                                    voucher?.min_order_amount ?? undefined
                                "
                                placeholder="0 = tanpa batas"
                            />
                            <p class="text-xs text-muted-foreground">
                                Subtotal produk minimum (setelah promo & tier)
                                agar voucher bisa dipakai.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="max_uses">Kuota Global</Label>
                                <Input
                                    id="max_uses"
                                    name="max_uses"
                                    type="number"
                                    min="1"
                                    :default-value="
                                        voucher?.max_uses ?? undefined
                                    "
                                    placeholder="0 = tanpa batas"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Total maksimal pemakaian semua customer.
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="max_uses_per_user"
                                    >Maks / Customer</Label
                                >
                                <Input
                                    id="max_uses_per_user"
                                    name="max_uses_per_user"
                                    type="number"
                                    min="1"
                                    :default-value="
                                        voucher?.max_uses_per_user ?? undefined
                                    "
                                    placeholder="0 = tanpa batas"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Maksimal pemakaian per satu customer.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 border-t pt-4">
                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="processing"
                            >
                                {{
                                    processing
                                        ? 'Menyimpan...'
                                        : isEdit
                                          ? 'Simpan Perubahan'
                                          : 'Buat Voucher'
                                }}
                            </Button>
                            <Button
                                variant="outline"
                                type="button"
                                class="w-full"
                                as-child
                            >
                                <Link :href="indexRoute().url">Batal</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Form>
    </div>
</template>
