<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item serah terima konsinyasi — qty per buku yang dititipkan.
 *
 * @property string $id
 * @property string $delivery_id
 * @property string $book_id
 * @property int $qty
 * @property int $harga_asli
 * @property int $harga_titip
 */
#[Fillable(['delivery_id', 'book_id', 'qty', 'harga_asli', 'harga_titip'])]
class ConsignmentDeliveryItem extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<ConsignmentDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ConsignmentDelivery::class, 'delivery_id');
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
            'harga_asli' => 'integer',
            'harga_titip' => 'integer',
        ];
    }
}
