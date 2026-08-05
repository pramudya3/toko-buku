<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Ban,
    CheckCircle2,
    PackageCheck,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as indexRoute } from '@/routes/admin/orders';

type OrderItem = {
    id: number;
    qty: number;
    price_original: number;
    promo_discount_amount: number;
    tier_discount_amount: number;
    price_final: number;
    book: {
        id: number;
        judul: string;
        kode_sku: string | null;
        cover_url: string | null;
    };
};

type Order = {
    id: number;
    no_order: string;
    nama_pembeli: string;
    alamat: string | null;
    metode_bayar: string;
    total: number;
    shipping_cost: number;
    is_dropship: boolean;
    warehouse_origin: string | null;
    status: string;
    ekspedisi: string | null;
    ongkir_estimasi: number | null;
    created_at: string;
    user: {
        id: number;
        name: string;
        whatsapp_number: string | null;
        status_pelanggan: string;
    } | null;
    items: OrderItem[];
    dropshipper: {
        id: number;
        end_customer_name: string;
        end_customer_whatsapp: string | null;
        end_customer_address: string | null;
    } | null;
    cash_flows: Array<{
        id: number;
        entry_date: string;
        flow_type: string;
        amount: number;
        description: string | null;
    }>;
};

type Props = {
    order: Order;
    statusOptions: Record<string, string>;
    couriers: Record<string, string>;
    warehouseOptions: Record<string, string>;
};

const props = defineProps<Props>();

const processOpen = ref(false);

const statusVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    menunggu_konfirmasi: 'warning',
    diproses: 'info',
    dikirim: 'info',
    selesai: 'success',
    batal: 'danger',
};

const subtotal = computed(() =>
    props.order.items.reduce(
        (sum, item) => sum + item.price_final * item.qty,
        0,
    ),
);

const paymentLabel: Record<string, string> = {
    transfer: 'Transfer',
    cod: 'COD',
};

const flowTypeVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    revenue: 'success',
    shipping: 'info',
    refund: 'danger',
};
</script>

