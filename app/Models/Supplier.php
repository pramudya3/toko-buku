<?php

namespace App\Models;

use App\Observers\SupplierObserver;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $nama
 * @property string|null $telepon
 * @property string|null $alamat
 * @property string|null $catatan
 */
#[Fillable(['nama', 'telepon', 'alamat', 'catatan'])]

#[ObservedBy([SupplierObserver::class])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return HasMany<SupplierPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(SupplierPurchase::class);
    }

    /**
     * @return HasMany<SupplierReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Sisa hutang: total pembelian − total retur − total pembayaran.
     */
    public function saldoHutang(): int
    {
        return $this->purchases()->sum('total')
            - $this->returns()->sum('total')
            - $this->payments()->sum('amount');
    }
}
