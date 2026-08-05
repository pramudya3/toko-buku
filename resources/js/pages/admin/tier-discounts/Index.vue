<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import TierDiscountController from '@/actions/App/Http/Controllers/Admin/TierDiscountController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

type TierDiscount = {
    id: number;
    tier: string;
    min_qty: number;
    max_qty: number | null;
    discount_percent: number;
};

type Props = {
    tierDiscounts: Record<string, TierDiscount[]>;
    tierOptions: Record<string, string>;
};

const props = defineProps<Props>();

const formDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const selectedTier = ref('');
const editingDiscount = ref<TierDiscount | null>(null);
const deletingDiscount = ref<TierDiscount | null>(null);
const openGroups = ref<Record<string, boolean>>({});
const allTiersFilter = '__all_tiers__';
const filterTier = ref(allTiersFilter);

function openCreate(tier?: string) {
    editingDiscount.value = null;
    selectedTier.value = tier ?? '';
    formDialogOpen.value = true;
}

function openEdit(discount: TierDiscount) {
    editingDiscount.value = discount;
    selectedTier.value = discount.tier;
    formDialogOpen.value = true;
}

function openDelete(discount: TierDiscount) {
    deletingDiscount.value = discount;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    if (deletingDiscount.value) {
        router.delete(TierDiscountController.destroy(deletingDiscount.value.id).url);
        deleteDialogOpen.value = false;
        deletingDiscount.value = null;
    }
}

function getTierLabel(tier: string): string {
    return props.tierOptions[tier] ?? tier;
}

function formatQtyRange(discount: TierDiscount): string {
    if (discount.max_qty) {
        return `${discount.min_qty} - ${discount.max_qty}`;
    }

    return `${discount.min_qty}+`;
}

function toggleGroup(tier: string) {
    openGroups.value[tier] = !openGroups.value[tier];
}

const groupedDiscounts = computed(() => {
    const groups: Record<string, TierDiscount[]> = {};

    for (const [tier, discounts] of Object.entries(props.tierDiscounts)) {
        groups[tier] = [...discounts].sort((a, b) => a.min_qty - b.min_qty);
    }

    return groups;
});

const tierOrder = ['reguler', 'bazaf', 'guru', 'reseller'];
const sortedTiers = computed(() => {
    const tiers = tierOrder.filter((t) => groupedDiscounts.value[t]?.length);

    if (filterTier.value === allTiersFilter) {
        return tiers;
    }

    return tiers.filter((t) => t === filterTier.value);
});

// Init all groups as open
for (const tier of Object.keys(groupedDiscounts.value)) {
    if (!(tier in openGroups.value)) {
        openGroups.value[tier] = true;
    }
}
</script>

