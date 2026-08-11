<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SupplierPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_id
 * @property string $ref_code
 * @property Carbon $purchase_date
 * @property int $total
 * @property string|null $warehouse_kode
 * @property string|null $notes
 * @property int|null $user_id
 */
#[Fillable(['supplier_id', 'ref_code', 'purchase_date', 'total', 'warehouse_kode', 'notes', 'user_id'])]

class SupplierPurchase extends Model
{
    /** @use HasFactory<SupplierPurchaseFactory> */
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
     * Gudang tujuan barang masuk — dipakai retur untuk menentukan gudang asal.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_kode', 'kode');
    }

    /**
     * @return HasMany<SupplierPurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierPurchaseItem::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @return HasMany<SupplierReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            // date:Y-m-d — tanggal murni tanpa konversi timezone.
            'purchase_date' => 'date:Y-m-d',
            'total' => 'integer',
        ];
    }
}
