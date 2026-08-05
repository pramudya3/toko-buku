<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\Warehouse;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manajemen stok multi-gudang (INV-01..08, BR-06..07).
 *
 * Semua mutasi wajib melalui service ini: validasi saldo, catat audit trail,
 * sinkronisasi agregat books.stok, dan memastikan stok defect tidak pernah dijual.
 */
final class InventoryService
{
    /**
     * Eksekusi mutasi stok + audit trail dalam 1 transaksi atomik.
     */
    public function move(
        Book $book,
        MovementType $type,
        int $qty,
        ?Warehouse $from = null,
        ?Warehouse $to = null,
        ?string $reference = null,
        ?int $userId = null,
        ?string $notes = null,
    ): InventoryMovement {
        if ($qty <= 0) {
            throw new RuntimeException('Jumlah mutasi harus lebih dari 0.');
        }

        $this->validateMovement($type, $from, $to);

        return DB::transaction(function () use ($book, $type, $qty, $from, $to, $reference, $userId, $notes): InventoryMovement {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail((int) $book->getKey());

            /** @var InventoryStock $stock */
            $stock = $lockedBook->inventoryStock()->lockForUpdate()->firstOrCreate(
                ['book_id' => $lockedBook->id],
                [
                    'stock_malang' => 0,
                    'stock_sidoarjo' => 0,
                    'stock_defect' => 0,
                ],
            );

            $this->applyMovement($stock, $type, $qty, $from, $to);

            $movement = InventoryMovement::create([
                'book_id' => $lockedBook->id,
                'from_warehouse' => $from,
                'to_warehouse' => $to,
                'qty' => $qty,
                'type' => $type,
                'reference' => $reference,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            $this->syncBookStock($lockedBook, $stock);

            return $movement;
        });
    }

    /**
     * Pastikan setiap buku memiliki baris stok yang menjadi sumber kebenaran.
     */
    public function ensureStock(Book $book): InventoryStock
    {
        return DB::transaction(function () use ($book): InventoryStock {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail((int) $book->getKey());

            /** @var InventoryStock $stock */
            $stock = $lockedBook->inventoryStock()->lockForUpdate()->firstOrCreate(
                ['book_id' => $lockedBook->id],
                [
                    'stock_malang' => 0,
                    'stock_sidoarjo' => 0,
                    'stock_defect' => 0,
                ],
            );

            $this->syncBookStock($lockedBook, $stock);

            return $stock;
        });
    }

    /**
     * Deduksi stok gudang asal saat order selesai (ORD-06).
     * Dipanggil di dalam transaksi AccountingService (BR-06).
     */
    public function deductForOrder(Order $order, ?int $userId): void
    {
        if ($order->warehouse_origin === null || ! $order->warehouse_origin->isSellable()) {
            throw new RuntimeException('Order tidak memiliki gudang asal yang valid.');
        }

        $order->loadMissing('items.book');

        foreach ($order->items->sortBy('book_id') as $item) {
            $this->move(
                book: $item->book,
                type: MovementType::Out,
                qty: $item->qty,
                from: $order->warehouse_origin,
                reference: "order-{$order->id}",
                userId: $userId,
                notes: "Deduksi stok order {$order->no_order}",
            );
        }
    }

    /**
     * Stok normal (malang + sidoarjo) — defect tidak pernah dihitung (INV-05, BR-07).
     */
    public function availableStock(Book $book): int
    {
        $stock = $book->inventoryStock;

        return $stock === null ? $book->stok : $stock->availableStock();
    }

    /**
     * Validasi saldo cukup; dilempar RuntimeException bila tidak.
     */
    public function assertSufficientStock(Book $book, Warehouse $warehouse, int $qty): void
    {
        if ($qty <= 0) {
            throw new RuntimeException('Jumlah stok harus lebih dari 0.');
        }

        if (! $warehouse->isSellable()) {
            throw new RuntimeException('Stok defect tidak pernah dijual.');
        }

        $stock = $book->inventoryStock;

        $current = $stock === null
            ? 0
            : match ($warehouse) {
                Warehouse::Malang => $stock->stock_malang,
                Warehouse::Sidoarjo => $stock->stock_sidoarjo,
                Warehouse::Defect => $stock->stock_defect,
            };

        if ($current < $qty) {
            throw new RuntimeException(
                "Stok {$warehouse->label()} tidak mencukupi (tersedia {$current}, diminta {$qty}).",
            );
        }
    }

    /**
     * Sinkronisasi kolom agregat books.stok (INV-06).
     */
    public function syncBookStock(Book $book, ?InventoryStock $stock = null): void
    {
        $stock ??= $book->inventoryStock;

        if ($stock === null) {
            $book->update(['stok' => 0]);

            return;
        }

        $book->update(['stok' => $stock->availableStock()]);
    }

    /**
     * Terapkan mutasi ke baris InventoryStock (mutasi saldo antar gudang).
     */
    private function applyMovement(InventoryStock $stock, MovementType $type, int $qty, ?Warehouse $from, ?Warehouse $to): void
    {
        $column = fn (Warehouse $warehouse): string => match ($warehouse) {
            Warehouse::Malang => 'stock_malang',
            Warehouse::Sidoarjo => 'stock_sidoarjo',
            Warehouse::Defect => 'stock_defect',
        };

        match ($type) {
            MovementType::In => $this->increment($stock, $column($this->requiredWarehouse($to)), $qty),
            MovementType::Out => $this->decrement($stock, $column($this->requiredWarehouse($from)), $qty),
            MovementType::Transfer, MovementType::Defect => $this->transfer(
                $stock,
                $column($this->requiredWarehouse($from)),
                $column($this->requiredWarehouse($to)),
                $qty,
            ),
        };
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
        };
    }

    private function validateInbound(?Warehouse $from, ?Warehouse $to): void
    {
        if ($from !== null || $to === null || ! $to->isSellable()) {
            throw new RuntimeException('Stok masuk harus memiliki gudang tujuan normal.');
        }
    }

    private function validateOutbound(?Warehouse $from, ?Warehouse $to): void
    {
        if ($from === null || ! $from->isSellable() || $to !== null) {
            throw new RuntimeException('Stok keluar harus berasal dari gudang normal.');
        }
    }

    private function validateTransfer(?Warehouse $from, ?Warehouse $to): void
    {
        if (
            $from === null
            || $to === null
            || ! $from->isSellable()
            || ! $to->isSellable()
            || $from === $to
        ) {
            throw new RuntimeException('Transfer hanya boleh antar dua gudang normal yang berbeda.');
        }
    }

    private function validateDefect(?Warehouse $from, ?Warehouse $to): void
    {
        if ($from === null || ! $from->isSellable() || $to !== Warehouse::Defect) {
            throw new RuntimeException('Mutasi defect harus memindahkan stok normal ke gudang defect.');
        }
    }

    private function increment(InventoryStock $stock, string $column, int $qty): void
    {
        $stock->increment($column, $qty);
    }

    private function decrement(InventoryStock $stock, string $column, int $qty): void
    {
        $current = $stock->{$column} ?? 0;

        if ($current < $qty) {
            throw new RuntimeException('Saldo gudang tidak mencukupi untuk mutasi ini.');
        }

        $stock->decrement($column, $qty);
    }

    private function transfer(InventoryStock $stock, string $from, string $to, int $qty): void
    {
        $this->decrement($stock, $from, $qty);
        $this->increment($stock, $to, $qty);
    }
}
