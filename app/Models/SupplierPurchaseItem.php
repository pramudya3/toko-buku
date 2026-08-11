<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_purchase_id
 * @property int $book_id
 * @property int $qty
 * @property int $price
 * @property int $subtotal
 */
#[Fillable(['supplier_purchase_id', 'book_id', 'qty', 'price', 'subtotal'])]

class SupplierPurchaseItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<SupplierPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchase::class);
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
            'subtotal' => 'integer',
        ];
    }
}
