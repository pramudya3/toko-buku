<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
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
import { create, edit, index as indexRoute } from '@/routes/admin/customers';

type Customer = {
    id: number;
    name: string;
    email: string;
    whatsapp_number: string | null;
    status_pelanggan: string;
    is_active: boolean;
    orders_count: number;
};

type Props = {
    customers: {
        data: Customer[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
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

const tierVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    reguler: 'neutral',
    bazaf: 'info',
    guru: 'success',
    reseller: 'warning',
};

const tierLabel: Record<string, string> = {
    reguler: 'Reguler',
    bazaf: 'Bazaf',
    guru: 'Guru',
    reseller: 'Reseller',
};
</script>

<template>
    <Head title="Pelanggan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Pelanggan</h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola data dan status tier pelanggan
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Buat Pelanggan
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
                placeholder="Cari nama, email, WhatsApp..."
            />
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>WhatsApp</TableHead>
                            <TableHead>Tier</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="customer in customers.data"
                            :key="customer.id"
                        >
                            <TableCell class="font-medium">{{
                                customer.name
                            }}</TableCell>
                            <TableCell>{{ customer.email }}</TableCell>
                            <TableCell class="tabular-nums">{{
                                customer.whatsapp_number ?? '—'
                            }}</TableCell>
                            <TableCell>
                                <StatusBadge
                                    :variant="
                                        tierVariant[
                                            customer.status_pelanggan
                                        ] ?? 'neutral'
                                    "
                                    :label="
                                        tierLabel[customer.status_pelanggan] ??
                                        customer.status_pelanggan
                                    "
                                />
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :variant="
                                        customer.is_active
                                            ? 'success'
                                            : 'danger'
                                    "
                                    :label="
                                        customer.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'
                                    "
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            icon: Pencil,
                                            href: edit(customer.id),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!customers.data.length"
                    title="Tidak ada pelanggan"
                    description="Pelanggan storefront akan muncul di sini."
                />
                <Pagination v-else :paginator="customers" />
            </CardContent>
        </Card>
    </div>
</template>
