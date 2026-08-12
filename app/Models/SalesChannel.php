<?php

namespace App\Models;

use Database\Factories\SalesChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sumber penjualan (sales channel) yang dikelola admin — mis. toko, Shopee,
 * Tokopedia. Dipakai sebagai opsi "Pembelian dari" pada order manual.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'is_active', 'sort_order'])]
class SalesChannel extends Model
{
    /** @use HasFactory<SalesChannelFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
