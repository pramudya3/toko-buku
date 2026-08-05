<?php

use App\Enums\CustomerTier;
use App\Enums\PromotionType;
use App\Models\Book;
use App\Models\Promotion;
use App\Services\Pricing\PriceBreakdown;
use App\Services\PricingService;

it('returns base price without active promotions or tier', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);

    expect(app(PricingService::class)->finalPrice($book, 1))->toBe(100000);
});

it('applies global promotions without a pivot attachment', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);
    Promotion::factory()->percentage(20)->global()->create();

    expect(app(PricingService::class)->finalPrice($book, 1))->toBe(80000);
});

it('applies percentage promotion (BR-02)', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);
    $promo = Promotion::factory()->percentage(20)->create();
    $book->promotions()->attach($promo);

    expect(app(PricingService::class)->finalPrice($book, 1))->toBe(80000);
});

it('ignores inactive, expired and upcoming promotions (BR-02)', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);

    foreach ([
        Promotion::factory()->percentage(50)->inactive(),
        Promotion::factory()->percentage(50)->expired(),
        Promotion::factory()->percentage(50)->upcoming(),
    ] as $factory) {
        $book->promotions()->attach($factory->create());
    }

    expect(app(PricingService::class)->finalPrice($book, 1))->toBe(100000);
});

it('applies fixed price promotion capped at the base price', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);
    $book->promotions()->attach(Promotion::factory()->fixed(70000)->create());

    expect(app(PricingService::class)->finalPrice($book, 1))->toBe(70000);
});

it('applies bundle promotion only when qty threshold is met', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);
    $book->promotions()->attach(Promotion::factory()->bundle(qty: 3, percent: 15)->create());

    expect(app(PricingService::class)->finalPrice($book, 2))->toBe(100000)
        ->and(app(PricingService::class)->finalPrice($book, 3))->toBe(85000);
});

it('applies tier discount after promotion (BR-01, BR-03)', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);

    // Reseller: qty 10 → 10% tier discount.
    $price = app(PricingService::class)->finalPrice($book, 10, CustomerTier::Reseller);

    expect($price)->toBe(90000);

    // Reseller: qty 20 → 15%.
    $price20 = app(PricingService::class)->finalPrice($book, 20, CustomerTier::Reseller);

    expect($price20)->toBe(85000);

    // Bazaf: qty 10 → 5%.
    $bazaf = app(PricingService::class)->finalPrice($book, 10, CustomerTier::Bazaf);

    expect($bazaf)->toBe(95000);

    // Reguler & guru tanpa diskon.
    expect(app(PricingService::class)->finalPrice($book, 10, CustomerTier::Reguler))->toBe(100000)
        ->and(app(PricingService::class)->finalPrice($book, 10, CustomerTier::Guru))->toBe(100000);
});

it('chains promotion then tier discount (BR-01)', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);
    $book->promotions()->attach(Promotion::factory()->percentage(20)->create());

    // 100000 → promo 20% = 80000 → tier reseller 10% = 72000.
    $breakdown = app(PricingService::class)->priceBreakdown($book, 10, CustomerTier::Reseller);

    expect($breakdown)->toBeInstanceOf(PriceBreakdown::class)
        ->and($breakdown->originalPrice)->toBe(100000)
        ->and($breakdown->promoDiscount)->toBe(20000)
        ->and($breakdown->tierDiscount)->toBe(8000)
        ->and($breakdown->finalPrice)->toBe(72000);
});

it('rounds discounts down so final price never exceeds base', function (): void {
    $book = Book::factory()->create(['harga' => 99999]);
    $book->promotions()->attach(Promotion::factory()->percentage(33)->create());

    $price = app(PricingService::class)->finalPrice($book, 1);

    expect($price)->toBeLessThanOrEqual(99999)
        ->and($price)->toBe(66999);
});

it('rebuilds order item prices when applyToOrder is called (ORD-08)', function (): void {
    $book = Book::factory()->withStock()->create(['harga' => 100000]);
    $book->promotions()->attach(Promotion::factory()->percentage(10)->create());

    $order = \App\Models\Order::factory()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 0,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 0,
    ]);

    app(PricingService::class)->applyToOrder($order->load('items.book'));

    $item = $order->items()->first();

    expect($item->price_original)->toBe(100000)
        ->and($item->promo_discount_amount)->toBe(10000)
        ->and($item->price_final)->toBe(90000)
        ->and($order->fresh()->total)->toBe(180000);
});
