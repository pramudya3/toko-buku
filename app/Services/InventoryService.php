<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\BookEditionStock;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manajemen stok multi-gudang per cetakan (INV-01..08, BR-06..07).
 *
 * Semua mutasi wajib melalui service ini: validasi saldo, catat audit trail,
 * sinkronisasi agregat (inventory_stocks per buku + books.stok), dan
 * memastikan stok defect tidak pernah dijual.
 *
 * Sejak stok per cetakan aktif, mutasi selalu menunjuk ke sebuah cetakan
 * (BookEdition). Bila pemanggil tidak menyebut cetakan, dipakai cetakan aktif
 * (atau cetakan pertama). Baris inventory_stocks per buku adalah mirror dari
 * jumlah stok semua cetakan buku tsb.
 */
final class InventoryService
{
    /**
     * Eksekusi mutasi stok + audit trail dalam 1 transaksi atomik.
     *
     * @param  BookEdition|null  $edition  Cetakan tujuan; null = cetakan aktif/pertama.
     */
    public function move(
        Book $book,
        MovementType $type,
        int $qty,
        ?Warehouse $from = null,
        ?Warehouse $to = null,
        ?string $reference = null,
        ?string $userId = null,
        ?string $notes = null,
        ?BookEdition $edition = null,
    ): InventoryMovement {
        if ($qty <= 0) {
            throw new RuntimeException('Jumlah mutasi harus lebih dari 0.');
        }

        $this->validateMovement($type, $from, $to);

        return DB::transaction(function () use ($book, $type, $qty, $from, $to, $reference, $userId, $notes, $edition): InventoryMovement {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->getKey());

            $lockedEdition = $this->resolveEdition($lockedBook, $edition);

            $this->applyMovement($lockedBook, $type, $qty, $from, $to, $lockedEdition);

            $movement = InventoryMovement::create([
                'book_id' => $lockedBook->id,
                'book_edition_id' => $lockedEdition?->id,
                'from_warehouse_id' => $from?->id,
                'to_warehouse_id' => $to?->id,
                'qty' => $qty,
                'type' => $type,
                'reference' => $reference,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            $this->syncBookStock($lockedBook);

            return $movement;
        });
    }

    /**
     * Pastikan setiap buku memiliki baris stok untuk gudang tertentu.
     */
    public function ensureStock(Book $book, ?Warehouse $warehouse = null): InventoryStock
    {
        $warehouse ??= Warehouse::default();

        return DB::transaction(function () use ($book, $warehouse): InventoryStock {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->getKey());

            return $lockedBook->inventoryStocks()->firstOrCreate(
                ['warehouse_id' => $warehouse->id],
                ['qty' => 0],
            );
        });
    }

    /**
     * Pastikan sebuah cetakan memiliki baris stok di gudang tertentu.
     */
    public function ensureEditionStock(BookEdition $edition, ?Warehouse $warehouse = null): BookEditionStock
    {
        $warehouse ??= Warehouse::default();

        return $edition->stocks()->firstOrCreate(
            ['warehouse_id' => $warehouse->id],
            ['qty' => 0],
        );
    }

    /**
     * Deduksi stok gudang asal saat order selesai (BR-06). Setiap item
     * memotong stok cetakan yang dibeli.
     */
    /**
     * Deduksi stok utk order (reserve saat diproses) — barang keluar dari
     * gudang asal, per cetakan (BR-05, INV-03).
     */
    public function deductForOrder(Order $order, ?string $userId): void
    {
        $warehouse = $order->warehouse_origin !== null
            ? Warehouse::query()->sellable()->where('kode', $order->warehouse_origin)->first()
            : null;

        if ($warehouse === null) {
            throw new RuntimeException('Order tidak memiliki gudang asal yang valid.');
        }

        $order->loadMissing('items.book', 'items.edition');

        foreach ($order->items->sortBy('book_id') as $item) {
            $this->move(
                book: $item->book,
                type: MovementType::Out,
                qty: $item->qty,
                from: $warehouse,
                reference: "order-{$order->id}",
                userId: $userId,
                notes: "Deduksi stok order {$order->no_order}",
                edition: $item->edition,
            );
        }
    }

