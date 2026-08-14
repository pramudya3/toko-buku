<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Pengajuan Stok', href: '/admin/stock-requests' },
            { title: 'Detail' },
        ],
    },
});

import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';

type RequestItem = {
    id: string;
    user: {
        id: string;
        name: string;
        email: string | null;
        whatsapp_number: string | null;
    };
    created_at: string;
};

type Props = {
    book: {
        id: string;
        judul: string;
        cover_url: string | null;
        stok: number;
    };
    requests: {
        data: RequestItem[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
};

defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'user', header: 'Pengaju' },
    {
        key: 'whatsapp',
        header: 'WhatsApp',
        cellClass: 'whitespace-nowrap tabular-nums',
    },
    { key: 'email', header: 'Email' },
    {
        key: 'created_at',
        header: 'Tanggal Pengajuan',
        cellClass: 'text-muted-foreground whitespace-nowrap',
    },
];
</script>

<template>
    <Head :title="`Pengajuan Stok — ${book.judul}`" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex items-center gap-4">
            <img
                v-if="book.cover_url"
                :src="book.cover_url"
                :alt="book.judul"
                class="size-16 shrink-0 rounded-lg border bg-muted object-cover"
            />
            <h1 class="min-w-0 text-xl font-semibold tracking-tight">
                {{ book.judul }}
            </h1>
        </div>

        <DataTable
            :data="requests.data"
            :columns="columns"
            :paginator="requests"
            empty-title="Belum ada pengaju"
            empty-description="Belum ada customer yang mengajukan buku ini."
        >
            <template #cell-user="{ row }">
                <span class="font-medium">{{ row.user.name }}</span>
            </template>
            <template #cell-whatsapp="{ row }">
                <span
                    :class="
                        row.user.whatsapp_number ? '' : 'text-muted-foreground'
                    "
                >
                    {{ row.user.whatsapp_number ?? '—' }}
                </span>
            </template>
            <template #cell-email="{ row }">
                <span :class="row.user.email ? '' : 'text-muted-foreground'">
                    {{ row.user.email ?? '—' }}
                </span>
            </template>
            <template #cell-created_at="{ row }">
                {{
                    new Date(row.created_at).toLocaleString('id-ID', {
                        timeZone: 'Asia/Jakarta',
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    })
                }}
            </template>
        </DataTable>
    </div>
</template>
