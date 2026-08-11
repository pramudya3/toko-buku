<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran piutang (cicilan / pelunasan).
 *
 * @property int $id
 * @property int $receivable_id
 * @property string $paid_at
 * @property int $amount
 * @property string $metode
 * @property string|null $notes
 */
class ReceivablePayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'receivable_id',
        'paid_at',
        'amount',
        'metode',
        'notes',
    ];

    /**
     * @return BelongsTo<Receivable, $this>
     */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'date:Y-m-d',
            'amount' => 'integer',
        ];
    }
}
