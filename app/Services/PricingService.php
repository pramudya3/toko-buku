<?php

namespace App\Services;

use App\Enums\CustomerTier;
use App\Enums\PromotionType;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\Order;
use App\Models\Promotion;
use App\Services\Pricing\PriceBreakdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya sumber kebenaran perhitungan harga (BR-01).
 *
 * Urutan: harga dasar (books.harga) → promo aktif (kalender) → tier discount.
 * Dipakai oleh panel admin dan (nanti) storefront — konsistensi dijamin test matrix.
 */
final class PricingService
{
    /**
     * @var array<string, Promotion|null>
     */
    private array $activePromotionCache = [];

    /**
     * Harga final per unit untuk qty & tier tertentu.
     */
    public function finalPrice(Book $book, int $qty, ?CustomerTier $tier = null): int
    {
        return $this->priceBreakdown($book, $qty, $tier)->finalPrice;
    }

    /**
     * Rincian diskon utk ditampilkan di UI (original, promo, tier, final).
     */
    public function priceBreakdown(Book $book, int $qty, ?CustomerTier $tier = null): PriceBreakdown
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah buku harus lebih dari 0.');
        }

        $original = $book->harga;
        $promotion = $this->activePromotion($book);
        $afterPromo = $this->applyPromotion($promotion, $original, $qty);
        $promoName = $promotion?->promo_name;

        $tierDiscount = $this->tierDiscountAmount($tier, $qty, $afterPromo);

        return new PriceBreakdown(
            originalPrice: $original,
            promoDiscount: $original - $afterPromo,
            tierDiscount: $tierDiscount,
            finalPrice: $afterPromo - $tierDiscount,
            promoName: $promoName,
        );
    }

    /**
     * Promo aktif utk sebuah buku (BR-02): is_active && start_date ≤ today ≤ end_date.
     */
    public function activePromotion(Book $book): ?Promotion
    {
        $today = now()->toDateString();
        $cacheKey = $book->getKey().':'.$today;

        if (array_key_exists($cacheKey, $this->activePromotionCache)) {
            return $this->activePromotionCache[$cacheKey];
        }

        $promotion = Promotion::query()
            ->where('is_active', true)
            // Bundle tidak ikut seleksi promo per unit — hanya berlaku di level order
            // (matchingBundleDiscounts) saat seluruh buku bundle ada di keranjang.
            ->where('promo_type', '!=', PromotionType::Bundle->value)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(function (Builder $query) use ($book): void {
                $query->whereDoesntHave('books')
                    ->orWhereHas('books', function (Builder $query) use ($book): void {
                        $query->whereKey($book->getKey());
                    });
            })
            ->latest('end_date')
            ->latest('id')
            ->first();

        $this->activePromotionCache[$cacheKey] = $promotion;

        return $promotion;
    }

    /**
     * Harga setelah promo (belum termasuk tier discount).
     * Bundle tidak dihitung di sini — ditangani di level order oleh applyToOrder().
     */
    private function applyPromotion(?Promotion $promo, int $price, int $qty): int
    {
        if ($promo === null) {
            return $price;
        }

        $discountPercentage = $promo->discount_percentage ?? 0;

        return match ($promo->promo_type) {
            PromotionType::Percentage => intdiv($price * (100 - $discountPercentage), 100),
            PromotionType::Fixed => min($promo->promo_value ?? $price, $price),
            default => $price,
        };
    }

    /**
     * Price breakdown dengan promo yang sudah di-preload (hindari N+1).
     */
    public function priceBreakdownWithPromo(Book $book, ?Promotion $promo, int $qty = 1, ?CustomerTier $tier = null): PriceBreakdown
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah buku harus lebih dari 0.');
        }

        $original = $book->harga;
        $afterPromo = $this->applyPromotion($promo, $original, $qty);
        $tierDiscount = $this->tierDiscountAmount($tier, $qty, $afterPromo);

        return new PriceBreakdown(
            originalPrice: $original,
            promoDiscount: $original - $afterPromo,
            tierDiscount: $tierDiscount,
            finalPrice: $afterPromo - $tierDiscount,
            promoName: $promo?->promo_name,
        );
    }

    /**
     * Diskon tier (BR-03) dari tabel tier_discounts (dikelola di panel admin),
     * dihitung dari harga setelah promo. Threshold tertinggi yang tercapai dipakai.
     * Contoh: bazaf min_qty 1 → 5%, min_qty 6 → 10% → qty 3 = 5%, qty 8 = 10%.
     */
    private function tierDiscountAmount(?CustomerTier $tier, int $qty, int $price): int
    {
        if ($tier === null) {
            return 0;
        }

        $discountPercent = (int) (DB::table('tier_discounts')
            ->where('tier', $tier->value)
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('min_qty')
            ->value('discount_percent') ?? 0);

        return intdiv($price * $discountPercent, 100);
    }

    /**
     * (Re)build seluruh order_items dari service (ORD-08).
     * Dipanggil saat order dibuat/diedit; menghitung ulang subtotal & total.
     */
    public function applyToOrder(Order $order): void
    {
        $tier = $order->user?->status_pelanggan;

        // Harga dasar mengikuti cetakan terpilih bila ada (snapshot harga edisi)
        // — berlaku juga untuk perhitungan diskon bundle di bawah. Setiap item
        // diberi klon Book sendiri agar harga antar cetakan tidak saling timpa
        // (eager load memakai instance Book yang sama per buku).
        foreach ($order->items as $item) {
            if ($item->book_edition_id !== null && $item->edition !== null) {
                $clone = $item->book->replicate();
                $clone->exists = true;
                $clone->id = $item->book->id;
                $clone->harga = $item->edition->harga_jual;
                $item->setRelation('book', $clone);
                $item->harga_beli_snapshot = $item->edition->harga_beli;
            }
        }

        // Pre-komputasi diskon bundle — dicek per order, bukan per buku.
        $bundleDiscounts = $this->matchingBundleDiscounts($order);

        $total = 0;

        foreach ($order->items as $item) {
            $breakdown = $this->priceBreakdown($item->book, $item->qty, $tier);

            $bundleDiscountAmount = $bundleDiscounts[$item->id] ?? 0;
            $inBundle = $bundleDiscountAmount > 0;

            $item->judul_snapshot = $item->book->judul;
            $item->harga_snapshot = $item->book->harga;
            $item->price_original = $breakdown->originalPrice;

            if ($inBundle) {
                // Buku dalam bundle lengkap: HANYA diskon bundle (BR-02), dan
                // diskon hanya utk 1 SET pertama — eksemplar ekstra dihitung
                // harga normal (baris terpisah agar kolom per-unit tetap jujur).
                $discountedQty = min($item->qty, 1);
                $extraQty = $item->qty - $discountedQty;

                $item->qty = $discountedQty;
                $item->promo_discount_amount = $bundleDiscountAmount;
                $item->tier_discount_amount = 0;
                $item->price_final = max(0, $breakdown->originalPrice - $bundleDiscountAmount);
                $item->save();

                $total += $item->price_final * $discountedQty;

                if ($extraQty > 0) {
                    $extra = $item->replicate();
                    $extra->qty = $extraQty;
                    $extra->promo_discount_amount = 0;
                    $extra->tier_discount_amount = 0;
                    $extra->price_final = $breakdown->originalPrice;
                    $extra->save();

                    $total += $extra->price_final * $extraQty;
                }

                continue;
            }

            $finalPrice = $breakdown->finalPrice - $bundleDiscountAmount;
            $item->promo_discount_amount = $breakdown->promoDiscount + $bundleDiscountAmount;
            $item->tier_discount_amount = $breakdown->tierDiscount;
            $item->price_final = max(0, $finalPrice);
            $item->save();

            $total += $item->price_final * $item->qty;
        }

        $order->total = $total + $order->shipping_cost;
        $order->save();
    }

    /**
     * Diskon bundle per unit untuk satu buku — dihitung dari HARGA DASAR
     * (tanpa promo satuan). Buku dalam bundle lengkap HANYA mendapat diskon
     * bundle, promo lain tidak bertumpuk (BR-02).
     */
    public function bundleUnitDiscount(Book $book, Promotion $promo): int
    {
        return intdiv($book->harga * ($promo->discount_percentage ?? 0), 100);
    }

    /**
     * Rincian harga 1 set paket bundle (tanpa tier discount) untuk display
     * storefront. Hanya diskon bundle yang berlaku — promo satuan tidak
     * ditumpuk pada buku bundle (BR-02).
     *
     * @return array{
     *     discount_percent: int,
     *     items: list<array{book: Book, unit_price: int, unit_discount: int, unit_final: int}>,
     *     total_original: int,
     *     total_discount: int,
     *     total_final: int,
     * }
     */
    public function bundleBreakdown(Promotion $promo): array
    {
        $items = [];
        $totalOriginal = 0;
        $totalDiscount = 0;

        foreach ($promo->books as $book) {
            $discount = $this->bundleUnitDiscount($book, $promo);

            $items[] = [
                'book' => $book,
                'unit_price' => $book->harga,
                'unit_discount' => $discount,
                'unit_final' => $book->harga - $discount,
            ];
            $totalOriginal += $book->harga;
            $totalDiscount += $discount;
        }

        return [
            'discount_percent' => $promo->discount_percentage ?? 0,
            'items' => $items,
            'total_original' => $totalOriginal,
            'total_discount' => $totalDiscount,
            'total_final' => $totalOriginal - $totalDiscount,
        ];
    }

    /**
     * Cari bundle promo aktif yang semua bukunya ada di order ini.
     * Return map item_id => diskon per unit (dalam rupiah) dari bundle.
     *
     * @return array<int, int>
     */
    /**
     * Diskon bundle utk item keranjang/order (sebelum order dibuat atau saat rebuild).
     * Return map book_id => diskon per unit (rupiah), konsisten dgn applyToOrder().
     *
     * @param  array<int, array{book: Book, qty: int}>  $items
     * @return array<int, int>
     */
    public function cartBundleDiscounts(array $items): array
    {
        $today = now()->toDateString();
        $orderBookIds = collect($items)->pluck('book.id')->all();

        $bundlePromos = Promotion::query()
            ->with('books:id')
            ->where('promo_type', PromotionType::Bundle->value)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        $discounts = []; // book_id => diskon per unit

        foreach ($bundlePromos as $promo) {
            $bundleBookIds = $promo->books->pluck('id')->all();

            // Bundle harus punya buku spesifik (bukan global).
            if (count($bundleBookIds) === 0) {
                continue;
            }

            // Semua buku bundle harus ada di keranjang (1 set = 1 eksemplar setiap buku).
            if (count(array_diff($bundleBookIds, $orderBookIds)) > 0) {
                continue;
            }

            foreach ($bundleBookIds as $bookId) {
                $item = collect($items)->firstWhere('book.id', $bookId);
                $discount = $this->bundleUnitDiscount($item['book'], $promo);
                $current = $discounts[$bookId] ?? 0;
                $discounts[$bookId] = max($current, $discount);
            }
        }

        return $discounts;
    }

    /**
     * Cari bundle promo aktif yang semua bukunya ada di order ini.
     * Return map item_id => diskon per unit (dalam rupiah) dari bundle.
     *
     * @return array<int, int>
     */
    private function matchingBundleDiscounts(Order $order): array
    {
        $items = $order->items
            ->map(fn ($item): array => ['book' => $item->book, 'qty' => $item->qty])
            ->all();

        $byBook = $this->cartBundleDiscounts($items);

        $discounts = []; // item_id => diskon per unit

        foreach ($order->items as $item) {
            if (isset($byBook[$item->book_id])) {
                $discounts[$item->id] = $byBook[$item->book_id];
            }
        }

        return $discounts;
    }

    /**
     * Simpan order beserta item-nya dengan perhitungan harga otomatis, dalam 1 transaksi.
     *
     * @param  array<int, array{book_id: int, qty: int, book_edition_id?: int|null, edition_snapshot?: string|null}>  $items
     */
    public function storeOrderWithItems(Order $order, array $items): Order
    {
        DB::transaction(function () use ($order, $items): void {
            $order->save();

            $bookIds = collect($items)->pluck('book_id')->unique()->values();
            $books = Book::query()->whereKey($bookIds)->get()->keyBy('id');

            foreach ($items as $row) {
                $book = $books->get($row['book_id']);

                if ($book === null) {
                    throw (new ModelNotFoundException)->setModel(Book::class, [$row['book_id']]);
                }

                $edition = null;

                // Harga dasar mengikuti cetakan terpilih bila ada.
                if (! empty($row['book_edition_id'])) {
                    $edition = BookEdition::query()->find($row['book_edition_id']);

                    if ($edition === null || $edition->book_id !== $book->id) {
                        throw new RuntimeException('Cetakan buku tidak valid.');
                    }

                    $book->harga = $edition->harga_jual;
                }

                $order->items()->create([
                    'book_id' => $book->id,
                    'book_edition_id' => $edition?->id,
                    'judul_snapshot' => $book->judul,
                    'harga_snapshot' => $edition?->harga_jual ?? $book->harga,
                    'harga_beli_snapshot' => $edition?->harga_beli ?? 0,
                    'edition_snapshot' => $edition !== null ? "Cetakan ke-{$edition->cetakan_ke}" : null,
                    'qty' => $row['qty'],
                    'price_original' => 0,
                    'promo_discount_amount' => 0,
                    'tier_discount_amount' => 0,
                    'price_final' => 0,
                ]);
            }

            $order->load('items.book');
            $this->applyToOrder($order);
        });

        return $order->fresh(['items.book', 'user']);
    }
}
