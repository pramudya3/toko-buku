<script setup lang="ts">
/**
 * Beranda editorial proto-d — /pcd.
 * Artikel nyata dari CMS (aktif & terbit) + data toko nyata: buku unggulan,
 * promo, dan paket dari backend.
 *
 * Klik artikel membuka halaman baca /pcd/artikel/{slug}; filter tanggal ada
 * di sidebar (samping daftar).
 */
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ShoppingCart, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import ArticleThumb from '@/components/storefront-pcd/ArticleThumb.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { formatDateID, todayWIB } from '@/lib/date';
import { bookShowUrl } from '@/lib/slug';
import { show as articleShowRoute } from '@/routes/pcd/articles';
import { catalog as catalogUrl, show as showRoute } from '@/routes/pcd/books';
import { show as bundleShowRoute } from '@/routes/pcd/bundles';

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    is_preorder: boolean;
    category: { id: string; nama: string } | null;
    price_breakdown?: {
        original_price: number;
        promo_discount: number;
        final_price: number;
        promo_name: string | null;
    } | null;
};

type BundleBook = {
    id: string;
    judul: string;
    cover_url: string | null;
    stok: number;
};

type Bundle = {
    id: string;
    promo_name: string;
    discount_percent: number;
    books: BundleBook[];
    total_original: number;
    total_discount: number;
    total_final: number;
};

type Promo = {
    id: string;
    promo_name: string;
    promo_type: 'percentage' | 'fixed';
    discount_percentage: number | null;
    promo_value: number | null;
    start_date: string;
    end_date: string;
    is_global: boolean;
    books: Array<{ id: string; judul: string; cover_url: string | null }>;
};

type Motif =
    'stack' | 'manuscript' | 'readers' | 'quote' | 'shelf' | 'pencil' | 'lamp';

type ArticleCard = {
    id: string;
    judul: string;
    slug: string;
    kategori_id: string | null;
    kategori_label: string;
    penulis: string | null;
    ringkasan: string;
    published_at: string | null;
    motif: Motif | null;
    cover_url: string | null;
    menit: number;
};

const props = defineProps<{
    books: Book[];
    categories: Array<{ id: string; nama: string }>;
    bundles: Bundle[];
    promos: Promo[];
    articles: ArticleCard[];
}>();

defineOptions({ layout: StorefrontPcdLayout });

// ── Artikel (data nyata dari CMS, terurut terbaru) ──
const featuredArticle = computed<ArticleCard | null>(() => {
    return props.articles[0] ?? null;
});

const listArticles = computed<ArticleCard[]>(() => props.articles.slice(1));

const articleCategories = computed<Array<{ id: string; nama: string }>>(() => {
    const seen = new Set<string>();
    const cats: Array<{ id: string; nama: string }> = [];

    for (const a of props.articles) {
        if (a.kategori_id && !seen.has(a.kategori_id)) {
            seen.add(a.kategori_id);
            cats.push({ id: a.kategori_id, nama: a.kategori_label });
        }
    }

    return [{ id: 'all', nama: 'Semua' }, ...cats];
});

const activeCat = ref('all');
const period = ref('all');
const exactDate = ref('');

const parseISO = (s: string) => {
    const [y, m, d] = s.split('-').map(Number);

    return new Date(y, m - 1, d);
};

const dateMin = computed(() => {
    const dates = props.articles.map((a) => a.published_at).filter(Boolean);

    return dates.length > 0 ? [...dates].sort()[0]! : undefined;
});

const dateMax = todayWIB();

const filteredArticles = computed(() => {
    const today = new Date();
    const cutoff7 = new Date(today);
    cutoff7.setDate(cutoff7.getDate() - 7);
    const cutoff30 = new Date(today);
    cutoff30.setDate(cutoff30.getDate() - 30);
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

    return listArticles.value.filter((a) => {
        if (activeCat.value !== 'all' && a.kategori_id !== activeCat.value) {
            return false;
        }

        if (exactDate.value) {
            return a.published_at === exactDate.value;
        }

        if (!a.published_at) {
            return true;
        }

        if (period.value === '7d') {
            return parseISO(a.published_at) >= cutoff7;
        }

        if (period.value === '30d') {
            return parseISO(a.published_at) >= cutoff30;
        }

        if (period.value === 'month') {
            return parseISO(a.published_at) >= monthStart;
        }

        return true;
    });
});

