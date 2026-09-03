<?php

namespace App\Models;

use Database\Factories\SupplierReturnReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property string $type
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'category', 'type', 'is_active', 'sort_order'])]
class SupplierReturnReason extends Model
{
    /** @use HasFactory<SupplierReturnReasonFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'reasons';

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
