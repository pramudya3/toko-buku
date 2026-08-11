<?php

use App\Enums\CustomerTier;
use App\Models\Book;
use App\Models\Order;
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

it('does not let a bundle promo shadow unit promotions (BR-02)', function (): void {
    $book = Book::factory()->create(['harga' => 100000]);

    // Bundle promo berakhir lebih lama — sebelumnya menang di seleksi promo aktif
    // dan menghalangi diskon satuan karena bundle tidak mengubah harga per unit.
    $bundle = Promotion::factory()->bundle(percent: 15)->create([
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);
    $bundle->books()->attach($book);

    $percentage = Promotion::factory()->percentage(20)->create([
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
    ]);
    $book->promotions()->attach($percentage);

    // Diskon satuan 20% tetap berlaku meski bundle promo ada & berakhir lebih lama.
    expect(app(PricingService::class)->finalPrice($book, 1))->toBe(80000);
});

it('applies bundle promotion when order contains all bundle books', function (): void {
    $bookA = Book::factory()->withStock()->create(['harga' => 100000]);
    $bookB = Book::factory()->withStock()->create(['harga' => 50000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    // Order dengan kedua buku bundle + qty memenuhi.
    $order = Order::factory()->create();
    $order->items()->createMany([
        [
            'book_id' => $bookA->id,
            'judul_snapshot' => $bookA->judul,
            'harga_snapshot' => $bookA->harga,
            'qty' => 2,
            'price_original' => 0,
            'promo_discount_amount' => 0,
            'tier_discount_amount' => 0,
            'price_final' => 0,
        ],
        [
            'book_id' => $bookB->id,
            'judul_snapshot' => $bookB->judul,
            'harga_snapshot' => $bookB->harga,
            'qty' => 1,
            'price_original' => 0,
            'promo_discount_amount' => 0,
            'tier_discount_amount' => 0,
            'price_final' => 0,
        ],
    ]);

    app(PricingService::class)->applyToOrder($order->load('items.book'));

    $itemA = $order->items()->where('book_id', $bookA->id)->first();
    $itemB = $order->items()->where('book_id', $bookB->id)->first();

    // 100000 - 15% = 85000 → promo_discount = 15000 (hanya 1 set pertama).
    expect($itemA->price_original)->toBe(100000)
        ->and($itemA->qty)->toBe(1)
        ->and($itemA->promo_discount_amount)->toBe(15000)
        ->and($itemA->price_final)->toBe(85000);

    // Eksemplar ke-2 bookA di luar 1 set → baris terpisah harga normal.
    $extraA = $order->items()->where('book_id', $bookA->id)->get();

    expect($extraA)->toHaveCount(2)
        ->and($extraA->last()->qty)->toBe(1)
        ->and($extraA->last()->promo_discount_amount)->toBe(0)
        ->and($extraA->last()->price_final)->toBe(100000);

    // 50000 - 15% = 42500 → promo_discount = 7500.
    expect($itemB->price_original)->toBe(50000)
        ->and($itemB->promo_discount_amount)->toBe(7500)
        ->and($itemB->price_final)->toBe(42500)
        ->and($order->fresh()->total)->toBe(227500);
});

it('computes bundle breakdown for one set (storefront display)', function (): void {
    $bookA = Book::factory()->create(['harga' => 100000]);
    $bookB = Book::factory()->create(['harga' => 50000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    $breakdown = app(PricingService::class)->bundleBreakdown($promo);

    expect($breakdown['total_original'])->toBe(150000)
        ->and($breakdown['total_discount'])->toBe(22500)
        ->and($breakdown['total_final'])->toBe(127500)
        ->and($breakdown['discount_percent'])->toBe(15)
        ->and($breakdown['items'])->toHaveCount(2)
        ->and($breakdown['items'][0]['unit_discount'])->toBe(15000)
        ->and($breakdown['items'][1]['unit_final'])->toBe(42500);
});

it('applies only the bundle discount to bundle books (no unit promo stacking)', function (): void {
    $bookA = Book::factory()->create(['harga' => 100000]);
    $bookB = Book::factory()->create(['harga' => 50000]);

    // Buku A punya promo satuan 20% — TIDAK bertumpuk dgn bundle.
    $bookA->promotions()->attach(Promotion::factory()->percentage(20)->create());

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    $breakdown = app(PricingService::class)->bundleBreakdown($promo);

    // Diskon hanya dari bundle 15% x harga dasar; promo 20% diabaikan.
    expect($breakdown['total_original'])->toBe(150000)
        ->and($breakdown['total_discount'])->toBe(22500)
        ->and($breakdown['total_final'])->toBe(127500)
        ->and($breakdown['items'][0]['unit_price'])->toBe(100000)
        ->and($breakdown['items'][0]['unit_discount'])->toBe(15000)
        ->and($breakdown['items'][0]['unit_final'])->toBe(85000);
});

it('computes cart bundle discounts when cart qualifies (checkout preview)', function (): void {
    $bookA = Book::factory()->create(['harga' => 100000]);
    $bookB = Book::factory()->create(['harga' => 50000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    $items = [
        ['book' => $bookA, 'qty' => 2],
        ['book' => $bookB, 'qty' => 2],
    ];

    $discounts = app(PricingService::class)->cartBundleDiscounts($items);

    expect($discounts[$bookA->id])->toBe(15000)
        ->and($discounts[$bookB->id])->toBe(7500);
});

it('applies bundle discount regardless of qty as long as all books are present', function (): void {
    $bookA = Book::factory()->create(['harga' => 100000]);
    $bookB = Book::factory()->create(['harga' => 50000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    // Semua buku paket ada (qty berapa pun) → diskon berlaku.
    $items = [
        ['book' => $bookA, 'qty' => 1],
        ['book' => $bookB, 'qty' => 2],
    ];

    $discounts = app(PricingService::class)->cartBundleDiscounts($items);

    expect($discounts[$bookA->id])->toBe(15000)
        ->and($discounts[$bookB->id])->toBe(7500);
});

it('does not apply bundle when order is missing a bundle book', function (): void {
    $bookA = Book::factory()->withStock()->create(['harga' => 100000]);
    $bookB = Book::factory()->withStock()->create(['harga' => 50000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    // Order hanya berisi bookA — bookB tidak ada.
    $order = Order::factory()->create();
    $order->items()->create([
        'book_id' => $bookA->id,
        'judul_snapshot' => $bookA->judul,
        'harga_snapshot' => $bookA->harga,
        'qty' => 2,
        'price_original' => 0,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 0,
    ]);

    app(PricingService::class)->applyToOrder($order->load('items.book'));

    $item = $order->items()->first();

    expect($item->price_final)->toBe(100000);
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

    $order = Order::factory()->create();
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
