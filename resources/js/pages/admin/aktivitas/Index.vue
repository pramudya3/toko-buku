<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Log Aktivitas', href: '/admin/aktivitas' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as indexRoute } from '@/routes/admin/aktivitas';

type ActivityRow = {
    id: string;
    created_at: string;
    description: string;
    action: string;
    ip_address: string | null;
    user: { id: string; name: string } | null;
};

const props = defineProps<{
    logs: {
        data: ActivityRow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        from?: string;
        to?: string;
        user_id?: string;
        action?: string;
        search?: string;
    };
    userOptions: Array<{ id: string; name: string }>;
    actionOptions: Record<string, string>;
}>();

const columns: DataTableColumn[] = [
    {
        key: 'created_at',
        header: 'Waktu',
        cellClass: 'whitespace-nowrap text-muted-foreground',
    },
    { key: 'user', header: 'User' },
    { key: 'action', header: 'Aktivitas' },
    { key: 'description', header: 'Keterangan' },
    { key: 'ip_address', header: 'IP', cellClass: 'font-mono' },
];

const allUsers = 'all';
const allActions = 'all';

const search = ref(props.filters.search ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const userId = ref(props.filters.user_id ?? allUsers);
const action = ref(props.filters.action ?? allActions);

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialSearch = props.filters.search ?? '';
const initialFrom = props.filters.from ?? '';
const initialTo = props.filters.to ?? '';
const initialUserId = props.filters.user_id ?? allUsers;
const initialAction = props.filters.action ?? allActions;

const hasActiveFilters = computed(
    () =>
        search.value !== initialSearch ||
        from.value !== initialFrom ||
        to.value !== initialTo ||
        userId.value !== initialUserId ||
        action.value !== initialAction,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                from: from.value || undefined,
                to: to.value || undefined,
                user_id: userId.value === allUsers ? undefined : userId.value,
                action: action.value === allActions ? undefined : action.value,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    search.value = initialSearch;
    from.value = initialFrom;
    to.value = initialTo;
    userId.value = initialUserId;
    action.value = initialAction;
    applyFilters();
}

watch([search, from, to, userId, action], applyFilters);

function formatTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Log Aktivitas" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Log Aktivitas</h1>
            <p class="text-sm text-muted-foreground">
                Riwayat login, perubahan pengaturan, dan aktivitas penting
                lainnya
            </p>
        </div>

        <!-- Filter -->
        <div
            class="flex w-full flex-col divide-y divide-border overflow-hidden rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
            <div class="relative flex items-center">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari keterangan..."
                />
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Dari
                </p>
                <Input
                    v-model="from"
                    type="date"
                    class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-36"
                    aria-label="Dari tanggal"
                />
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Sampai
                </p>
                <Input
                    v-model="to"
                    type="date"
                    class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-36"
                    aria-label="Sampai tanggal"
                />
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    User
                </p>
                <Select v-model="userId">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-44"
                    >
                        <SelectValue placeholder="Semua user" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allUsers">Semua user</SelectItem>
                        <SelectItem
                            v-for="user in userOptions"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Aktivitas
                </p>
                <Select v-model="action">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-44"
                    >
                        <SelectValue placeholder="Semua aktivitas" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allActions"
                            >Semua aktivitas</SelectItem
                        >
                        <SelectItem
                            v-for="(label, value) in actionOptions"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <button
                v-if="hasActiveFilters"
                type="button"
                class="flex h-11 w-full items-center justify-center gap-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-destructive md:h-9 md:w-9"
                title="Hapus filter"
                aria-label="Hapus filter"
                @click="resetFilters"
            >
                <X class="size-4" />
                <span class="md:hidden">Hapus filter</span>
            </button>
        </div>

        <DataTable
            :data="logs.data"
            :columns="columns"
            :paginator="logs"
            empty-title="Belum ada aktivitas"
            empty-description="Login, perubahan pengaturan, dan aktivitas penting akan tercatat di sini."
        >
            <template #cell-created_at="{ row }">
                {{ formatTime(row.created_at) }}
            </template>
            <template #cell-user="{ row }">
                {{ row.user?.name ?? '—' }}
            </template>
            <template #cell-action="{ row }">
                <span
                    class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium"
                    :class="
                        row.action.startsWith('auth.')
                            ? 'border-blue-200 bg-blue-100 text-blue-700'
                            : row.action.startsWith('settings')
                              ? 'border-amber-200 bg-amber-100 text-amber-700'
                              : 'border-gray-200 bg-gray-100 text-gray-600'
                    "
                >
                    {{ actionOptions[row.action] ?? row.action }}
                </span>
            </template>
            <template #cell-ip_address="{ row }">
                {{ row.ip_address ?? '—' }}
            </template>
        </DataTable>
    </div>
</template>
