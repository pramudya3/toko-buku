<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $supplier_purchase_id
 * @property Carbon $payment_date
 * @property int $amount
 * @property string|null $notes
 * @property int|null $user_id
 */
#[Fillable(['supplier_id', 'supplier_purchase_id', 'payment_date', 'amount', 'notes', 'user_id'])]

class SupplierPayment extends Model
{
    /** @use HasFactory<SupplierPaymentFactory> */
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

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount' => 'integer',
        ];
    }
}
