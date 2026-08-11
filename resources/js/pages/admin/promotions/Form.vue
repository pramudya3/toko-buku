<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PromotionController from '@/actions/App/Http/Controllers/Admin/PromotionController';
import BookCombobox from '@/components/BookCombobox.vue';
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
import { index as indexRoute } from '@/routes/admin/promotions';

type Book = {
    id: string;
    judul: string;
    kode_sku: string | null;
    harga: number;
    cover_url?: string | null;
};

type Promotion = {
    id: string;
    promo_name: string;
    promo_type: string;
    discount_percentage: number | null;
    promo_value: number | null;
    start_date: string;
    end_date: string;
    is_active: boolean;
    is_global: boolean;
    books: Book[];
};

const props = defineProps<{
    promotion: Promotion | null;
    typeOptions: Record<string, string>;
    books: Book[];
}>();

const isEdit = Boolean(props.promotion);
const action = isEdit ? PromotionController.update : PromotionController.store;
const submitArgs = isEdit ? props.promotion?.id : undefined;

const isActive = ref(props.promotion ? props.promotion.is_active : true);

// ── Mode: satuan / bundle ──
const promoMode = ref<'satuan' | 'bundle'>(
    props.promotion?.promo_type === 'bundle' ? 'bundle' : 'satuan',
);

// ── Satuan: jenis diskon ──
const satuanType = ref<'percentage' | 'fixed'>(
    props.promotion?.promo_type === 'fixed' ? 'fixed' : 'percentage',
);

// ── Bundle: buku dalam paket ──
const bundleBooks = ref<Book[]>(
    isEdit && props.promotion?.promo_type === 'bundle'
        ? (props.promotion.books as unknown as Book[])
        : [],
);

// ── Satuan: buku terpilih ──
const selectedBooks = ref<Book[]>(
    isEdit && props.promotion?.promo_type !== 'bundle'
        ? ((props.promotion?.books as unknown as Book[]) ?? [])
        : [],
);

// ── Buku terpilih (satuan & bundle) ──
const targetBooks = computed(() =>
    promoMode.value === 'bundle' ? bundleBooks.value : selectedBooks.value,
);

function onTargetBooksChange(books: Book[]) {
    if (promoMode.value === 'bundle') {
        bundleBooks.value = books;
    } else {
        selectedBooks.value = books;
    }
}

// ── Computed hidden fields ──
const promoTypeValue = computed(() =>
    promoMode.value === 'bundle' ? 'bundle' : satuanType.value,
);
const isGlobalValue = computed(() =>
    promoMode.value === 'bundle'
        ? '0'
        : selectedBooks.value.length === 0
          ? '1'
          : '0',
);
const bookIdsValue = computed(() =>
    promoMode.value === 'bundle'
        ? bundleBooks.value.map((b) => b.id)
        : selectedBooks.value.map((b) => b.id),
);
</script>

