<?php

namespace App\Models;

use App\Enums\PromotionType;
use App\Observers\PromotionObserver;
use Carbon\Carbon;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $promo_name
 * @property PromotionType $promo_type
 * @property int|null $discount_percentage
 * @property int|null $promo_value
 * @property int|null $bundle_qty
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 * @property bool $is_global (computed: true jika tidak ada buku terlampir)
 */
#[Fillable([
    'promo_name', 'promo_type', 'discount_percentage', 'promo_value',
    'bundle_qty', 'start_date', 'end_date', 'is_active',
])]
#[ObservedBy([PromotionObserver::class])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, SoftDeletes;

    use HasUuids;

    /**
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'promotion_book')->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'promo_type' => PromotionType::class,
            'discount_percentage' => 'integer',
            'promo_value' => 'integer',
            'bundle_qty' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Global jika tidak ada buku terlampir.
     */
    protected function isGlobal(): Attribute
    {
        return Attribute::get(fn () => $this->books()->doesntExist());
    }

    /**
     * Promo berlaku hari ini (BR-02).
     */
    public function isActiveToday(): bool
    {
        $today = now()->startOfDay();

        return $this->is_active
            && $this->start_date->lte($today)
            && $this->end_date->gte($today);
    }
}
