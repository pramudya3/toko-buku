<script setup lang="ts">
/**
 * ArticleFilterSidebar — sidebar filter artikel proto-d.
 *
 * Kategori jamak (OR), bulan & tahun (gabungan), plus tombol bersihkan.
 * Komponen presentasional murni: halaman yang menangani navigasi Inertia.
 */
import { computed } from 'vue';

type CategoryFacet = {
    id: string;
    nama: string;
    articles_count: number;
};

type DateFacet = {
    year: number;
    month: number;
    count: number;
};

const props = defineProps<{
    categories: CategoryFacet[];
    dateFacets: DateFacet[];
    categoriesSelected: string[];
    month: number | null;
    year: number | null;
    /** Sedang menavigasi (filter terkunci, tampil spinner ringan). */
    loading?: boolean;
}>();

const emit = defineEmits<{
    'update:categories': [value: string[]];
    'update:month': [value: number | null];
    'update:year': [value: number | null];
    clear: [];
}>();

const MONTHS_SHORT = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

const activeCount = computed(
    () =>
        props.categoriesSelected.length +
        (props.month !== null ? 1 : 0) +
        (props.year !== null ? 1 : 0),
);

/** Tahun unik (terbaru dulu) dengan total artikel. */
const years = computed(() => {
    const map = new Map<number, number>();

    for (const facet of props.dateFacets) {
        map.set(facet.year, (map.get(facet.year) ?? 0) + facet.count);
    }

    return [...map.entries()]
        .map(([year, count]) => ({ year, count }))
        .sort((a, b) => b.year - a.year);
});

/** Bulan yang tersedia — dibatasi ke tahun terpilih bila ada. */
const months = computed(() => {
    const facets = props.year
        ? props.dateFacets.filter((f) => f.year === props.year)
        : props.dateFacets;

    return facets.map((f) => ({ ...f })).sort((a, b) => b.month - a.month);
});

function toggleCategory(id: string): void {
    const next = props.categoriesSelected.includes(id)
        ? props.categoriesSelected.filter((c) => c !== id)
        : [...props.categoriesSelected, id];

    emit('update:categories', next);
}

function selectMonth(value: string): void {
    emit('update:month', value === '' ? null : Number(value));
}

function selectYear(value: string): void {
    const next = value === '' ? null : Number(value);

    emit('update:year', next);

    // Ganti tahun → bulan terpilih bisa jadi tidak tersedia di tahun baru.
    if (next !== null && props.month !== null) {
        const available = props.dateFacets.some(
            (f) => f.year === next && f.month === props.month,
        );

        if (!available) {
            emit('update:month', null);
        }
    }
}
</script>

<template>
    <div
        class="rounded-lg border-2 border-flat-border bg-white p-5"
        aria-label="Filter artikel"
    >
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold">Filter</h3>
            <span
                v-if="activeCount > 0"
                class="rounded-full bg-flat-primary px-2.5 py-0.5 text-[11px] font-semibold text-white"
                aria-label="Jumlah filter aktif"
            >
                {{ activeCount }}
            </span>
        </div>

        <!-- Kategori (jamak, OR) -->
        <fieldset class="mt-5" :disabled="loading">
            <legend class="text-xs font-medium text-gray-500">Kategori</legend>
            <div class="mt-2 space-y-1">
                <label
                    v-for="cat in categories"
                    :key="cat.id"
                    class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-md px-2 text-sm transition-colors hover:bg-flat-muted"
                >
                    <input
                        type="checkbox"
                        class="size-4 shrink-0 rounded border-flat-border"
                        :checked="categoriesSelected.includes(cat.id)"
                        :value="cat.id"
                        @change="toggleCategory(cat.id)"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ cat.nama }}</span>
                    <span
                        class="text-xs text-gray-500 tabular-nums"
                        aria-label="Jumlah artikel"
                        >{{ cat.articles_count }}</span
                    >
                </label>
                <p
                    v-if="categories.length === 0"
                    class="px-2 py-1 text-xs text-gray-500"
                >
                    Belum ada kategori.
                </p>
            </div>
        </fieldset>

        <!-- Bulan & tahun -->
        <div class="mt-5 grid grid-cols-2 gap-3">
            <label class="block" :class="{ 'opacity-60': loading }">
                <span class="text-xs font-medium text-gray-500">Bulan</span>
                <select
                    class="mt-2 min-h-12 w-full rounded-md border-2 border-transparent bg-flat-muted px-3 text-sm transition-colors outline-none focus:border-flat-primary focus:bg-white disabled:opacity-60"
                    :value="month ?? ''"
                    :disabled="loading || months.length === 0"
                    aria-label="Filter bulan"
                    @change="
                        selectMonth(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option value="">Semua bulan</option>
                    <option v-for="m in months" :key="m.month" :value="m.month">
                        {{ MONTHS_SHORT[m.month - 1] }} ({{ m.count }})
                    </option>
                </select>
            </label>
            <label class="block" :class="{ 'opacity-60': loading }">
                <span class="text-xs font-medium text-gray-500">Tahun</span>
                <select
                    class="mt-2 min-h-12 w-full rounded-md border-2 border-transparent bg-flat-muted px-3 text-sm transition-colors outline-none focus:border-flat-primary focus:bg-white disabled:opacity-60"
                    :value="year ?? ''"
                    :disabled="loading || years.length === 0"
                    aria-label="Filter tahun"
                    @change="
                        selectYear(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option value="">Semua tahun</option>
                    <option v-for="y in years" :key="y.year" :value="y.year">
                        {{ y.year }} ({{ y.count }})
                    </option>
                </select>
            </label>
        </div>

        <!-- Bersihkan -->
        <button
            v-if="activeCount > 0"
            type="button"
            class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-md border-2 border-flat-border bg-white text-sm font-medium text-flat-ink transition-all duration-200 hover:bg-flat-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-flat-primary disabled:opacity-60"
            :disabled="loading"
            @click="emit('clear')"
        >
            Bersihkan filter
        </button>
    </div>
</template>
