<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SupplierReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $supplier_purchase_id
 * @property Carbon $return_date
 * @property int $total
 * @property string|null $notes
 * @property int|null $user_id
 */
#[Fillable(['supplier_id', 'supplier_purchase_id', 'return_date', 'total', 'shipping_cost', 'notes', 'user_id'])]

class SupplierReturn extends Model
{
    /** @use HasFactory<SupplierReturnFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<SupplierPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchase::class);
    }

    /**
     * @return HasMany<SupplierReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    protected function casts(): array
    {
        return [
            'return_date' => 'date:Y-m-d',
            'total' => 'integer',
            'shipping_cost' => 'integer',
        ];
    }
}
