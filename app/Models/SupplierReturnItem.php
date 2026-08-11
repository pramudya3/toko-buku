<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_return_id
 * @property int $book_id
 * @property int $qty
 * @property int $price
 * @property string $reason
 * @property int $subtotal
 */
#[Fillable(['supplier_return_id', 'book_id', 'qty', 'price', 'reason', 'subtotal'])]

class SupplierReturnItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<SupplierReturn, $this>
     */
    public function return(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
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