<template>
    <Head title="Tier Discount" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Tier Discount</h1>
                <p class="text-sm text-muted-foreground">
                    Atur diskon berdasarkan tier pelanggan dan jumlah pembelian
                </p>
            </div>
            <Button @click="openCreate()">
                <Plus class="size-4" />
                Tambah
            </Button>
        </div>

        <div class="flex items-center gap-2">
            <Select v-model="filterTier">
                <SelectTrigger class="w-48">
                    <SelectValue placeholder="Semua tier" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="allTiersFilter">Semua tier</SelectItem>
                    <SelectItem
                        v-for="(label, value) in tierOptions"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Card v-if="sortedTiers.length">
            <CardContent class="p-0">
                <div class="divide-y">
                    <div v-for="tier in sortedTiers" :key="tier">
                        <div
                            class="flex items-center justify-between px-4 py-3 hover:bg-muted/30 cursor-pointer"
                            @click="toggleGroup(tier)"
                        >
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <ChevronDown
                                    v-if="openGroups[tier]"
                                    class="size-4 text-muted-foreground"
                                />
                                <ChevronRight
                                    v-else
                                    class="size-4 text-muted-foreground"
                                />
                                {{ getTierLabel(tier) }}
                                <span class="text-xs text-muted-foreground font-normal">
                                    ({{ groupedDiscounts[tier].length }})
                                </span>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click.stop="openCreate(tier)"
                            >
                                <Plus class="size-3 mr-1" />
                                tambah
                            </Button>
                        </div>

                        <div v-if="openGroups[tier]" class="border-t">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Range Qty</TableHead>
                                        <TableHead>Diskon</TableHead>
                                        <TableHead class="w-12"><span class="sr-only">Aksi</span></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="discount in groupedDiscounts[tier]"
                                        :key="discount.id"
                                    >
                                        <TableCell class="font-mono text-xs">
                                            {{ formatQtyRange(discount) }}
                                        </TableCell>
                                        <TableCell>
                                            {{ discount.discount_percent }}%
                                        </TableCell>
                                        <TableCell>
                                            <DataTableActions
                                                :actions="[
                                                    {
                                                        label: 'Edit',
                                                        icon: Pencil,
                                                        onClick: () =>
                                                            openEdit(discount),
                                                    },
                                                    {
                                                        label: 'Hapus',
                                                        icon: Trash2,
                                                        variant: 'destructive',
                                                        onClick: () =>
                                                            openDelete(discount),
                                                    },
                                                ]"
                                            />
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <EmptyState
            v-else
            title="Belum ada tier discount"
            description="Klik 'Tambah' untuk menambahkan rule diskon."
        />

        <!-- Form Dialog -->
        <Dialog v-model:open="formDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{
                        editingDiscount ? 'Edit Rule' : 'Tambah Rule'
                    }}</DialogTitle>
                    <DialogDescription>
                        Atur diskon berdasarkan tier dan range jumlah pembelian.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingDiscount"
                    :action="TierDiscountController.update(editingDiscount.id).url"
                    method="post"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="formDialogOpen = false"
                >
                    <input type="hidden" name="_method" value="put" />
                    <input type="hidden" name="tier" :value="editingDiscount.tier" />

                    <div class="grid gap-2">
                        <Label>Tier</Label>
                        <div class="h-9 rounded-md border bg-muted px-3 text-sm flex items-center">
                            {{ getTierLabel(editingDiscount.tier) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="min_qty">Min Qty</Label>
                            <Input
                                id="min_qty"
                                name="min_qty"
                                type="number"
                                min="1"
                                required
                                :default-value="editingDiscount.min_qty"
                            />
                            <span v-if="errors.min_qty" class="text-sm text-destructive">
                                {{ errors.min_qty }}
                            </span>
                        </div>

                        <div class="grid gap-2">
                            <Label for="max_qty">Max Qty</Label>
                            <Input
                                id="max_qty"
                                name="max_qty"
                                type="number"
                                min="1"
                                placeholder="Kosong = tanpa batas"
                                :default-value="editingDiscount.max_qty ?? ''"
                            />
                            <span v-if="errors.max_qty" class="text-sm text-destructive">
                                {{ errors.max_qty }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="discount_percent">Diskon (%)</Label>
                        <Input
                            id="discount_percent"
                            name="discount_percent"
                            type="number"
                            min="0"
                            max="100"
                            required
                            :default-value="editingDiscount.discount_percent"
                        />
                        <span v-if="errors.discount_percent" class="text-sm text-destructive">
                            {{ errors.discount_percent }}
                        </span>
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            Simpan
                        </Button>
                    </DialogFooter>
                </Form>

                <Form
                    v-else
                    :action="TierDiscountController.store().url"
                    method="post"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="formDialogOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="tier">Tier</Label>
                        <select
                            name="tier"
                            v-model="selectedTier"
                            required
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="" disabled>Pilih tier</option>
                            <option v-for="(label, value) in tierOptions" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <span v-if="errors.tier" class="text-sm text-destructive">
                            {{ errors.tier }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="min_qty">Min Qty</Label>
                            <Input
                                id="min_qty"
                                name="min_qty"
                                type="number"
                                min="1"
                                required
                            />
                            <span v-if="errors.min_qty" class="text-sm text-destructive">
                                {{ errors.min_qty }}
                            </span>
                        </div>

                        <div class="grid gap-2">
                            <Label for="max_qty">Max Qty</Label>
                            <Input
                                id="max_qty"
                                name="max_qty"
                                type="number"
                                min="1"
                                placeholder="Kosong = tanpa batas"
                            />
                            <span v-if="errors.max_qty" class="text-sm text-destructive">
                                {{ errors.max_qty }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="discount_percent">Diskon (%)</Label>
                        <Input
                            id="discount_percent"
                            name="discount_percent"
                            type="number"
                            min="0"
                            max="100"
                            required
                        />
                        <span v-if="errors.discount_percent" class="text-sm text-destructive">
                            {{ errors.discount_percent }}
                        </span>
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            Simpan
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation -->
        <ConfirmDeleteDialog
            v-model:open="deleteDialogOpen"
            title="Hapus Rule?"
            :description="
                deletingDiscount
                    ? `${getTierLabel(deletingDiscount.tier)} — ${formatQtyRange(deletingDiscount)} → ${deletingDiscount.discount_percent}%`
                    : ''
            "
            @confirm="confirmDelete"
        />
    </div>
</template>
