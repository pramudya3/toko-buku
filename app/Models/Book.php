<?php

namespace App\Models;

use App\Observers\BookObserver;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 * @property string|null $penterjemah
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
 * @property string|null $preorder_eta
 * @property string|null $rating_umur
 * @property string|null $dimensi
 * @property string|null $kemasan
 * @property int|null $berat_gr
 * @property int|null $jumlah_halaman
 * @property string|null $jenis_kertas
 * @property string|null $cetakan
 * @property string|null $bahasa
 * @property string|null $jenis_cover
 */
#[Fillable([
    'kode_sku', 'judul', 'penulis', 'penterjemah', 'penerbit', 'tahun', 'isbn', 'sinopsis',
    'harga', 'stok', 'category_id', 'cover_url', 'aktif', 'is_preorder', 'preorder_eta',
    'rating_umur', 'dimensi', 'kemasan', 'berat_gr',
    'jumlah_halaman', 'jenis_kertas', 'cetakan', 'bahasa', 'jenis_cover',
])]
#[ObservedBy([BookObserver::class])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

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
     * @return HasMany<InventoryStock, $this>
     */
    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * @return HasMany<BookEdition, $this>
     */
    public function editions(): HasMany
    {
        return $this->hasMany(BookEdition::class);
    }

    /**
     * Galeri gambar buku, diurutkan sesuai urutan tampil.
     *
     * @return HasMany<BookImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(BookImage::class)->orderBy('urutan');
    }

    /**
     * Edisi aktif (atau edisi pertama jika tidak ada yang ditandai aktif).
     *
     * @return HasOne<BookEdition, $this>
     */
    public function activeEdition(): HasOne
    {
        return $this->hasOne(BookEdition::class)->where('is_active', true);
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
            'preorder_eta' => 'date',
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
     * Stok per gudang untuk tampilan list.
     *
     * @return array{warehouses: array<int, array{kode: string, nama: string, qty: int}>, total: int}
     */
    public function stockBreakdown(): array
    {
        $stocks = $this->inventoryStocks()->with('warehouse')->get();

        $warehouses = $stocks
            ->filter(fn (InventoryStock $stock): bool => $stock->qty > 0)
            ->map(fn (InventoryStock $stock): array => [
                'kode' => $stock->warehouse->kode,
                'nama' => $stock->warehouse->nama,
                'qty' => $stock->qty,
            ])
            ->values()
            ->all();

        $total = $stocks
            ->filter(fn (InventoryStock $stock): bool => ! $stock->warehouse->is_defect)
            ->sum('qty');

        return [
            'warehouses' => $warehouses,
            'total' => $total > 0 ? $total : $this->stok,
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
