<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use App\Enums\PaymentMethod;
use App\Enums\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $no_order
 * @property int|null $user_id
 * @property string $nama_pembeli
 * @property string|null $no_hp
 * @property string|null $email_pembeli
 * @property string|null $alamat
 * @property string|null $provinsi
 * @property string|null $kabupaten_kota
 * @property string|null $kecamatan
 * @property string|null $kode_pos
 * @property string|null $nama_penerima
 * @property PaymentMethod $metode_bayar
 * @property int $total
 * @property int $shipping_cost
 * @property bool $is_dropship
 * @property Warehouse|null $warehouse_origin
 * @property OrderStatus $status
 * @property string|null $ekspedisi
 * @property int|null $ongkir_estimasi
 */
#[Fillable([
    'no_order', 'user_id', 'nama_pembeli', 'no_hp', 'email_pembeli', 'alamat',
    'provinsi', 'kabupaten_kota', 'kecamatan', 'kode_pos', 'nama_penerima',
    'metode_bayar', 'total', 'shipping_cost', 'is_dropship', 'warehouse_origin',
    'status', 'ekspedisi', 'ongkir_estimasi',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<Dropshipper, $this>
     */
    public function dropshipper(): HasOne
    {
        return $this->hasOne(Dropshipper::class);
    }

    /**
     * @return HasMany<CashFlow, $this>
     */
    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class);
    }

    protected function casts(): array
    {
        return [
            'metode_bayar' => PaymentMethod::class,
            'total' => 'integer',
            'shipping_cost' => 'integer',
            'is_dropship' => 'boolean',
            'warehouse_origin' => Warehouse::class,
            'status' => OrderStatus::class,
            'ongkir_estimasi' => 'integer',
        ];
    }

    /**
     * Subtotal produk (tanpa ongkir).
     */
    public function subtotal(): int
    {
        return $this->total - $this->shipping_cost;
    }
}
