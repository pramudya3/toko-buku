<?php

namespace App\Models;

use App\Enums\FlowType;
use Carbon\Carbon;
use Database\Factories\CashFlowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $order_id
 * @property Carbon $entry_date
 * @property FlowType $flow_type
 * @property int $amount
 * @property string|null $description
 */
#[Fillable(['order_id', 'entry_date', 'flow_type', 'amount', 'description'])]
class CashFlow extends Model
{
    /** @use HasFactory<CashFlowFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'flow_type' => FlowType::class,
            'amount' => 'integer',
        ];
    }
}
