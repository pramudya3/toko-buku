<?php

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\InventoryMovementFactory;
use App\Enums\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $book_id
 * @property Warehouse|null $from_warehouse
 * @property Warehouse|null $to_warehouse
 * @property int $qty
 * @property MovementType $type
 * @property string|null $reference
 * @property int|null $user_id
 * @property string|null $notes
 */
#[Fillable([
    'book_id', 'from_warehouse', 'to_warehouse', 'qty', 'type',
    'reference', 'user_id', 'notes',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

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

    protected function casts(): array
    {
        return [
            'from_warehouse' => Warehouse::class,
            'to_warehouse' => Warehouse::class,
            'qty' => 'integer',
            'type' => MovementType::class,
        ];
    }
}
