<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retur penjualan — barang dikembalikan pembeli (stok kembali ke gudang,
 * refund dicatat di cash_flows).
 *
 * @property int $id
 * @property int $order_id
 * @property string $return_date
 * @property int $total_refund
 * @property string|null $notes
 * @property int|null $user_id
 */
class SalesReturn extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'return_date',
        'total_refund',
        'notes',
        'user_id',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<SalesReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
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
            'return_date' => 'date:Y-m-d',
            'total_refund' => 'integer',
        ];
    }
}
