<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 * @property string|null $warehouse_origin
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property string|null $ekspedisi
 * @property string|null $ongkir_estimasi
 */
#[Fillable([
    'no_order', 'user_id', 'nama_pembeli', 'no_hp', 'email_pembeli', 'alamat',
    'provinsi', 'kabupaten_kota', 'kecamatan', 'kode_pos', 'nama_penerima',
    'metode_bayar', 'total', 'shipping_cost', 'is_dropship', 'warehouse_origin',
    'status', 'payment_status', 'ekspedisi', 'ongkir_estimasi',
])]
#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUuids;

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
            // metode_bayar: string biasa — bisa berisi metode custom dari tabel.
            'total' => 'integer',
            'shipping_cost' => 'integer',
            'is_dropship' => 'boolean',
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
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
