<?php

namespace App\Models;

use Database\Factories\InventoryStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $book_id
 * @property int $stock_malang
 * @property int $stock_sidoarjo
 * @property int $stock_defect
 */
#[Fillable(['book_id', 'stock_malang', 'stock_sidoarjo', 'stock_defect'])]
class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use HasFactory;

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
            'stock_malang' => 'integer',
            'stock_sidoarjo' => 'integer',
            'stock_defect' => 'integer',
        ];
    }

    /**
     * Total stok normal (malang + sidoarjo). Defect tidak pernah dihitung.
     */
    public function availableStock(): int
    {
        return $this->stock_malang + $this->stock_sidoarjo;
    }
}
