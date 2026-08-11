<?php

namespace App\Models;

use App\Observers\WarehouseObserver;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property string|null $alamat
 * @property bool $is_defect
 * @property bool $is_active
 */
#[Fillable(['kode', 'nama', 'alamat', 'is_defect', 'is_active'])]

#[ObservedBy([WarehouseObserver::class])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Gudang normal (bukan defect) dan aktif.
     *
     * @return Builder<Warehouse>
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_defect', false)->where('is_active', true);
    }

    /**
     * Gudang khusus barang cacat (hanya boleh satu).
     *
     * @return Builder<Warehouse>
     */
    public function scopeDefect(Builder $query): Builder
    {
        return $query->where('is_defect', true);
    }

    /**
     * @return HasMany<InventoryStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * Ambil gudang default (kode malang) atau gudang normal pertama.
     */
    public static function default(): self
    {
        return self::query()->sellable()->where('kode', 'malang')->first()
            ?? self::query()->sellable()->first()
            ?? self::query()->firstOrCreate(
                ['kode' => 'malang'],
                ['nama' => 'Malang', 'is_defect' => false, 'is_active' => true],
            );
    }

    /**
     * Gudang defect — dibuatkan otomatis bila belum ada.
     */
    public static function defect(): self
    {
        return self::query()->defect()->first()
            ?? self::query()->firstOrCreate(
                ['kode' => 'defect'],
                ['nama' => 'Defect', 'is_defect' => true, 'is_active' => true],
            );
    }

    protected function casts(): array
    {
        return [
            'is_defect' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
