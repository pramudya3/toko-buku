<?php

namespace App\Models;

use Database\Factories\DropshipperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $user_id
 * @property string $end_customer_name
 * @property string|null $end_customer_whatsapp
 * @property string|null $end_customer_address
 */
#[Fillable([
    'order_id', 'user_id', 'end_customer_name',
    'end_customer_whatsapp', 'end_customer_address',
])]
class Dropshipper extends Model
{
    /** @use HasFactory<DropshipperFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