<template>
    <Head :title="`Order ${order.no_order}`" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="indexRoute().url">← Pesanan</Link>
                </Button>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ order.no_order }}
                </h1>
                <StatusBadge
                    :variant="statusVariant[order.status] ?? 'neutral'"
                    :label="statusOptions[order.status] ?? order.status"
                />
                <StatusBadge
                    v-if="order.is_dropship"
                    variant="info"
                    label="Dropship"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Konfirmasi: isi ongkir + ekspedisi + gudang asal -->
                <Button
                    v-if="order.status === 'menunggu_konfirmasi'"
                    @click="processOpen = true"
                >
                    <PackageCheck class="size-4" />
                    Proses Order
                </Button>
                <Form
                    v-if="order.status === 'diproses'"
                    v-bind="OrderController.updateStatus.form(order.id)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="status" value="dikirim" />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                    >
                        <Truck class="size-4" />
                        Tandai Dikirim
                    </Button>
                </Form>
                <Form
                    v-if="order.status === 'dikirim'"
                    v-bind="OrderController.updateStatus.form(order.id)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="status" value="selesai" />
                    <Button type="submit" :disabled="processing">
                        <CheckCircle2 class="size-4" />
                        Tandai Selesai
                    </Button>
                </Form>
                <Form
                    v-if="!['selesai', 'batal'].includes(order.status)"
                    v-bind="OrderController.updateStatus.form(order.id)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="status" value="batal" />
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Ban class="size-4" />
                        Batalkan
                    </Button>
                </Form>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Item pesanan -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Item Pesanan</CardTitle
                    >
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Buku</TableHead>
                                <TableHead>Qty</TableHead>
                                <TableHead>Harga Asli</TableHead>
                                <TableHead>Diskon Promo</TableHead>
                                <TableHead>Diskon Tier</TableHead>
                                <TableHead class="text-right"
                                    >Harga Final</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="item in order.items"
                                :key="item.id"
                            >
                                <TableCell>
                                    <p class="font-medium">
                                        {{ item.book.judul }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ item.book.kode_sku }}
                                    </p>
                                </TableCell>
                                <TableCell>{{ item.qty }}</TableCell>
                                <TableCell
                                    ><Money :value="item.price_original"
                                /></TableCell>
                                <TableCell>
                                    <span
                                        v-if="item.promo_discount_amount > 0"
                                        class="text-red-600"
                                        >-<Money
                                            :value="item.promo_discount_amount"
                                    /></span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="item.tier_discount_amount > 0"
                                        class="text-red-600"
                                        >-<Money
                                            :value="item.tier_discount_amount"
                                    /></span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                >
                                    <Money :value="item.price_final" />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div class="flex flex-col gap-1 border-t px-4 py-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums"
                                ><Money :value="subtotal"
                            /></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Ongkir</span>
                            <span class="tabular-nums"
                                ><Money :value="order.shipping_cost"
                            /></span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 font-semibold"
                        >
                            <span>Grand Total</span>
                            <span class="tabular-nums"
                                ><Money :value="order.total"
                            /></span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Info order -->
            <div class="flex flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Informasi Order</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Pembeli</p>
                            <p class="font-medium">{{ order.nama_pembeli }}</p>
                            <p
                                v-if="order.user"
                                class="text-xs text-muted-foreground"
                            >
                                {{ order.user.name }} ·
                                {{ order.user.status_pelanggan }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Metode Bayar
                            </p>
                            <p>
                                {{
                                    paymentLabel[order.metode_bayar] ??
                                    order.metode_bayar
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Alamat Pengiriman
                            </p>
                            <p class="whitespace-pre-wrap">
                                {{ order.alamat ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Ekspedisi
                            </p>
                            <p>
                                {{
                                    couriers[order.ekspedisi ?? ''] ??
                                    order.ekspedisi ??
                                    'Belum diisi'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Gudang Asal
                            </p>
                            <p>
                                {{
                                    warehouseOptions[
                                        order.warehouse_origin ?? ''
                                    ] ?? 'Belum dipilih'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Dibuat</p>
                            <p>
                                {{
                                    new Date(order.created_at).toLocaleString(
                                        'id-ID',
                                    )
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="order.dropshipper">
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Data Dropship (end-customer)</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Nama</p>
                            <p class="font-medium">
                                {{ order.dropshipper.end_customer_name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                WhatsApp
                            </p>
                            <p class="tabular-nums">
                                {{
                                    order.dropshipper.end_customer_whatsapp ??
                                    '—'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Alamat</p>
                            <p class="whitespace-pre-wrap">
                                {{
                                    order.dropshipper.end_customer_address ??
                                    '—'
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="order.cash_flows.length">
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Arus Kas Terkait</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-2 text-sm">
                        <div
                            v-for="flow in order.cash_flows"
                            :key="flow.id"
                            class="flex items-center justify-between rounded-lg border px-3 py-2"
                        >
                            <div>
                                <StatusBadge
                                    :variant="
                                        flowTypeVariant[flow.flow_type] ??
                                        'neutral'
                                    "
                                    :label="flow.flow_type"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ flow.description }}
                                </p>
                            </div>
                            <span class="font-medium tabular-nums"
                                ><Money :value="flow.amount"
                            /></span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Dialog proses order -->
        <Dialog v-model:open="processOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Proses Order {{ order.no_order }}</DialogTitle>
                    <DialogDescription>
                        Isi ongkir final, pilih ekspedisi, dan tentukan gudang
                        asal. Status berubah menjadi <b>Diproses</b>.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="OrderController.process.form(order.id)"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="shipping_cost">Ongkir Final (Rp) *</Label>
                        <Input
                            id="shipping_cost"
                            name="shipping_cost"
                            type="number"
                            min="0"
                            :default-value="order.shipping_cost || undefined"
                            required
                        />
                        <span
                            v-if="errors.shipping_cost"
                            class="text-sm text-destructive"
                            >{{ errors.shipping_cost }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="ekspedisi">Ekspedisi *</Label>
                        <Select
                            name="ekspedisi"
                            :default-value="order.ekspedisi ?? undefined"
                        >
                            <SelectTrigger id="ekspedisi">
                                <SelectValue placeholder="Pilih ekspedisi" />
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
                        <span
                            v-if="errors.ekspedisi"
                            class="text-sm text-destructive"
                            >{{ errors.ekspedisi }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="warehouse_origin">Gudang Asal *</Label>
                        <Select name="warehouse_origin">
                            <SelectTrigger id="warehouse_origin">
                                <SelectValue placeholder="Pilih gudang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in warehouseOptions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span
                            v-if="errors.warehouse_origin"
                            class="text-sm text-destructive"
                            >{{ errors.warehouse_origin }}</span
                        >
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            <ArrowRight class="size-4" />
                            {{ processing ? 'Memproses...' : 'Proses Order' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
