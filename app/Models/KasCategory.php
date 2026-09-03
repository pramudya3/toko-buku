<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kategori Kas (induk) — sesuai Kategori.md (9 kategori).
 *
 * @property string $id
 * @property string $nama
 * @property string|null $kode
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['nama', 'kode', 'sort_order', 'is_active'])]
class KasCategory extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'cash_flow_categories';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<KasSubCategory, $this>
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(KasSubCategory::class, 'cash_flow_category_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<CashFlow, $this>
     */
    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class, 'kas_category_id');
    }
}
