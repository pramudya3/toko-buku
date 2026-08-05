<?php

namespace App\Services\Pricing;

/**
 * DTO rincian harga final: base → promo → tier (BR-01).
 */
final readonly class PriceBreakdown
{
    public function __construct(
        public int $originalPrice,
        public int $promoDiscount,
        public int $tierDiscount,
        public int $finalPrice,
        public ?string $promoName = null,
    ) {
    }
}
