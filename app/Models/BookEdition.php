<?php

namespace App\Models;

use Database\Factories\BookEditionFactory;
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
 * @property bool $is_active
 */
class BookEdition extends Model
{
    /** @use HasFactory<BookEditionFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'book_id',
        'cetakan_ke',
        'nama',
        'harga_beli',
        'harga_jual',
        'is_active',
    ];

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
            'is_active' => 'boolean',
        ];
    }
}