    /**
     * Kembalikan stok order yang dibatalkan dari status diproses —
     * kebalikan dari deductForOrder (BR-05).
     */
    public function restoreForOrder(Order $order, ?string $userId): void
    {
        $warehouse = $order->warehouse_origin !== null
            ? Warehouse::query()->sellable()->where('kode', $order->warehouse_origin)->first()
            : null;

        if ($warehouse === null) {
            throw new RuntimeException('Order tidak memiliki gudang asal yang valid.');
        }

        $order->loadMissing('items.book', 'items.edition');

        foreach ($order->items->sortBy('book_id') as $item) {
            $this->move(
                book: $item->book,
                type: MovementType::In,
                qty: $item->qty,
                to: $warehouse,
                reference: "order-{$order->id}-batal",
                userId: $userId,
                notes: "Restore stok order batal {$order->no_order}",
                edition: $item->edition,
            );
        }
    }

    /**
     * Stok normal (semua gudang sellable) — defect tidak pernah dihitung (INV-05, BR-07).
     */
    public function availableStock(Book $book): int
    {
        return $book->inventoryStocks()
            ->whereHas('warehouse', fn ($query) => $query->sellable())
            ->sum('qty');
    }

    /**
     * Stok normal sebuah cetakan (semua gudang sellable).
     */
    public function availableEditionStock(BookEdition $edition): int
    {
        return $edition->stocks()
            ->whereHas('warehouse', fn ($query) => $query->sellable())
            ->sum('qty');
    }

    /**
     * Validasi saldo cukup; dilempar RuntimeException bila tidak.
     */
    public function assertSufficientStock(Book $book, Warehouse $warehouse, int $qty, ?BookEdition $edition = null): void
    {
        if ($qty <= 0) {
            throw new RuntimeException('Jumlah stok harus lebih dari 0.');
        }

        if ($warehouse->is_defect) {
            throw new RuntimeException('Stok defect tidak pernah dijual.');
        }

        $current = $edition !== null
            ? $edition->stocks()->where('warehouse_id', $warehouse->id)->sum('qty')
            : $book->inventoryStocks()->where('warehouse_id', $warehouse->id)->sum('qty');

        if ($current < $qty) {
            throw new RuntimeException(
                "Stok {$warehouse->nama} tidak mencukupi (tersedia {$current}, diminta {$qty}).",
            );
        }
    }

    /**
     * Sinkronisasi agregat: baris inventory_stocks per buku = jumlah stok
     * semua cetakan per gudang, lalu books.stok (INV-06).
     */
    public function syncBookStock(Book $book): void
    {
        $editionIds = $book->editions()->pluck('id');

        // Buku legacy tanpa cetakan: agregat dihitung dari baris buku langsung.
        if ($editionIds->isEmpty()) {
            $book->update(['stok' => $this->availableStock($book)]);

            return;
        }

        $sums = BookEditionStock::query()
            ->whereIn('book_edition_id', $editionIds)
            ->selectRaw('warehouse_id, SUM(qty) as total')
            ->groupBy('warehouse_id')
            ->pluck('total', 'warehouse_id');

        DB::transaction(function () use ($book, $sums): void {
            $book->inventoryStocks()->delete();

            foreach ($sums as $warehouseId => $total) {
                if ((int) $total > 0) {
                    $book->inventoryStocks()->create([
                        'warehouse_id' => $warehouseId,
                        'qty' => (int) $total,
                    ]);
                }
            }

            $book->update(['stok' => $this->availableStock($book)]);
        });
    }

    /**
     * Tentukan cetakan mutasi: yang diberikan, atau cetakan aktif/pertama.
     * Buku tanpa cetakan (legacy) → null (mutasi level buku).
     */
    private function resolveEdition(Book $book, ?BookEdition $edition): ?BookEdition
    {
        if ($edition !== null) {
            $locked = BookEdition::query()->lockForUpdate()->find($edition->id);

            if ($locked === null || $locked->book_id !== $book->id) {
                throw new RuntimeException('Cetakan tidak sesuai dengan buku.');
            }

            return $locked;
        }

        $first = $book->editions()->lockForUpdate()->orderBy('is_active', 'desc')->orderBy('cetakan_ke')->first();

        return $first;
    }

