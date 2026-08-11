<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $tier
 * @property int $min_qty
 * @property int|null $max_qty
 * @property int $discount_percent
 */
#[Fillable(['tier', 'min_qty', 'max_qty', 'discount_percent'])]
class TierDiscount extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'discount_percent' => 'integer',
        ];
    }
}
