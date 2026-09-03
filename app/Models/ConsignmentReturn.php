<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retur sisa barang konsinyasi dari mitra — stok kembali ke toko.
 *
 * @property string $id
 * @property string $customer_id
 * @property string|null $warehouse_id
 * @property string $book_id
 * @property int $qty
 * @property string $return_date
 * @property string|null $notes
 * @property string|null $user_id
 */
#[Fillable(['kode', 'customer_id', 'warehouse_id', 'book_id', 'qty', 'return_date', 'notes', 'user_id'])]
class ConsignmentReturn extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
            'return_date' => 'date:Y-m-d',
        ];
    }
}
