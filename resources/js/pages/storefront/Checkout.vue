<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Minus, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import AddressFields from '@/components/AddressFields.vue';
import type { AddressValue } from '@/components/AddressFields.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import Money from '@/components/Money.vue';
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
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

type CartBook = {
    id: number;
    judul: string;
    kode_sku: string | null;
    harga: number;
    stok: number;
};

type CartItem = {
    book: CartBook;
    qty: number;
};

type UserInfo = {
    name: string;
    whatsapp_number: string | null;
    email: string | null;
    alamat: string | null;
    provinsi: string | null;
    kabupaten_kota: string | null;
    kecamatan: string | null;
    kode_pos: string | null;
};

const props = defineProps<{
    items: CartItem[];
    paymentOptions: Record<string, string>;
    couriers: Record<string, string>;
    user: UserInfo | null;
}>();

defineOptions({
    layout: CustomerLayout,
});

const subtotal = computed(() =>
    props.items.reduce((sum, item) => sum + item.book.harga * item.qty, 0),
);

const address = ref<AddressValue>({
    provinsi: props.user?.provinsi ?? '',
    kabupaten_kota: props.user?.kabupaten_kota ?? '',
    kecamatan: props.user?.kecamatan ?? '',
    kode_pos: props.user?.kode_pos ?? '',
    alamat: props.user?.alamat ?? '',
});

function updateQty(item: CartItem, delta: number) {
    const qty = item.qty + delta;

    if (qty <= 0) {
        router.post(CartController.remove(item.book.id).url, undefined, {
            preserveScroll: true,
        });

        return;
    }

    router.post(
        CartController.updateQty(item.book.id).url,
        { qty },
        { preserveScroll: true },
    );
}

function removeItem(item: CartItem) {
    router.post(CartController.remove(item.book.id).url, undefined, {
        preserveScroll: true,
    });
}

function onFormError() {
    toast.error('Gagal memproses pesanan — periksa kembali isian yang wajib diisi.');
}
</script>

<template>
    <Head title="Checkout" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Checkout</h1>
            <p class="text-sm text-muted-foreground">
                Lengkapi data diri dan alamat pengiriman.
            </p>
        </div>

        <Form
            :action="CartController.store().url"
            method="post"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
            @error="onFormError"
        >
            <div v-if="items.length" class="grid gap-6 lg:grid-cols-3">
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base font-medium"
                                >Data Pembeli</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="nama_pembeli">Nama Lengkap *</Label>
                                <Input
                                    id="nama_pembeli"
                                    name="nama_pembeli"
                                    :default-value="user?.name ?? undefined"
                                    required
                                />
                                <InputError :message="errors.nama_pembeli" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="whatsapp_pembeli">WhatsApp</Label>
                                <Input
                                    id="whatsapp_pembeli"
                                    name="whatsapp_pembeli"
                                    :default-value="
                                        user?.whatsapp_number ?? undefined
                                    "
                                    placeholder="08xxxxxxxxxx"
                                />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="email_pembeli">Email (opsional)</Label>
                                <Input
                                    id="email_pembeli"
                                    name="email_pembeli"
                                    type="email"
                                    :default-value="user?.email ?? undefined"
                                />
                                <InputError :message="errors.email_pembeli" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base font-medium"
                                >Alamat Pengiriman</CardTitle
                            >
                        </CardHeader>
                        <CardContent>
                            <AddressFields
                                v-model="address"
                                :endpoint="'public'"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base font-medium"
                                >Pembayaran & Pengiriman</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="metode_bayar">Metode Bayar *</Label>
                                <Select
                                    name="metode_bayar"
                                    default-value="transfer"
                                >
                                    <SelectTrigger id="metode_bayar">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(label, value) in paymentOptions"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.metode_bayar" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ekspedisi">Ekspedisi</Label>
                                <Select name="ekspedisi">
                                    <SelectTrigger id="ekspedisi">
                                        <SelectValue
                                            placeholder="Pilih ekspedisi"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(label, value) in couriers"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="h-fit lg:sticky lg:top-20">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base font-medium"
                                >Ringkasan Pesanan</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="flex flex-col gap-4">
                            <ul class="flex flex-col gap-3">
                                <li
                                    v-for="item in items"
                                    :key="item.book.id"
                                    class="flex flex-col gap-2 rounded-lg border bg-muted/30 p-3"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="min-w-0 truncate text-sm font-medium">
                                            {{ item.book.judul }}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="size-8 shrink-0 text-destructive"
                                            title="Hapus item"
                                            @click="removeItem(item)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs text-muted-foreground">
                                            <Money :value="item.book.harga" />
                                            × {{ item.qty }}
                                        </p>
                                        <Money
                                            :value="item.book.harga * item.qty"
                                            class="shrink-0 text-sm font-medium"
                                        />
                                    </div>
                                    <div
                                        class="flex items-center gap-2"
                                    >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon-sm"
                                            class="size-9"
                                            @click="updateQty(item, -1)"
                                        >
                                            <Minus class="size-4" />
                                        </Button>
                                        <span
                                            class="w-10 text-center text-base tabular-nums"
                                            >{{ item.qty }}</span
                                        >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon-sm"
                                            class="size-9"
                                            @click="updateQty(item, 1)"
                                        >
                                            <Plus class="size-4" />
                                        </Button>
                                    </div>
                                </li>
                            </ul>

                            <div
                                class="flex items-center justify-between border-t pt-3"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Subtotal</span
                                >
                                <Money
                                    :value="subtotal"
                                    class="font-semibold"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Promo & diskon tier dihitung saat pesanan
                                diproses. Ongkir disepakati via WhatsApp.
                            </p>

                            <Button
                                type="submit"
                                size="lg"
                                :disabled="processing"
                            >
                                {{ processing ? 'Memproses...' : 'Buat Pesanan' }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <EmptyState
                v-else
                title="Keranjang kosong"
                description="Belum ada buku di keranjang."
            >
                <Button size="sm" as-child>
                    <Link :href="'/buku'">Lihat Katalog</Link>
                </Button>
            </EmptyState>
            <InputError :message="errors.items" />
        </Form>
    </div>
</template>