    /**
     * Terapkan mutasi ke baris stok cetakan (atau level buku bila tanpa cetakan).
     */
    private function applyMovement(Book $book, MovementType $type, int $qty, ?Warehouse $from, ?Warehouse $to, ?BookEdition $edition): void
    {
        match ($type) {
            MovementType::In => $this->incrementRow($book, $this->requiredWarehouse($to), $qty, $edition),
            MovementType::Out => $this->decrementRow($book, $this->requiredWarehouse($from), $qty, $edition),
            MovementType::Return => $this->decrementRow($book, $this->requiredWarehouse($from), $qty, $edition),
            MovementType::Transfer, MovementType::Defect => $this->transferRow(
                $book,
                $this->requiredWarehouse($from),
                $this->requiredWarehouse($to),
                $qty,
                $edition,
            ),
        };
    }

    private function incrementRow(Book $book, Warehouse $warehouse, int $qty, ?BookEdition $edition): void
    {
        if ($edition !== null) {
            $row = $this->ensureEditionStock($edition, $warehouse);
            $row->increment('qty', $qty);

            return;
        }

        $row = $book->inventoryStocks()->firstOrCreate(
            ['warehouse_id' => $warehouse->id],
            ['qty' => 0],
        );

        $row->increment('qty', $qty);
    }

    private function decrementRow(Book $book, Warehouse $warehouse, int $qty, ?BookEdition $edition): void
    {
        if ($edition !== null) {
            $current = $edition->stocks()->where('warehouse_id', $warehouse->id)->sum('qty');

            if ($current < $qty) {
                throw new RuntimeException("Saldo gudang {$warehouse->nama} tidak mencukupi untuk mutasi ini.");
            }

            $edition->stocks()->where('warehouse_id', $warehouse->id)->decrement('qty', $qty);

            return;
        }

        $current = $book->inventoryStocks()->where('warehouse_id', $warehouse->id)->sum('qty');

        if ($current < $qty) {
            throw new RuntimeException("Saldo gudang {$warehouse->nama} tidak mencukupi untuk mutasi ini.");
        }

        $book->inventoryStocks()->where('warehouse_id', $warehouse->id)->decrement('qty', $qty);
    }

    private function transferRow(Book $book, Warehouse $from, Warehouse $to, int $qty, ?BookEdition $edition): void
    {
        $this->decrementRow($book, $from, $qty, $edition);
        $this->incrementRow($book, $to, $qty, $edition);
    }

    private function requiredWarehouse(?Warehouse $warehouse): Warehouse
    {
        return $warehouse ?? throw new RuntimeException('Gudang mutasi wajib diisi.');
    }

    private function validateMovement(MovementType $type, ?Warehouse $from, ?Warehouse $to): void
    {
        match ($type) {
            MovementType::In => $this->validateInbound($from, $to),
            MovementType::Out => $this->validateOutbound($from, $to),
            MovementType::Transfer => $this->validateTransfer($from, $to),
            MovementType::Defect => $this->validateDefect($from, $to),
            MovementType::Return => $this->validateReturn($from, $to),
        };
    }

    private function validateInbound(?Warehouse $from, ?Warehouse $to): void
    {
        if ($from !== null || $to === null || $to->is_defect) {
            throw new RuntimeException('Stok masuk harus memiliki gudang tujuan normal.');
        }
    }

    private function validateOutbound(?Warehouse $from, ?Warehouse $to): void
    {
        if ($from === null || $from->is_defect || $to !== null) {
            throw new RuntimeException('Stok keluar harus berasal dari gudang normal.');
        }
    }

    private function validateTransfer(?Warehouse $from, ?Warehouse $to): void
    {
        if (
            $from === null
            || $to === null
            || $from->is_defect
            || $to->is_defect
            || $from->id === $to->id
        ) {
            throw new RuntimeException('Transfer hanya boleh antar dua gudang normal yang berbeda.');
        }
    }

    private function validateDefect(?Warehouse $from, ?Warehouse $to): void
    {
        if ($from === null || $from->is_defect || $to === null || ! $to->is_defect) {
            throw new RuntimeException('Mutasi defect harus memindahkan stok normal ke gudang defect.');
        }
    }

    private function validateReturn(?Warehouse $from, ?Warehouse $to): void
    {
        if ($from === null || ! $from->is_defect || $to !== null) {
            throw new RuntimeException('Retur supplier harus berasal dari gudang defect.');
        }
    }
}
