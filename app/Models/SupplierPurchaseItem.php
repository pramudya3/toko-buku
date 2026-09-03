<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_purchase_id
 * @property int $book_id
 * @property int $qty
 * @property int $price
 * @property int $subtotal
 */
#[Fillable(['supplier_purchase_id', 'book_id', 'book_edition_id', 'qty', 'price', 'subtotal', 'stock_before', 'hpp_old', 'hpp_new', 'landed_cost'])]

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

    /**
     * @return BelongsTo<BookEdition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(BookEdition::class, 'book_edition_id');
    }

    /**
     * @return HasMany<SupplierPurchaseItemAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPurchaseItemAllocation::class, 'supplier_purchase_item_id');
    }

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'integer',
            'subtotal' => 'integer',
            'stock_before' => 'integer',
            'hpp_old' => 'integer',
            'hpp_new' => 'integer',
            'landed_cost' => 'integer',
        ];
    }
}
