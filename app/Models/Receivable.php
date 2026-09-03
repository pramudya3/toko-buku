<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Piutang pelanggan — pelanggan yang tidak membayar penuh sekaligus.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $order_id
 * @property int $amount
 * @property int $paid_amount
 * @property string|null $due_date
 * @property string|null $notes
 */
#[Fillable(['customer_id', 'order_id', 'amount', 'paid_amount', 'due_date', 'notes'])]
class Receivable extends Model
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<ReceivablePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    /**
     * Sisa piutang yang belum terbayar.
     */
    public function remaining(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }

    public function isPaidOff(): bool
    {
        return $this->remaining() <= 0;
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_amount' => 'integer',
            'due_date' => 'date:Y-m-d',
        ];
    }
}
