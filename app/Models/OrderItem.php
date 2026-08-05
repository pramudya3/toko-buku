<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $book_id
 * @property string $judul_snapshot
 * @property int $harga_snapshot
 * @property int $qty
 * @property int $price_original
 * @property int $promo_discount_amount
 * @property int $tier_discount_amount
 * @property int $price_final
 */
#[Fillable([
    'order_id', 'book_id', 'judul_snapshot', 'harga_snapshot', 'qty', 'price_original',
    'promo_discount_amount', 'tier_discount_amount', 'price_final',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price_original' => 'integer',
            'promo_discount_amount' => 'integer',
            'tier_discount_amount' => 'integer',
            'price_final' => 'integer',
        ];
    }
}
