<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item retur penjualan — menunjuk order_item asal (untuk cek qty sisa).
 *
 * @property int $id
 * @property int $sales_return_id
 * @property int $order_item_id
 * @property int $book_id
 * @property int|null $book_edition_id
 * @property int $qty
 * @property int $price_refund
 * @property string $condition
 * @property string|null $reason
 */
#[Fillable(['sales_return_id', 'order_item_id', 'book_id', 'book_edition_id', 'qty', 'price_refund', 'condition', 'reason'])]
class SalesReturnItem extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<SalesReturn, $this>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
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
            'qty' => 'integer',
            'price_refund' => 'integer',
        ];
    }
}
