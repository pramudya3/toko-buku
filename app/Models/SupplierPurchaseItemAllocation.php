<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $supplier_purchase_item_id
 * @property string $warehouse_kode
 * @property int $qty
 */
class SupplierPurchaseItemAllocation extends Model
{
    use HasUuids;

    protected $table = 'supplier_purchase_item_allocations';

    protected $fillable = [
        'supplier_purchase_item_id',
        'warehouse_kode',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    /**
     * @return BelongsTo<SupplierPurchaseItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseItem::class, 'supplier_purchase_item_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_kode', 'kode');
    }
}
