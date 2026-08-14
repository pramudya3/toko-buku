<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Pengajuan Stok', href: '/admin/stock-requests' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import { Input } from '@/components/ui/input';
import { index as indexRoute, show } from '@/routes/admin/stock-requests';

type RequestRow = {
    book: {
        id: string;
        judul: string;
        cover_url: string | null;
        stok: number;
    };
    total_requests: number;
    last_requested_at: string;
};

type Props = {
    requests: {
        data: RequestRow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'book', header: 'Buku' },
    { key: 'stok', header: 'Stok', cellClass: 'text-center tabular-nums' },
    {
        key: 'total_requests',
        header: 'Pengaju',
        cellClass: 'text-center tabular-nums',
    },
    {
        key: 'last_requested_at',
        header: 'Terakhir Diajukan',
        cellClass: 'text-center text-muted-foreground whitespace-nowrap',
    },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const search = ref(props.filters.search ?? '');

const hasActiveFilters = computed(() => search.value !== '');

let searchTimer: ReturnType<typeof setTimeout> | undefined;

function applySearch(): void {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { search: search.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 350);
}

function resetSearch(): void {
    search.value = '';
    applySearch();
}

watch(search, applySearch);
</script>

<template>
    <Head title="Pengajuan Stok" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Pengajuan Stok</h1>
            <p class="text-sm text-muted-foreground">
                Buku yang diajukan customer saat stok habis
            </p>
        </div>

        <div
            class="flex w-full flex-col divide-y divide-border overflow-hidden rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
            <div class="relative flex items-center">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari judul buku..."
                />
            </div>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="flex h-11 w-full items-center justify-center gap-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-destructive md:h-9 md:w-9"
                title="Hapus filter"
                aria-label="Hapus filter"
                @click="resetSearch"
            >
                <X class="size-4" />
                <span class="md:hidden">Hapus filter</span>
            </button>
        </div>

        <DataTable
            :data="requests.data"
            :columns="columns"
            :paginator="requests"
            empty-title="Belum ada pengajuan stok"
            empty-description="Pengajuan dari customer akan tampil di sini."
        >
            <template #cell-book="{ row }">
                <div class="flex items-center gap-3">
                    <img
                        v-if="row.book.cover_url"
                        :src="row.book.cover_url"
                        :alt="row.book.judul"
                        class="size-10 shrink-0 rounded-md border bg-muted object-cover"
                    />
                    <span
                        v-else
                        class="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted text-xs text-muted-foreground"
                    >
                        -
                    </span>
                    <span class="line-clamp-2 font-medium">
                        {{ row.book.judul }}
                    </span>
                </div>
            </template>
            <template #cell-stok="{ row }">
                <span
                    :class="
                        row.book.stok <= 0
                            ? 'font-medium text-destructive'
                            : 'font-medium text-emerald-600'
                    "
                >
                    {{ row.book.stok }}
                </span>
            </template>
            <template #cell-total_requests="{ row }">
                <span class="font-semibold tabular-nums">
                    {{ row.total_requests }}
                </span>
            </template>
            <template #cell-last_requested_at="{ row }">
                {{
                    new Date(row.last_requested_at).toLocaleDateString(
                        'id-ID',
                        {
                            timeZone: 'Asia/Jakarta',
                        },
                    )
                }}
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Detail',
                            href: show(row.book.id).url,
                        },
                    ]"
                />
            </template>
        </DataTable>
    </div>
</template>
