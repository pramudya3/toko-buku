<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_return_id
 * @property int $book_id
 * @property int $qty
 * @property int $price
 * @property string $reason
 * @property int $subtotal
 */
#[Fillable(['supplier_return_id', 'book_id', 'book_edition_id', 'qty', 'price', 'reason', 'subtotal', 'stock_before', 'hpp_at_return'])]

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

    /**
     * @return HasMany<SupplierReturnItemAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierReturnItemAllocation::class, 'supplier_return_item_id');
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
            'price' => 'integer',
            'subtotal' => 'integer',
            'stock_before' => 'integer',
            'hpp_at_return' => 'integer',
        ];
    }
}
