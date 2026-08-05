<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import PromotionController from '@/actions/App/Http/Controllers/Admin/PromotionController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit, index as indexRoute } from '@/routes/admin/promotions';

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
    books_count: number;
};

type Props = {
    promotions: {
        data: Promotion[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
    typeOptions: Record<string, string>;
};

const props = defineProps<Props>();

const search = ref(props.filters.search ?? '');

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { search: search.value || undefined },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
});

const typeLabel = (promo: Promotion) => {
    switch (promo.promo_type) {
        case 'percentage':
            return `${promo.discount_percentage}%`;
        case 'fixed':
            return `Rp ${promo.promo_value?.toLocaleString('id-ID')}`;
        case 'bundle':
            return `Beli ≥ ${promo.bundle_qty} → ${promo.discount_percentage}%`;
        default:
            return promo.promo_type;
    }
};

const typeVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    percentage: 'info',
    fixed: 'warning',
    bundle: 'success',
};

function confirmDelete(promo: Promotion) {
    deletingPromo.value = promo;
}

const deletingPromo = ref<Promotion | null>(null);

function executeDelete() {
    if (!deletingPromo.value) {
        return;
    }

    const promo = deletingPromo.value;

    deletingPromo.value = null;
    router.delete(PromotionController.destroy(promo.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Promosi" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Promosi</h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola promo, diskon, dan bundle item
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Buat Promo
                </Link>
            </Button>
        </div>

        <div class="relative max-w-sm">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-9"
                placeholder="Cari promo..."
            />
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Tipe</TableHead>
                            <TableHead>Nilai</TableHead>
                            <TableHead>Periode</TableHead>
                            <TableHead>Buku</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="promo in promotions.data"
                            :key="promo.id"
                        >
                            <TableCell class="font-medium">{{
                                promo.promo_name
                            }}</TableCell>
                            <TableCell>
                                <StatusBadge
                                    :variant="
                                        typeVariant[promo.promo_type] ??
                                        'neutral'
                                    "
                                    :label="
                                        typeOptions[promo.promo_type] ??
                                        promo.promo_type
                                    "
                                />
                            </TableCell>
                            <TableCell class="tabular-nums">{{
                                typeLabel(promo)
                            }}</TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ promo.start_date }} → {{ promo.end_date }}
                            </TableCell>
                            <TableCell>{{
                                promo.books_count === 0
                                    ? 'Global'
                                    : `${promo.books_count} buku`
                            }}</TableCell>
                            <TableCell>
                                <Form
                                    v-bind="
                                        PromotionController.toggle.form(
                                            promo.id,
                                        )
                                    "
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="processing"
                                    >
                                        <StatusBadge
                                            :variant="
                                                promo.is_active
                                                    ? 'success'
                                                    : 'danger'
                                            "
                                            :label="
                                                promo.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'
                                            "
                                        />
                                    </Button>
                                </Form>
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            icon: Pencil,
                                            href: edit(promo.id),
                                        },
                                        {
                                            label: 'Hapus',
                                            icon: Trash2,
                                            variant: 'destructive',
                                            onClick: () => confirmDelete(promo),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!promotions.data.length"
                    title="Belum ada promo"
                    description="Buat promosi pertama untuk menarik pembeli."
                />
                <Pagination v-else :paginator="promotions" />
            </CardContent>
        </Card>

        <ConfirmDeleteDialog
            :open="!!deletingPromo"
            @update:open="(open) => { if (!open) deletingPromo = null }"
            title="Hapus Promo?"
            :description="
                deletingPromo
                    ? `Promo '${deletingPromo.promo_name}' akan dihapus.`
                    : ''
            "
            @confirm="executeDelete"
        />
    </div>
</template>
