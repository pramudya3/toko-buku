<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Staf', href: '/admin/users' },
        ],
    },
});

import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import type { RowAction } from '@/components/DataTableActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index as indexRoute } from '@/routes/admin/users';

type User = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
};

type Props = {
    users: {
        data: User[];
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
    { key: 'name', header: 'Nama', cellClass: 'font-medium' },
    { key: 'email', header: 'Email' },
    { key: 'is_active', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const currentUserId = (
    usePage().props.auth as unknown as { user: { id: string } | null }
).user?.id;

function rowActions(user: User): RowAction[] {
    const actions: RowAction[] = [
        {
            label: 'Edit',
            href: edit(user.id).url,
        },
    ];

    if (user.id !== currentUserId) {
        actions.push({
            label: user.is_active ? 'Nonaktifkan' : 'Aktifkan',
            onClick: () =>
                router.patch(UserController.toggleActive(user.id).url, {
                    preserveScroll: true,
                }),
        });
    }

    return actions;
}

const search = ref(props.filters.search ?? '');

const hasActiveFilters = computed(() => search.value !== '');

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { search: search.value || undefined },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    search.value = '';
    applyFilters();
}

watch([search], applyFilters);
</script>

<template>
    <Head title="Staf" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Staf</h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola akun yang dapat login sebagai admin
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Buat User
                </Link>
            </Button>
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
                    placeholder="Cari nama atau email..."
                />
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
            :data="users.data"
            :columns="columns"
            :paginator="users"
            empty-title="Tidak ada user"
            empty-description="User admin yang dapat login ke panel akan muncul di sini."
        >
            <template #cell-name="{ row }">
                <span class="inline-flex items-center gap-2">
                    {{ row.name }}
                    <Badge
                        v-if="row.id === currentUserId"
                        variant="outline"
                        class="border-gray-200 bg-gray-100 text-gray-600"
                    >
                        Anda
                    </Badge>
                </span>
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge
                    :variant="row.is_active ? 'success' : 'danger'"
                    :label="row.is_active ? 'Aktif' : 'Nonaktif'"
                />
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions :actions="rowActions(row)" />
            </template>
        </DataTable>
    </div>
</template>
