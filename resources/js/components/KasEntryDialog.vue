<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle } from '@lucide/vue';
import { computed } from 'vue';
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
import { todayWIB } from '@/lib/date';

const props = withDefaults(
    defineProps<{
        open: boolean;
        mode: 'in' | 'out';
        /** Tanggal default (YYYY-MM-DD) — biasanya bulan yang sedang dibuka. */
        defaultDate?: string;
    }>(),
    {
        defaultDate: '',
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const isIn = computed(() => props.mode === 'in');
const title = computed(() =>
    isIn.value ? 'Catat Uang Masuk' : 'Catat Uang Keluar',
);
const description = computed(() =>
    isIn.value
        ? 'Pemasukan manual — penjualan langsung, pelunasan, dll.'
        : 'Pengeluaran manual — operasional, gaji, dll.',
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
                v-bind="CashFlowController.store.form()"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <p
                    v-if="
                        errors.entry_date || errors.amount || errors.description
                    "
                    class="rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                >
                    Periksa kembali isian formulir.
                </p>
                <input
                    type="hidden"
                    name="flow_type"
                    :value="isIn ? 'income' : 'expense'"
                />
                <div class="grid gap-2">
                    <Label for="entry_date">Tanggal *</Label>
                    <Input
                        id="entry_date"
                        name="entry_date"
                        type="date"
                        :default-value="defaultDate || todayWIB()"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="amount">Jumlah (Rp) *</Label>
                    <CurrencyInput
                        id="amount"
                        name="amount"
                        min="1"
                        required
                        placeholder="100.000"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="description">Keterangan *</Label>
                    <Input
                        id="description"
                        name="description"
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
                        {{ processing ? 'Menyimpan...' : 'Simpan' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
