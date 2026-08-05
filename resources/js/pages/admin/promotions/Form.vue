<script setup lang="ts">
import { Form, Head, Link, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import PromotionController from '@/actions/App/Http/Controllers/Admin/PromotionController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as indexRoute } from '@/routes/admin/promotions';
import { books as bookOptions } from '@/routes/admin/promotions/options';

type Book = {
    id: number;
    judul: string;
    kode_sku: string | null;
    harga: number;
};

type Promotion = {
    id: number;
    promo_name: string;
    promo_type: string;
    discount_percentage: number | null;
    promo_value: number | null;
    bundle_qty: number | null;
    start_date: string;
    end_date: string;
    is_active: boolean;
    is_global: boolean;
    books: Array<{ id: number }>;
};

const props = defineProps<{
    promotion: Promotion | null;
    typeOptions: Record<string, string>;
    books: Book[];
}>();

const isActive = ref(props.promotion ? props.promotion.is_active : true);

const isEdit = Boolean(props.promotion);
const action = isEdit ? PromotionController.update : PromotionController.store;
const submitArgs = isEdit ? props.promotion?.id : undefined;

const isGlobal = ref(
    props.promotion?.is_global ?? props.promotion?.books.length === 0,
);
const selectedBooks = ref<Set<number>>(
    new Set((props.promotion?.books ?? []).map((book) => book.id)),
);
const availableBooks = ref<Book[]>(props.books);
const bookSearch = ref('');
const bookSearchRequest = useHttp({ search: '' });
let bookSearchTimer: ReturnType<typeof setTimeout> | undefined;

function toggleGlobal(value: boolean) {
    isGlobal.value = value;

    if (value) {
        selectedBooks.value = new Set();
    }
}

function searchBooks() {
    clearTimeout(bookSearchTimer);

    if (!bookSearch.value.trim()) {
        availableBooks.value = props.books;

        return;
    }

    bookSearchTimer = setTimeout(() => {
        bookSearchRequest.get(
            bookOptions({ query: { search: bookSearch.value.trim() } }).url,
            {
                onSuccess: (data) => {
                    availableBooks.value = data as Book[];
                },
            },
        );
    }, 250);
}

function toggleBook(bookId: number) {
    isGlobal.value = false;
    const next = new Set(selectedBooks.value);

    if (next.has(bookId)) {
        next.delete(bookId);
    } else {
        next.add(bookId);
    }

    selectedBooks.value = next;
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Promo' : 'Buat Promo'" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{
                    isEdit
                        ? `Edit Promo: ${promotion?.promo_name}`
                        : 'Buat Promo Baru'
                }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Harga promo diterapkan otomatis selama periode aktif
            </p>
        </div>

        <Form
            v-bind="action.form(submitArgs as number)"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Aturan Promo</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="promo_name">Nama Promo *</Label>
                        <Input
                            id="promo_name"
                            name="promo_name"
                            :default-value="promotion?.promo_name ?? undefined"
                            placeholder="Contoh: Diskon Akhir Pekan"
                            required
                        />
                        <InputError :message="errors.promo_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="promo_type">Tipe Promo *</Label>
                        <Select
                            name="promo_type"
                            :default-value="
                                promotion?.promo_type ?? 'percentage'
                            "
                        >
                            <SelectTrigger id="promo_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in typeOptions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.promo_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="is_active">Status</Label>
                        <Label class="flex h-9 items-center gap-2">
                            <input
                                type="hidden"
                                name="is_active"
                                :value="isActive ? '1' : '0'"
                            />
                            <Checkbox v-model="isActive" />
                            Aktif
                        </Label>
                    </div>
                    <div class="grid gap-2">
                        <Label for="discount_percentage"
                            >Diskon Persentase (%) — utk tipe percentage &
                            bundle</Label
                        >
                        <Input
                            id="discount_percentage"
                            name="discount_percentage"
                            type="number"
                            min="1"
                            max="100"
                            :default-value="
                                promotion?.discount_percentage ?? undefined
                            "
                        />
                        <InputError :message="errors.discount_percentage" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="promo_value"
                            >Harga Tetap — utk tipe fixed</Label
                        >
                        <CurrencyInput
                            id="promo_value"
                            name="promo_value"
                            :default-value="promotion?.promo_value ?? undefined"
                            placeholder="50.000"
                        />
                        <InputError :message="errors.promo_value" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="bundle_qty"
                            >Qty Minimal Bundle — utk tipe bundle</Label
                        >
                        <Input
                            id="bundle_qty"
                            name="bundle_qty"
                            type="number"
                            min="2"
                            :default-value="promotion?.bundle_qty ?? undefined"
                        />
                        <InputError :message="errors.bundle_qty" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="start_date">Tanggal Mulai *</Label>
                        <Input
                            id="start_date"
                            name="start_date"
                            type="date"
                            :default-value="promotion?.start_date ?? undefined"
                            required
                        />
                        <InputError :message="errors.start_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="end_date">Tanggal Selesai *</Label>
                        <Input
                            id="end_date"
                            name="end_date"
                            type="date"
                            :default-value="promotion?.end_date ?? undefined"
                            required
                        />
                        <InputError :message="errors.end_date" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Buku yang Terkena Promo</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <input
                        type="hidden"
                        name="is_global"
                        :value="isGlobal ? '1' : '0'"
                    />
                    <Label class="flex items-center gap-2">
                        <Checkbox
                            :checked="isGlobal"
                            @update:checked="toggleGlobal"
                        />
                        Global — berlaku untuk semua buku
                    </Label>

                    <div v-if="!isGlobal" class="flex flex-col gap-3">
                        <Input
                            v-model="bookSearch"
                            placeholder="Cari judul atau SKU buku..."
                            @input="searchBooks"
                        />
                        <div class="grid gap-2 md:grid-cols-2">
                            <button
                                v-for="book in availableBooks"
                                :key="book.id"
                                type="button"
                                class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors"
                                :class="
                                    selectedBooks.has(book.id)
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted/50'
                                "
                                @click="toggleBook(book.id)"
                            >
                                <span class="truncate">{{ book.judul }}</span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                    >{{ book.kode_sku }}</span
                                >
                            </button>
                        </div>
                    </div>

                    <input
                        v-for="bookId in Array.from(selectedBooks)"
                        :key="bookId"
                        type="hidden"
                        name="book_ids[]"
                        :value="String(bookId)"
                    />
                    <p v-if="isGlobal" class="text-sm text-muted-foreground">
                        Semua buku aktif akan terkena promo ini.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ selectedBooks.size }} buku dipilih.
                    </p>
                    <InputError :message="errors.book_ids" />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{
                        processing
                            ? 'Menyimpan...'
                            : isEdit
                              ? 'Simpan Perubahan'
                              : 'Buat Promo'
                    }}
                </Button>
                <Button variant="outline" type="button" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
