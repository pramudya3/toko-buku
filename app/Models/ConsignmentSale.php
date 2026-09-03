<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Laporan laku konsinyasi dari mitra — momen pengakuan penjualan.
 * Totalnya otomatis menjadi piutang (Receivable) mitra.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $sale_date
 * @property string|null $notes
 * @property string|null $receivable_id
 * @property string|null $user_id
 */
#[Fillable(['kode', 'customer_id', 'sale_date', 'notes', 'receivable_id', 'user_id'])]
class ConsignmentSale extends Model
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
     * Piutang yang dibuat otomatis dari laporan laku ini.
     *
     * @return BelongsTo<Receivable, $this>
     */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    /**
     * @return HasMany<ConsignmentSaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentSaleItem::class, 'sale_id');
    }

    protected function casts(): array
    {
        return [
            'sale_date' => 'date:Y-m-d',
        ];
    }
}
