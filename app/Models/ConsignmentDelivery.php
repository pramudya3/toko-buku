<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Serah terima barang konsinyasi ke mitra (tier Bazaf). Barang belum
 * terjual — stok toko berkurang dan menjadi titipan di mitra.
 *
 * @property string $id
 * @property string $customer_id
 * @property string|null $warehouse_id
 * @property string $delivery_date
 * @property string|null $notes
 * @property string|null $user_id
 */
#[Fillable(['kode', 'customer_id', 'warehouse_id', 'delivery_date', 'notes', 'user_id'])]
class ConsignmentDelivery extends Model
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
     * @return HasMany<ConsignmentDeliveryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentDeliveryItem::class, 'delivery_id');
    }

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date:Y-m-d',
        ];
    }
}
