<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $book_id
 * @property int|null $book_edition_id
 * @property bool $is_preorder
 * @property string $judul_snapshot
 * @property int $harga_snapshot
 * @property int|null $harga_beli_snapshot
 * @property string|null $edition_snapshot
 * @property int $qty
 * @property int $price_original
 * @property int $promo_discount_amount
 * @property int $tier_discount_amount
 * @property int $price_final
 * @property bool $is_custom_price
 * @property int|null $custom_price
 * @property string|null $price_note
 * @property string|null $promo_id_snapshot
 */
#[Fillable([
    'order_id', 'book_id', 'book_edition_id', 'is_preorder', 'judul_snapshot',
    'harga_snapshot', 'harga_beli_snapshot', 'edition_snapshot', 'qty', 'price_original',
    'promo_discount_amount', 'tier_discount_amount', 'price_final',
    'is_custom_price', 'custom_price', 'price_note', 'promo_id_snapshot',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    use HasUuids;

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

    /**
     * @return BelongsTo<BookEdition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(BookEdition::class, 'book_edition_id');
    }

    protected function casts(): array
    {
        return [
            'is_preorder' => 'boolean',
            'qty' => 'integer',
            'price_original' => 'integer',
            'promo_discount_amount' => 'integer',
            'tier_discount_amount' => 'integer',
            'price_final' => 'integer',
            'is_custom_price' => 'boolean',
            'custom_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promoSnapshot(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promo_id_snapshot');
    }
}
