<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stok per cetakan per gudang.
 *
 * @property int $id
 * @property int $book_edition_id
 * @property int $warehouse_id
 * @property int $qty
 */
#[Fillable(['book_edition_id', 'warehouse_id', 'qty'])]
class BookEditionStock extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<BookEdition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(BookEdition::class, 'book_edition_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }
}
