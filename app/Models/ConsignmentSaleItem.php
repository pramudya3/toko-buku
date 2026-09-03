<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item laporan laku konsinyasi — qty + harga titip per buku.
 *
 * @property string $id
 * @property string $sale_id
 * @property string $book_id
 * @property int $qty
 * @property int $price
 */
#[Fillable(['sale_id', 'book_id', 'qty', 'price'])]
class ConsignmentSaleItem extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<ConsignmentSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(ConsignmentSale::class, 'sale_id');
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
            'price' => 'integer',
        ];
    }
}
