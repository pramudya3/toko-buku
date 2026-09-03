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
 * @property string $id
 * @property string|null $order_id
 * @property Carbon $entry_date
 * @property FlowType $flow_type
 * @property int $amount
 * @property string|null $description
 * @property string|null $kas_category_id
 * @property string|null $kas_sub_category_id
 */
#[Fillable(['order_id', 'entry_date', 'flow_type', 'amount', 'description', 'kas_category_id', 'kas_sub_category_id'])]
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

    /**
     * @return BelongsTo<KasCategory, $this>
     */
    public function kasCategory(): BelongsTo
    {
        return $this->belongsTo(KasCategory::class, 'kas_category_id');
    }

    /**
     * @return BelongsTo<KasSubCategory, $this>
     */
    public function kasSubCategory(): BelongsTo
    {
        return $this->belongsTo(KasSubCategory::class, 'kas_sub_category_id');
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