function resetFilters(): void {
    activeCat.value = 'all';
    period.value = 'all';
    exactDate.value = '';
}

function selectDate(date: string): void {
    exactDate.value = date;
    period.value = 'all';
}

// ── Sidebar: chip hari (dari tanggal artikel yang ada) ──
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

const dayChips = computed(() => {
    const counts = new Map<string, number>();

    for (const a of props.articles) {
        if (a.published_at) {
            counts.set(a.published_at, (counts.get(a.published_at) ?? 0) + 1);
        }
    }

    return [...counts.entries()]
        .sort(([a], [b]) => (a < b ? 1 : -1))
        .map(([date, count]) => {
            const [, m, d] = date.split('-').map(Number);

            return { date, count, label: `${d} ${MONTHS_SHORT[m - 1]}` };
        });
});

// ── Promo slot (data nyata dari backend) ──
const heroBundle = computed(() => props.bundles[0] ?? null);
const featuredBooks = computed(() => props.books);

const bundleHref = computed(() =>
    heroBundle.value
        ? bundleShowRoute({ bundle: heroBundle.value.id }).url
        : catalogUrl().url,
);

const dismissedPromos = ref<Set<string>>(new Set());
function dismissPromo(id: string): void {
    dismissedPromos.value.add(id);
}

const dateLabel = (value: string | null): string =>
    value ? formatDateID(value) : '';
</script>

