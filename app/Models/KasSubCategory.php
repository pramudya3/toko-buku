<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sub Kategori Kas — anak dari KasCategory, sesuai Kategori.md.
 *
 * @property string $id
 * @property string $cash_flow_category_id
 * @property string $nama
 */
#[Fillable(['cash_flow_category_id', 'nama', 'description', 'sort_order', 'is_active'])]
class KasSubCategory extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'cash_flow_sub_categories';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<KasCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KasCategory::class, 'cash_flow_category_id');
    }

    /**
     * @return HasMany<CashFlow, $this>
     */
    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class, 'kas_sub_category_id');
    }
}
