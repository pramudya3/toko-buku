<?php

namespace App\Models;

use Database\Factories\BookEditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $book_id
 * @property int $cetakan_ke
 * @property string|null $nama
 * @property int $harga_beli
 * @property int $harga_jual
 * @property string|null $harga_guru_type
 * @property int|null $harga_guru_value
 * @property bool $is_active
 */
#[Fillable(['book_id', 'cetakan_ke', 'nama', 'harga_beli', 'harga_jual', 'harga_guru_type', 'harga_guru_value', 'is_active'])]
class BookEdition extends Model
{
    /** @use HasFactory<BookEditionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<BookEditionStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(BookEditionStock::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Stok normal cetakan ini (semua gudang sellable) — defect tidak pernah dihitung.
     */
    public function stockTotal(): int
    {
        return (int) $this->stocks()
            ->whereHas('warehouse', fn ($query) => $query->sellable())
            ->sum('qty');
    }

    /**
     * Stok cetakan ini di gudang tertentu.
     */
    public function stockAt(?Warehouse $warehouse): int
    {
        if ($warehouse === null) {
            return 0;
        }

        return (int) $this->stocks()->where('warehouse_id', $warehouse->id)->sum('qty');
    }

    protected function casts(): array
    {
        return [
            'cetakan_ke' => 'integer',
            'nama' => 'string',
            'harga_beli' => 'integer',
            'harga_jual' => 'integer',
            'harga_guru_type' => 'string',
            'harga_guru_value' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Harga guru efektif (rupiah) untuk cetakan ini.
     * null = tidak ada harga guru (fallback ke harga_jual normal).
     */
    public function guruPrice(): ?int
    {
        if ($this->harga_guru_type === null || $this->harga_guru_value === null) {
            return null;
        }

        if ($this->harga_guru_type === 'percent') {
            $percent = max(0, min(100, (int) $this->harga_guru_value));

            return intdiv($this->harga_jual * (100 - $percent), 100);
        }

        if ($this->harga_guru_type === 'fixed') {
            return (int) $this->harga_guru_value;
        }

        return null;
    }

    /**
     * Apakah cetakan ini punya harga guru yang valid.
     */
    public function hasGuruPrice(): bool
    {
        return $this->guruPrice() !== null;
    }
}