<template>
    <Head :title="isEdit ? 'Edit Promo' : 'Buat Promo'" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <!-- ── Header ── -->
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{
                    isEdit
                        ? `Edit Promo: ${promotion?.promo_name}`
                        : 'Buat Promo Baru'
                }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Atur diskon satuan buku atau paket bundle
            </p>
        </div>

        <Form
            v-bind="action.form(submitArgs as string)"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <FormErrorAlert :errors="errors" />
            <!-- ── Hidden fields ── -->
            <input type="hidden" name="promo_type" :value="promoTypeValue" />
            <input type="hidden" name="is_global" :value="isGlobalValue" />
            <input
                v-for="id in bookIdsValue"
                :key="id"
                type="hidden"
                name="book_ids[]"
                :value="String(id)"
            />

            <div class="grid items-start gap-4 lg:grid-cols-3">
                <!-- ═══════════════════════════════════════════
                     KIRI (2/3) — Target Buku
                ════════════════════════════════════════════ -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base font-medium">
                            Target Buku
                            <span
                                v-if="targetBooks.length"
                                class="ml-1 text-xs font-normal text-muted-foreground"
                            >
                                ({{ targetBooks.length }} dipilih)
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <!-- Pilih buku (combobox + filter teks) -->
                        <BookCombobox
                            :model-value="targetBooks"
                            :initial-books="books"
                            :empty-hint="
                                promoMode === 'bundle'
                                    ? 'Pilih minimal 2 judul buku untuk membentuk paket.'
                                    : 'Pilih buku untuk diberikan diskon — kosongkan untuk promo global.'
                            "
                            @update:model-value="onTargetBooksChange"
                        />
                    </CardContent>
                </Card>

                <!-- ═══════════════════════════════════════════
                     KANAN (1/3) — Aturan Promo (sticky)
                ════════════════════════════════════════════ -->
                <Card class="lg:sticky lg:top-20">
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Aturan Promo</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <!-- Tipe Promo -->
                        <div class="grid gap-2">
                            <Label for="promo_mode">Tipe Promo</Label>
                            <Select v-model="promoMode">
                                <SelectTrigger id="promo_mode">
                                    <SelectValue placeholder="Pilih mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="satuan"
                                        >Satuan — diskon per judul
                                        buku</SelectItem
                                    >
                                    <SelectItem value="bundle"
                                        >Bundle — paket gabungan
                                        buku</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Satuan: jenis diskon + nilai -->
                        <template v-if="promoMode === 'satuan'">
                            <div class="grid gap-2">
                                <Label for="satuan_type">Jenis Diskon</Label>
                                <Select v-model="satuanType">
                                    <SelectTrigger id="satuan_type">
                                        <SelectValue
                                            placeholder="Pilih jenis"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="percentage"
                                            >Persentase (%)</SelectItem
                                        >
                                        <SelectItem value="fixed"
                                            >Harga Tetap (Rp)</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div
                                v-if="satuanType === 'percentage'"
                                class="grid gap-2"
                            >
                                <Label for="discount_percentage"
                                    >Diskon (%)</Label
                                >
                                <Input
                                    id="discount_percentage"
                                    name="discount_percentage"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :default-value="
                                        promotion?.discount_percentage ??
                                        undefined
                                    "
                                />
                            </div>
                            <div v-else class="grid gap-2">
                                <Label for="promo_value">Harga Tetap</Label>
                                <CurrencyInput
                                    id="promo_value"
                                    name="promo_value"
                                    :default-value="
                                        promotion?.promo_value ?? undefined
                                    "
                                    placeholder="50.000"
                                />
                            </div>
                        </template>

                        <!-- Bundle: diskon paket -->
                        <template v-else>
                            <div class="grid gap-2">
                                <Label for="discount_percentage"
                                    >Diskon (%)</Label
                                >
                                <Input
                                    id="discount_percentage"
                                    name="discount_percentage"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :default-value="
                                        promotion?.discount_percentage ??
                                        undefined
                                    "
                                />
                            </div>
                        </template>

                        <!-- Nama + periode + status -->
                        <div class="grid gap-4 border-t pt-4">
                            <div class="grid gap-2">
                                <Label for="promo_name">Nama Promo *</Label>
                                <Input
                                    id="promo_name"
                                    name="promo_name"
                                    :default-value="
                                        promotion?.promo_name ?? undefined
                                    "
                                    placeholder="Contoh: Diskon Akhir Pekan"
                                    required
                                />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-2">
                                    <Label for="start_date"
                                        >Tanggal Mulai *</Label
                                    >
                                    <Input
                                        id="start_date"
                                        name="start_date"
                                        type="date"
                                        :default-value="
                                            promotion?.start_date ?? undefined
                                        "
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="end_date"
                                        >Tanggal Selesai *</Label
                                    >
                                    <Input
                                        id="end_date"
                                        name="end_date"
                                        type="date"
                                        :default-value="
                                            promotion?.end_date ?? undefined
                                        "
                                        required
                                    />
                                </div>
                            </div>
                            <Label class="flex h-9 items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    :value="isActive ? '1' : '0'"
                                />
                                <Switch v-model="isActive" />
                                Aktif
                            </Label>
                        </div>

                        <!-- Actions -->
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
                                          : 'Buat Promo'
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
