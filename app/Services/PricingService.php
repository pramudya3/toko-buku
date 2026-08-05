<?php

namespace App\Services;

use App\Enums\CustomerTier;
use App\Enums\PromotionType;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promotion;
use App\Services\Pricing\PriceBreakdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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
            PromotionType::Bundle => $qty >= ($promo->bundle_qty ?? PHP_INT_MAX)
                ? intdiv($price * (100 - $discountPercentage), 100)
                : $price,
        };
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
            ->where(function ($query) use ($qty): void {
                $query->whereNull('max_qty')->orWhere('max_qty', '>=', $qty);
            })
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

        $total = 0;

        foreach ($order->items as $item) {
            $breakdown = $this->priceBreakdown($item->book, $item->qty, $tier);

            $item->judul_snapshot = $item->book->judul;
            $item->harga_snapshot = $item->book->harga;
            $item->price_original = $breakdown->originalPrice;
            $item->promo_discount_amount = $breakdown->promoDiscount;
            $item->tier_discount_amount = $breakdown->tierDiscount;
            $item->price_final = $breakdown->finalPrice;
            $item->save();

            $total += $breakdown->finalPrice * $item->qty;
        }

        $order->total = $total + $order->shipping_cost;
        $order->save();
    }

    /**
     * Simpan order beserta item-nya dengan perhitungan harga otomatis, dalam 1 transaksi.
     *
     * @param  array<int, array{book_id: int, qty: int}>  $items
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

                $order->items()->create([
                    'book_id' => $book->id,
                    'judul_snapshot' => $book->judul,
                    'harga_snapshot' => $book->harga,
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
