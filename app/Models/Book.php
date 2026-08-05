<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $kode_sku
 * @property string $judul
 * @property string|null $penulis
 * @property string|null $penerbit
 * @property int|null $tahun
 * @property string|null $isbn
 * @property string|null $sinopsis
 * @property int $harga
 * @property int $stok
 * @property int|null $category_id
 * @property string|null $cover_url
 * @property bool $aktif
 * @property bool $is_preorder
 * @property string|null $po_label
 */
#[Fillable([
    'kode_sku', 'judul', 'penulis', 'penerbit', 'tahun', 'isbn', 'sinopsis',
    'harga', 'stok', 'category_id', 'cover_url', 'aktif', 'is_preorder', 'po_label',
    'rating_umur', 'dimensi', 'kemasan', 'berat_gr',
    'jumlah_halaman', 'jenis_kertas', 'cetakan',
])]
class Book extends Model
{
    use SoftDeletes;

    /** @use HasFactory<BookFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Promotion, $this>
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_book')->withTimestamps();
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<InventoryStock, $this>
     */
    public function inventoryStock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'harga' => 'integer',
            'stok' => 'integer',
            'aktif' => 'boolean',
            'is_preorder' => 'boolean',
            'berat_gr' => 'integer',
            'jumlah_halaman' => 'integer',
        ];
    }

    /**
     * True ketika buku sudah pernah dipesan (riwayat order_items).
     */
    public function hasOrderHistory(): bool
    {
        return $this->orderItems()->exists();
    }

    /**
     * Nama gudang + stok untuk tampilan list.
     *
     * @return array{malang: int, sidoarjo: int, defect: int, total: int}
     */
    public function stockBreakdown(): array
    {
        $stock = $this->inventoryStock;

        if ($stock === null) {
            return [
                'malang' => 0,
                'sidoarjo' => 0,
                'defect' => 0,
                'total' => $this->stok,
            ];
        }

        return [
            'malang' => $stock->stock_malang,
            'sidoarjo' => $stock->stock_sidoarjo,
            'defect' => $stock->stock_defect,
            'total' => $stock->stock_malang + $stock->stock_sidoarjo,
        ];
    }

    /**
     * Label tampilan harga (rupiah) — dipakai untuk badge/placeholder.
     */
    /**
     * @return Attribute<non-falsy-string, never>
     */
    protected function hargaLabel(): Attribute
    {
        return Attribute::get(fn (): string => 'Rp '.number_format($this->harga, 0, ',', '.'));
    }
}
