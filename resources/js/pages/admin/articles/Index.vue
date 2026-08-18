<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Artikel', href: '/admin/articles' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Star, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateID } from '@/lib/date';
import { index as indexRoute } from '@/routes/admin/articles';

type Article = {
    id: string;
    judul: string;
    kategori_label: string;
    penulis: string | null;
    is_active: boolean;
    is_featured: boolean;
    published_at: string | null;
};

type Props = {
    articles: {
        data: Article[];
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
    {
        key: 'published_at',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-muted-foreground',
    },
    { key: 'judul', header: 'Judul', cellClass: 'font-medium' },
    { key: 'kategori_label', header: 'Kategori' },
    { key: 'featured', header: 'Unggulan', cellClass: 'text-center' },
    { key: 'status', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

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

const deletingArticle = ref<Article | null>(null);

function confirmDelete(article: Article) {
    deletingArticle.value = article;
}

function executeDelete() {
    if (!deletingArticle.value) {
        return;
    }

    const article = deletingArticle.value;

    deletingArticle.value = null;
    router.delete(ArticleController.destroy(article.id).url, {
        preserveScroll: true,
    });
}

const statusBadge = (row: Article) =>
    row.is_active
        ? { variant: 'success' as const, label: 'Aktif' }
        : { variant: 'neutral' as const, label: 'Nonaktif' };

function toggleFeatured(article: Article): void {
    router.patch(
        ArticleController.toggleFeatured(article.id).url,
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="Artikel" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Artikel</h1>
                <p class="text-sm text-muted-foreground">
                    Kelola konten menu Artikel di storefront
                </p>
            </div>
            <Button as-child>
                <Link :href="ArticleController.create().url">
                    <Plus class="size-4" />
                    Buat Artikel
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
                    placeholder="Cari judul..."
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
            :data="articles.data"
            :columns="columns"
            :paginator="articles"
            empty-title="Tidak ada artikel"
            empty-description="Buat artikel pertama untuk mengisi menu Artikel di storefront."
        >
            <template #cell-published_at="{ row }">
                <span v-if="row.published_at">
                    {{ formatDateID(row.published_at) }}
                </span>
                <span v-else class="text-muted-foreground">Draft</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :variant="statusBadge(row).variant"
                    :label="statusBadge(row).label"
                />
            </template>
            <template #cell-featured="{ row }">
                <span
                    class="inline-flex"
                    :title="
                        row.is_featured
                            ? 'Artikel unggulan'
                            : 'Bukan artikel unggulan'
                    "
                >
                    <Star
                        class="size-4"
                        :class="
                            row.is_featured
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground/40'
                        "
                        aria-hidden="true"
                    />
                    <span class="sr-only">
                        {{
                            row.is_featured
                                ? 'Artikel unggulan'
                                : 'Bukan artikel unggulan'
                        }}
                    </span>
                </span>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Edit',
                            href: ArticleController.edit(row.id).url,
                        },
                        {
                            label: row.is_featured
                                ? 'Batalkan Unggulan'
                                : 'Jadikan Unggulan',
                            onClick: () => toggleFeatured(row),
                        },
                        {
                            label: row.is_active ? 'Nonaktifkan' : 'Aktifkan',
                            onClick: () =>
                                router.patch(
                                    ArticleController.toggleActive(row.id).url,
                                    {
                                        preserveScroll: true,
                                    },
                                ),
                        },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () => confirmDelete(row),
                        },
                    ]"
                />
            </template>
        </DataTable>
    </div>

    <ConfirmDeleteDialog
        :open="!!deletingArticle"
        @update:open="
            (open) => {
                if (!open) deletingArticle = null;
            }
        "
        title="Hapus Artikel?"
        :description="
            deletingArticle
                ? `Artikel '${deletingArticle.judul}' akan dihapus dari storefront.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>
