<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CashFlowController from '@/actions/App/Http/Controllers/Admin/CashFlowController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import { Button } from '@/components/ui/button';
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
import { todayWIB } from '@/lib/date';

type KasSub = { id: string; nama: string };
type KasCat = {
    id: string;
    nama: string;
    sub_categories?: KasSub[];
    subCategories?: KasSub[];
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        mode: 'in' | 'out';
        /** Tanggal default (YYYY-MM-DD) — biasanya bulan yang sedang dibuka. */
        defaultDate?: string;
        /** Mode edit — jika diisi, dialog akan PUT ke kas.update */
        cashFlow?: {
            id: string;
            entry_date: string;
            amount: number;
            description: string;
            flow_type: string;
            kas_category_id?: string | null;
            kas_sub_category_id?: string | null;
        } | null;
        kasCategories?: KasCat[];
    }>(),
    {
        defaultDate: '',
        cashFlow: null,
        kasCategories: () => [],
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const isIn = computed(() => props.mode === 'in');
const isEdit = computed(() => props.cashFlow !== null);
const title = computed(() => {
    if (isEdit.value) {
        return 'Ubah Pencatatan';
    }

    return isIn.value ? 'Catat Pemasukan' : 'Catat Pengeluaran';
});
const description = computed(() => {
    if (isEdit.value) {
        return 'Perbarui pencatatan kas manual sebelum periode ditutup.';
    }

    return isIn.value
        ? 'Pencatatan pemasukan kas manual untuk periode terpilih.'
        : 'Pencatatan pengeluaran kas manual untuk periode terpilih.';
});
const formAction = computed(() =>
    isEdit.value && props.cashFlow
        ? CashFlowController.update.form(props.cashFlow.id)
        : CashFlowController.store.form(),
);

const selectedCategory = ref<string>(props.cashFlow?.kas_category_id ?? '');
const selectedSub = ref<string>(props.cashFlow?.kas_sub_category_id ?? '');

const subOptions = computed(() => {
    const cat = props.kasCategories.find(
        (c) => c.id === selectedCategory.value,
    );

    return cat ? (cat.sub_categories ?? cat.subCategories ?? []) : [];
});

watch(
    () => props.cashFlow,
    (val) => {
        selectedCategory.value = val?.kas_category_id ?? '';
        selectedSub.value = val?.kas_sub_category_id ?? '';
    },
);

watch(selectedCategory, () => {
    // Reset sub jika kategori ganti dan sub tidak ada di opsi baru
    if (!subOptions.value.some((s) => s.id === selectedSub.value)) {
        selectedSub.value = '';
    }
});

watch(
    () => props.open,
    (open) => {
        if (open && !isEdit.value) {
            selectedCategory.value = '';
            selectedSub.value = '';
        }

        if (open && isEdit.value) {
            selectedCategory.value = props.cashFlow?.kas_category_id ?? '';
            selectedSub.value = props.cashFlow?.kas_sub_category_id ?? '';
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ArrowDownCircle
                        v-if="isIn"
                        class="size-4 text-green-600"
                    />
                    <ArrowUpCircle v-else class="size-4 text-destructive" />
                    {{ title }}
                </DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="formAction"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <p
                    v-if="
                        errors.entry_date ||
                        errors.amount ||
                        errors.description ||
                        errors.kas_category_id ||
                        errors.kas_sub_category_id
                    "
                    class="rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                >
                    Mohon periksa kembali isian formulir.
                </p>
                <p
                    v-if="errors.kas_category_id"
                    class="text-sm text-destructive"
                >
                    {{ errors.kas_category_id }}
                </p>
                <p
                    v-if="errors.kas_sub_category_id"
                    class="text-sm text-destructive"
                >
                    {{ errors.kas_sub_category_id }}
                </p>
                <input
                    type="hidden"
                    name="flow_type"
                    :value="isIn ? 'income' : 'expense'"
                />
                <div class="grid gap-2">
                    <Label for="entry_date">Tanggal Pencatatan *</Label>
                    <Input
                        id="entry_date"
                        name="entry_date"
                        type="date"
                        :default-value="
                            cashFlow?.entry_date ?? defaultDate ?? todayWIB()
                        "
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="amount">Nominal (Rp) *</Label>
                    <CurrencyInput
                        id="amount"
                        name="amount"
                        :model-value="cashFlow?.amount"
                        min="1"
                        required
                        placeholder="100.000"
                    />
                </div>
                <template v-if="!isIn">
                    <div class="grid gap-2">
                        <Label>Kategori *</Label>
                        <Select v-model="selectedCategory">
                            <SelectTrigger>
                                <SelectValue
                                    placeholder="Pilih kategori pengeluaran"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="cat in kasCategories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="kas_category_id"
                            :value="selectedCategory"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Subkategori *</Label>
                        <Select
                            v-model="selectedSub"
                            :disabled="!selectedCategory"
                        >
                            <SelectTrigger>
                                <SelectValue
                                    :placeholder="
                                        selectedCategory
                                            ? 'Pilih subkategori'
                                            : 'Pilih kategori terlebih dahulu'
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="sub in subOptions"
                                    :key="sub.id"
                                    :value="sub.id"
                                >
                                    {{ sub.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="kas_sub_category_id"
                            :value="selectedSub"
                        />
                    </div>
                </template>
                <div class="grid gap-2">
                    <Label for="description">Keterangan Pencatatan *</Label>
                    <Input
                        id="description"
                        name="description"
                        :default-value="cashFlow?.description ?? ''"
                        required
                        placeholder="Mis. Penjualan langsung / Pembelian ATK"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{
                            processing
                                ? isEdit
                                    ? 'Memperbarui…'
                                    : 'Mencatat…'
                                : 'Catat'
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
