<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transaksi supplier (pembelian, retur cacat, pembayaran hutang).
 *
 * Semua transaksi atomik: header + item + mutasi stok via InventoryService
 * sehingga audit trail inventori selalu sinkron dengan catatan supplier.
 */
final class SupplierService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Catat pembelian: stok masuk gudang tujuan + hutang bertambah.
     * Bila ada pembayaran langsung, catat sebagai SupplierPayment.
     *
     * @param  array<int, array{book_id: int, qty: int, price: int}>  $items
     */
    public function recordPurchase(
        Supplier $supplier,
        string $refCode,
        string $purchaseDate,
        array $items,
        ?string $notes,
        int $paidAmount,
        ?string $userId,
        ?string $warehouseKode = null,
    ): SupplierPurchase {
        $warehouse = $warehouseKode !== null
            ? Warehouse::query()->where('kode', $warehouseKode)->first()
            : null;

        $warehouse ??= Warehouse::default();

        return DB::transaction(function () use ($supplier, $refCode, $purchaseDate, $items, $notes, $paidAmount, $userId, $warehouse): SupplierPurchase {
            $total = 0;

            foreach ($items as $item) {
                $total += $item['qty'] * $item['price'];
            }

            $purchase = $supplier->purchases()->create([
                'ref_code' => $refCode,
                'purchase_date' => $purchaseDate,
                'total' => $total,
                'warehouse_kode' => $warehouse->kode,
                'notes' => $notes,
                'user_id' => $userId,
            ]);

            foreach ($items as $item) {
                $book = Book::findOrFail($item['book_id']);

                $purchase->items()->create([
                    'book_id' => $book->id,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);

                $this->inventory->move(
                    book: $book,
                    type: MovementType::In,
                    qty: $item['qty'],
                    to: $warehouse,
                    reference: $refCode,
                    userId: $userId,
                    notes: "Pembelian dari {$supplier->nama}",
                );
            }

            if ($paidAmount > 0) {
                $supplier->payments()->create([
                    'supplier_purchase_id' => $purchase->id,
                    'payment_date' => $purchaseDate,
                    'amount' => $paidAmount,
                    'notes' => 'Pembayaran saat pembelian '.$refCode,
                    'user_id' => $userId,
                ]);
            }

            return $purchase;
        });
    }

    /**
     * Catat retur barang ke supplier — sumber stok per item:
     * - `defect`: dari gudang defect (alasan cacat, MovementType::Return).
     * - `normal`: dari gudang utama (alasan lain: salah kirim, kadaluarsa, dll —
     *   MovementType::Out). Hutang berkurang di kedua kasus.
     *
     * @param  array<int, array{book_id: int, qty: int, price: int, reason: string, source: string}>  $items
     */
    public function recordReturn(
        Supplier $supplier,
        string $returnDate,
        array $items,
        ?string $purchaseId,
        ?string $notes,
        ?string $userId,
    ): SupplierReturn {
        return DB::transaction(function () use ($supplier, $returnDate, $items, $purchaseId, $notes, $userId): SupplierReturn {
            // Validasi terhadap faktur: kepemilikan, qty, harga, dan total retur.
            $purchase = $this->validateReturnAgainstInvoice($supplier, $purchaseId, $items);

            // Gudang asal retur normal diambil OTOMATIS dari faktur (tujuan
            // gudang saat pembelian) — fallback gudang default tanpa faktur.
            $normalWarehouse = $purchase?->warehouse_kode !== null
                ? Warehouse::query()->where('kode', $purchase->warehouse_kode)->first()
                : null;

            $normalWarehouse ??= Warehouse::default();

            $total = 0;

            foreach ($items as $item) {
                $total += $item['qty'] * $item['price'];
            }

            $return = $supplier->returns()->create([
                'supplier_purchase_id' => $purchase?->id,
                'return_date' => $returnDate,
                'total' => $total,
                'notes' => $notes,
                'user_id' => $userId,
            ]);

            foreach ($items as $item) {
                $book = Book::findOrFail($item['book_id']);

                $return->items()->create([
                    'book_id' => $book->id,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'reason' => $item['reason'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);

                if (($item['source'] ?? 'defect') === 'normal') {
                    $this->inventory->move(
                        book: $book,
                        type: MovementType::Out,
                        qty: $item['qty'],
                        from: $normalWarehouse,
                        reference: 'RET-'.$return->id,
                        userId: $userId,
                        notes: "Retur ke {$supplier->nama}: {$item['reason']}",
                    );
                } else {
                    $this->inventory->move(
                        book: $book,
                        type: MovementType::Return,
                        qty: $item['qty'],
                        from: Warehouse::defect(),
                        reference: 'RET-'.$return->id,
                        userId: $userId,
                        notes: "Retur cacat ke {$supplier->nama}",
                    );
                }
            }

            return $return;
        });
    }

    /**
     * Validasi retur terhadap faktur (bila ditautkan):
     * faktur milik supplier, qty per buku ≤ qty dibeli, harga ≤ harga beli,
     * dan total retur ≤ sisa faktur (total − retur tertaut sebelumnya).
     *
     * Faktur tanpa baris item (data lama/factory) dilewati validasi per-item.
     *
     * @param  array<int, array{book_id: int, qty: int, price: int}>  $items
     */
    private function validateReturnAgainstInvoice(Supplier $supplier, ?string $purchaseId, array $items): ?SupplierPurchase
    {
        if ($purchaseId === null) {
            return null;
        }

        $purchase = $supplier->purchases()->with('items')->find($purchaseId);

        if ($purchase === null) {
            throw new RuntimeException('Faktur tidak ditemukan untuk supplier ini.');
        }

        $purchaseItems = $purchase->items->keyBy('book_id');
        $returnTotal = 0;

        foreach ($items as $item) {
            $purchaseItem = $purchaseItems->get($item['book_id']);
            $returnTotal += $item['qty'] * $item['price'];

            if ($purchaseItem !== null && $item['qty'] > $purchaseItem->qty) {
                throw new RuntimeException("Qty retur melebihi jumlah pembelian di faktur {$purchase->ref_code}.");
            }

            if ($purchaseItem !== null && $item['price'] > $purchaseItem->price) {
                throw new RuntimeException("Harga retur melebihi harga beli di faktur {$purchase->ref_code}.");
            }
        }

        $remaining = $purchase->total - (int) $purchase->returns()->sum('total');

        if ($returnTotal > $remaining) {
            throw new RuntimeException("Total retur melebihi sisa faktur {$purchase->ref_code}.");
        }

        return $purchase;
    }

    /**
     * Sisa hutang per faktur: total faktur − retur yang tertaut − pembayaran yang tertaut.
     */
    public function sisaPerFaktur(SupplierPurchase $purchase): int
    {
        return $purchase->total
            - $purchase->returns()->sum('total')
            - $purchase->payments()->sum('amount');
    }

    /**
     * Catat pembayaran hutang ke supplier.
     */
    public function recordPayment(
        Supplier $supplier,
        int $amount,
        string $paymentDate,
        ?string $purchaseId,
        ?string $notes,
        ?string $userId,
    ): SupplierPayment {
        if ($purchaseId !== null) {
            $purchase = $supplier->purchases()->find($purchaseId);

            if ($purchase === null) {
                throw new RuntimeException('Faktur tidak ditemukan untuk supplier ini.');
            }

            if ($amount > $this->sisaPerFaktur($purchase)) {
                throw new RuntimeException("Pembayaran melebihi sisa hutang faktur {$purchase->ref_code}.");
            }
        }

        return $supplier->payments()->create([
            'supplier_purchase_id' => $purchaseId,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'notes' => $notes,
            'user_id' => $userId,
        ]);
    }

    /**
     * Sisa hutang: total pembelian − total retur − total pembayaran.
     */
    public function saldoHutang(Supplier $supplier): int
    {
        return $supplier->purchases()->sum('total')
            - $supplier->returns()->sum('total')
            - $supplier->payments()->sum('amount');
    }
}