<template>
    <Head title="Artikel — Pustaka Cahaya Peradaban" />

    <div class="mx-auto max-w-5xl px-4 pt-16 pb-24 md:pt-24 md:pb-32">
        <!-- Intro -->
        <p
            class="mx-auto max-w-2xl text-center text-[15px] leading-relaxed text-pcd-muted"
        >
            Kami menerbitkan buku dan menulis tentangnya —<br
                class="hidden sm:block"
            />
            esai, resensi, dan catatan dari meja redaksi.
        </p>

        <!-- Artikel unggulan -->
        <article
            v-if="featuredArticle"
            class="mx-auto mt-16 max-w-2xl md:mt-20"
        >
            <p
                class="text-xs font-semibold tracking-[0.16em] text-pcd-accent uppercase"
            >
                {{ featuredArticle.kategori_label }} ·
                {{ dateLabel(featuredArticle.published_at) }}
            </p>
            <h1
                class="mt-4 font-serif text-[30px] leading-[1.25] font-semibold tracking-tight md:text-[40px] md:leading-[1.2]"
            >
                <Link
                    :href="
                        articleShowRoute({ article: featuredArticle.slug }).url
                    "
                    class="transition-colors hover:text-pcd-accent"
                >
                    {{ featuredArticle.judul }}
                </Link>
            </h1>
            <p class="mt-5 max-w-xl text-[15px] leading-[1.7] text-pcd-muted">
                {{ featuredArticle.ringkasan }}
            </p>
            <p class="mt-5 text-sm text-pcd-muted">
                {{ featuredArticle.penulis ?? 'Tim Penerbit' }} ·
                {{ featuredArticle.menit }} menit baca
            </p>

            <Link
                :href="articleShowRoute({ article: featuredArticle.slug }).url"
                class="mt-8 block"
            >
                <img
                    v-if="featuredArticle.cover_url"
                    :src="featuredArticle.cover_url"
                    :alt="`Ilustrasi artikel ${featuredArticle.judul}`"
                    class="aspect-video w-full rounded-lg object-cover ring-1 ring-pcd-hairline transition-transform duration-300 hover:scale-[1.01]"
                />
                <ArticleThumb
                    v-else
                    :motif="featuredArticle.motif ?? 'lamp'"
                    :label="`Ilustrasi artikel ${featuredArticle.judul}`"
                />
            </Link>

            <Link
                :href="articleShowRoute({ article: featuredArticle.slug }).url"
                class="mt-8 inline-flex min-h-12 items-center gap-1.5 text-sm font-semibold text-pcd-accent transition-colors hover:text-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-pcd-accent-strong"
            >
                Baca artikel
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </article>

        <!-- Daftar artikel + filter -->
        <section
            id="artikel-terbaru"
            class="mt-16 scroll-mt-24 border-t border-pcd-hairline pt-8 md:mt-20"
            aria-labelledby="artikel-terbaru-title"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2
                    id="artikel-terbaru-title"
                    class="font-serif text-2xl font-semibold tracking-tight"
                >
                    Artikel Terbaru
                </h2>
                <p class="text-xs text-pcd-muted" aria-live="polite">
                    {{ filteredArticles.length }} artikel
                </p>
            </div>

            <!-- Filter kategori -->
            <div
                class="mt-6 flex flex-wrap items-center gap-2"
                role="group"
                aria-label="Filter kategori artikel"
            >
                <button
                    v-for="cat in articleCategories"
                    :key="cat.id"
                    type="button"
                    class="min-h-12 rounded-full px-4 text-[13px] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong"
                    :class="
                        activeCat === cat.id
                            ? 'border border-transparent bg-pcd-ink font-medium text-white'
                            : 'border border-pcd-hairline text-pcd-muted hover:border-pcd-ink hover:text-pcd-ink'
                    "
                    :aria-pressed="activeCat === cat.id"
                    @click="activeCat = cat.id"
                >
                    {{ cat.nama }}
                </button>
            </div>

            <!-- Kolom kiri: daftar · Kolom kanan: filter tanggal -->
            <div
                class="mt-6 lg:grid lg:grid-cols-[minmax(0,1fr)_250px] lg:items-start lg:gap-12"
            >
                <div class="min-w-0">
                    <!-- Daftar artikel -->
                    <article
                        v-for="a in filteredArticles"
                        :key="a.id"
                        class="flex gap-5 border-b border-pcd-hairline py-8"
                    >
                        <Link
                            :href="articleShowRoute({ article: a.slug }).url"
                            class="w-28 shrink-0 text-left sm:w-40"
                            :aria-label="`Buka artikel ${a.judul}`"
                        >
                            <img
                                v-if="a.cover_url"
                                :src="a.cover_url"
                                :alt="`Ilustrasi artikel ${a.judul}`"
                                class="aspect-video w-full rounded-md object-cover ring-1 ring-pcd-hairline transition-transform duration-300 hover:scale-[1.02]"
                            />
                            <ArticleThumb
                                v-else
                                :motif="a.motif ?? 'stack'"
                                :label="`Ilustrasi artikel ${a.judul}`"
                            />
                        </Link>
                        <div class="min-w-0">
                            <p
                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <span
                                    class="text-xs font-semibold tracking-[0.14em] text-pcd-accent uppercase"
                                    >{{ a.kategori_label }}</span
                                >
                                <button
                                    v-if="a.published_at"
                                    type="button"
                                    class="rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                                    :class="
                                        exactDate === a.published_at
                                            ? 'border-transparent bg-pcd-ink text-white'
                                            : 'border-pcd-hairline text-pcd-muted hover:border-pcd-ink hover:text-pcd-ink'
                                    "
                                    :aria-pressed="exactDate === a.published_at"
                                    @click="selectDate(a.published_at!)"
                                >
                                    {{ dateLabel(a.published_at) }}
                                </button>
                            </p>
                            <h3
                                class="mt-2.5 font-serif text-lg leading-snug font-semibold tracking-tight md:text-xl"
                            >
                                <Link
                                    :href="
                                        articleShowRoute({ article: a.slug })
                                            .url
                                    "
                                    class="transition-colors hover:text-pcd-accent"
                                >
                                    {{ a.judul }}
                                </Link>
                            </h3>
                            <p
                                class="mt-2 text-sm leading-[1.7] text-pcd-muted"
                            >
                                {{ a.ringkasan }}
                            </p>
                        </div>
                    </article>

                    <!-- Tidak ada hasil -->
                    <div
                        v-if="filteredArticles.length === 0"
                        class="border-b border-pcd-hairline py-16 text-center"
                    >
                        <p class="text-sm text-pcd-muted">
                            {{
                                props.articles.length === 0
                                    ? 'Belum ada artikel. Cerita pertama akan segera hadir.'
                                    : 'Tidak ada artikel yang cocok dengan filter.'
                            }}
                        </p>
                        <button
                            v-if="props.articles.length > 0"
                            type="button"
                            class="mt-5 inline-flex min-h-12 items-center rounded-lg border border-pcd-hairline bg-pcd-surface px-6 text-sm font-medium transition-colors hover:border-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong"
                            @click="resetFilters"
                        >
                            Atur ulang filter
                        </button>
                    </div>
                </div>

                <!-- Sidebar: filter tanggal -->
                <aside
                    class="mt-10 lg:sticky lg:top-24 lg:mt-0"
                    aria-labelledby="filter-tanggal"
                >
                    <div
                        class="rounded-xl border border-pcd-hairline bg-pcd-surface p-5"
                    >
                        <h3 id="filter-tanggal" class="text-sm font-semibold">
                            Filter Tanggal
                        </h3>
                        <div class="mt-4 space-y-5">
                            <label class="block">
                                <span class="text-xs font-medium text-pcd-muted"
                                    >Periode</span
                                >
                                <select
                                    v-model="period"
                                    class="mt-2 min-h-12 w-full rounded-lg border border-pcd-hairline bg-pcd-surface px-3 text-sm transition-colors outline-none focus:border-pcd-accent focus:ring-2 focus:ring-pcd-accent/25"
                                    @change="exactDate = ''"
                                >
                                    <option value="all">Semua waktu</option>
                                    <option value="7d">7 hari terakhir</option>
                                    <option value="30d">
                                        30 hari terakhir
                                    </option>
                                    <option value="month">Bulan ini</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-medium text-pcd-muted"
                                    >Tepat tanggal</span
                                >
                                <input
                                    v-model="exactDate"
                                    type="date"
                                    :min="dateMin"
                                    :max="dateMax"
                                    class="mt-2 min-h-12 w-full rounded-lg border border-pcd-hairline bg-pcd-surface px-3 text-sm transition-colors outline-none focus:border-pcd-accent focus:ring-2 focus:ring-pcd-accent/25"
                                />
                            </label>
                            <div>
                                <span class="text-xs font-medium text-pcd-muted"
                                    >Hari</span
                                >
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="chip in dayChips"
                                        :key="chip.date"
                                        type="button"
                                        class="min-h-9 rounded-full border px-3 text-xs transition-colors"
                                        :class="
                                            exactDate === chip.date
                                                ? 'border-transparent bg-pcd-ink font-medium text-white'
                                                : 'border-pcd-hairline text-pcd-muted hover:border-pcd-ink hover:text-pcd-ink'
                                        "
                                        :aria-pressed="exactDate === chip.date"
                                        @click="selectDate(chip.date)"
                                    >
                                        {{ chip.label }} ({{ chip.count }})
                                    </button>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center text-sm font-medium text-pcd-accent transition-colors hover:text-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-pcd-accent-strong"
                                @click="
                                    exactDate = '';
                                    period = 'all';
                                "
                            >
                                Tampilkan semua
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Promo slot: paket (data nyata) -->
        <aside
            v-if="heroBundle && !dismissedPromos.has(`bundle-${heroBundle.id}`)"
            class="mt-14"
            aria-label="Promo paket buku"
        >
            <div
                class="relative flex items-center gap-4 rounded-lg border border-pcd-hairline bg-pcd-surface p-4 sm:p-5"
            >
                <button
                    type="button"
                    class="absolute -top-2.5 -right-2.5 flex size-9 items-center justify-center rounded-full border border-pcd-hairline bg-pcd-surface text-pcd-muted transition-colors hover:border-pcd-ink hover:text-pcd-ink"
                    :aria-label="`Tutup promo ${heroBundle.promo_name}`"
                    @click="dismissPromo(`bundle-${heroBundle.id}`)"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
                <div class="grid shrink-0 grid-cols-2 gap-1">
                    <img
                        v-for="b in heroBundle.books.slice(0, 4)"
                        :key="b.id"
                        :src="b.cover_url ?? ''"
                        :alt="b.cover_url ? `Sampul ${b.judul}` : ''"
                        class="size-10 rounded-sm object-cover ring-1 ring-pcd-hairline"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-xs font-semibold tracking-[0.14em] text-pcd-accent uppercase"
                    >
                        Promo · Paket
                    </p>
                    <h3 class="mt-1.5 truncate text-sm font-semibold">
                        {{ heroBundle.promo_name }}
                    </h3>
                    <p class="mt-0.5 truncate text-xs text-pcd-muted">
                        {{ heroBundle.books.length }} buku · hemat
                        <Money
                            :value="heroBundle.total_discount"
                            class="text-xs"
                        />
                    </p>
                </div>
                <Link
                    :href="bundleHref"
                    class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg bg-pcd-accent-strong px-4 text-sm font-semibold text-white transition-colors hover:bg-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong"
                    >Lihat Paket</Link
                >
            </div>
        </aside>

        <!-- Dari Toko: buku unggulan (data nyata) -->
        <section
            class="mt-20 border-t border-pcd-hairline pt-14 md:mt-28 md:pt-16"
            aria-labelledby="dari-toko"
        >
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-pcd-accent uppercase"
                    >
                        Dari Toko
                    </p>
                    <h2
                        id="dari-toko"
                        class="mt-2 font-serif text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        Buku Pilihan
                    </h2>
                </div>
                <Link
                    :href="catalogUrl().url"
                    class="shrink-0 text-sm font-medium text-pcd-muted transition-colors hover:text-pcd-ink"
                    >Semua buku →</Link
                >
            </div>

            <div
                v-if="featuredBooks.length > 0"
                class="mt-10 grid grid-cols-2 gap-x-6 gap-y-12 sm:grid-cols-4"
            >
                <div v-for="book in featuredBooks" :key="book.id">
                    <Link
                        :href="showRoute.url(bookShowUrl(book))"
                        class="group block"
                    >
                        <div
                            class="relative overflow-hidden rounded-md ring-1 ring-pcd-hairline"
                        >
                            <img
                                v-if="book.cover_url"
                                :src="book.cover_url"
                                :alt="`Sampul buku ${book.judul}`"
                                class="aspect-[5/7] w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                            />
                            <BookCoverPlaceholder
                                v-else
                                :title="book.judul"
                                class="aspect-[5/7] w-full"
                            />
                        </div>
                        <h3 class="mt-3 truncate text-sm font-semibold">
                            {{ book.judul }}
                        </h3>
                        <p class="mt-0.5 truncate text-xs text-pcd-muted">
                            {{ book.penulis ?? '—' }}
                        </p>
                        <p class="mt-2 text-sm font-semibold tabular-nums">
                            <Money
                                :value="
                                    book.price_breakdown?.final_price ??
                                    book.harga
                                "
                            />
                            <s
                                v-if="book.price_breakdown"
                                class="ml-1 text-xs font-normal text-pcd-muted"
                            >
                                <Money
                                    :value="book.price_breakdown.original_price"
                                />
                            </s>
                        </p>
                    </Link>
                    <Form
                        :action="CartController.add().url"
                        method="post"
                        class="mt-3"
                    >
                        <input type="hidden" name="book_id" :value="book.id" />
                        <input type="hidden" name="qty" value="1" />
                        <button
                            type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-pcd-hairline bg-pcd-surface text-xs font-semibold text-pcd-ink transition-colors hover:border-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong"
                        >
                            <ShoppingCart class="size-3.5" aria-hidden="true" />
                            Tambah
                        </button>
                    </Form>
                </div>
            </div>

            <p v-else class="mt-10 text-sm text-pcd-muted">
                Belum ada buku yang ditampilkan.
            </p>
        </section>
    </div>
</template>
