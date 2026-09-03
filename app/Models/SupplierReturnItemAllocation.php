<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItemAllocation extends Model
{
    use HasUuids;

    protected $table = 'supplier_return_item_allocations';

    protected $fillable = [
        'supplier_return_item_id',
        'warehouse_kode',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    /**
     * @return BelongsTo<SupplierReturnItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplierReturnItem::class, 'supplier_return_item_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_kode', 'kode');
    }
}
