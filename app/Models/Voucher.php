<?php

namespace App\Models;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Observers\VoucherObserver;
use Carbon\Carbon;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $nama
 * @property string|null $kode
 * @property VoucherType $voucher_type
 * @property VoucherScope $discount_scope
 * @property int|null $discount_percentage
 * @property int|null $discount_value
 * @property int $min_order_amount
 * @property int|null $max_uses
 * @property int|null $max_uses_per_user
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 * @property int $usages_count (computed via withCount)
 * @property int $user_usages_count (computed via withCount)
 * @property-read string $discount_label
 */
#[Fillable([
    'nama', 'kode', 'voucher_type', 'discount_scope', 'discount_percentage', 'discount_value',
    'min_order_amount', 'max_uses', 'max_uses_per_user', 'start_date', 'end_date', 'is_active',
])]
#[ObservedBy([VoucherObserver::class])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory, SoftDeletes;

    use HasUuids;

    /**
     * @return HasMany<VoucherUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    protected function casts(): array
    {
        return [
            'voucher_type' => VoucherType::class,
            'discount_scope' => VoucherScope::class,
            'discount_percentage' => 'integer',
            'discount_value' => 'integer',
            'min_order_amount' => 'integer',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Voucher berlaku hari ini.
     */
    public function isActiveToday(): bool
    {
        $today = now()->startOfDay();

        return $this->is_active
            && $this->start_date->lte($today)
            && $this->end_date->gte($today);
    }

    /**
     * Label diskon untuk ditampilkan di UI (mis. "10%" / "Rp 25.000").
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function discountLabel(): Attribute
    {
        return Attribute::get(function (): string {
            return match ($this->voucher_type) {
                VoucherType::Percentage => "{$this->discount_percentage}%",
                VoucherType::Fixed => 'Rp '.number_format($this->discount_value ?? 0, 0, ',', '.'),
            };
        });
    }
}
