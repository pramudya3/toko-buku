<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\BookEdition;
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
     * Mendukung multi-gudang (backward) & single-gudang + ongkir + per-cetakan.
     * Ongkir mempengaruhi HPP (landed cost) per cetakan secara weighted average.
     *
     * @param  array<int, array{book_id: string, book_edition_id?: string|null, qty?: int, price: int, allocations?: array<int, array{warehouse_kode: string, qty: int}>}>  $items
     * @param  array<int, string>|null  $warehouseKodes
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
        ?array $warehouseKodes = null,
        int $shippingCost = 0,
    ): SupplierPurchase {
        // Resolve daftar gudang tujuan (multi) — backward compat single warehouse_kode.
        $kodes = $warehouseKodes;
        if ($kodes === null || $kodes === []) {
            $kodes = $warehouseKode !== null ? [$warehouseKode] : [];
        }
        $kodes = array_values(array_unique(array_filter($kodes)));
        if ($kodes === []) {
            $default = Warehouse::default();
            $kodes = [$default->kode];
        }

        /** @var array<string, Warehouse> $warehousesByKode */
        $warehousesByKode = Warehouse::query()->whereIn('kode', $kodes)->get()->keyBy('kode')->all();
        foreach ($kodes as $kode) {
            if (! isset($warehousesByKode[$kode])) {
                throw new RuntimeException("Gudang {$kode} tidak ditemukan.");
            }
            if ($warehousesByKode[$kode]->is_defect) {
                throw new RuntimeException("Gudang {$kode} tidak boleh menjadi tujuan barang masuk.");
            }
        }
        $primaryWarehouse = $warehousesByKode[$kodes[0]];

        if ($shippingCost < 0) {
            throw new RuntimeException('Ongkos kirim tidak boleh negatif.');
        }

        return DB::transaction(function () use ($supplier, $refCode, $purchaseDate, $items, $notes, $paidAmount, $userId, $kodes, $warehousesByKode, $primaryWarehouse, $shippingCost): SupplierPurchase {
            // Normalisasi items: dukung allocations multi-gudang maupun qty tunggal (backward) + per-cetakan.
            $normalizedItems = [];
            $totalBarang = 0;
            $totalQty = 0;
            foreach ($items as $item) {
                $price = (int) $item['price'];
                $bookEditionId = $item['book_edition_id'] ?? null;
                if ($bookEditionId !== null && $bookEditionId !== '') {
                    $editionExists = BookEdition::where('id', $bookEditionId)->where('book_id', $item['book_id'])->exists();
                    if (! $editionExists) {
                        throw new RuntimeException('Cetakan tidak sesuai dengan buku.');
                    }
                } else {
                    $bookEditionId = null;
                }
                $allocations = $item['allocations'] ?? null;
                if (is_array($allocations) && $allocations !== []) {
                    $normalizedAlloc = [];
                    $qtySum = 0;
                    foreach ($allocations as $alloc) {
                        $q = (int) ($alloc['qty'] ?? 0);
                        if ($q < 0) {
                            throw new RuntimeException('Qty alokasi tidak boleh negatif.');
                        }
                        if ($q === 0) {
                            continue;
                        }
                        $wk = $alloc['warehouse_kode'];
                        if (! isset($warehousesByKode[$wk])) {
                            throw new RuntimeException("Gudang {$wk} tidak termasuk gudang tujuan.");
                        }
                        $normalizedAlloc[] = ['warehouse_kode' => $wk, 'qty' => $q];
                        $qtySum += $q;
                    }
                    if ($qtySum < 1) {
                        throw new RuntimeException('Total qty untuk buku harus minimal 1.');
                    }
                } else {
                    $qty = (int) ($item['qty'] ?? 0);
                    if ($qty < 1) {
                        throw new RuntimeException('Qty item minimal 1.');
                    }
                    $normalizedAlloc = [['warehouse_kode' => $kodes[0], 'qty' => $qty]];
                    $qtySum = $qty;
                }
                $subtotal = $qtySum * $price;
                $totalBarang += $subtotal;
                $totalQty += $qtySum;
                $normalizedItems[] = [
                    'book_id' => $item['book_id'],
                    'book_edition_id' => $bookEditionId,
                    'price' => $price,
                    'qty' => $qtySum,
                    'subtotal' => $subtotal,
                    'allocations' => $normalizedAlloc,
                ];
            }

            // HPP landed cost: ongkir dibagi proporsional qty (jika single gudang, semua ke 1 gudang)
            $shippingPerPcs = $totalQty > 0 && $shippingCost > 0 ? intdiv($shippingCost, $totalQty) : 0;
            $shippingRemainder = $totalQty > 0 && $shippingCost > 0 ? $shippingCost % $totalQty : 0;

            $purchase = $supplier->purchases()->create([
                'ref_code' => $refCode,
                'purchase_date' => $purchaseDate,
                'total' => $totalBarang,
                'shipping_cost' => $shippingCost,
                'warehouse_kode' => $primaryWarehouse->kode,
                'warehouse_kodes' => $kodes,
                'notes' => $notes,
                'user_id' => $userId,
            ]);

            $handledEditionIds = [];
            foreach ($normalizedItems as $nItem) {
                $book = Book::findOrFail($nItem['book_id']);
                $edition = null;
                if (! empty($nItem['book_edition_id'])) {
                    $edition = BookEdition::findOrFail($nItem['book_edition_id']);
                }

                // Hitung landed price per pcs untuk HPP (ongkir proporsional) — butuh sebelum snapshot
                $landedPricePerPcs = $nItem['price'] + $shippingPerPcs;
                if ($shippingRemainder > 0) {
                    $take = min($shippingRemainder, $nItem['qty']);
                    $shippingRemainder -= $take;
                    $landedPricePerPcs += intdiv($take, $nItem['qty']);
                }

                // Snapshot HPP sebelum update (untuk histori)
                $stockBefore = null;
                $hppOld = null;
                $hppNew = null;
                if ($edition) {
                    if (! in_array($edition->id, $handledEditionIds, true)) {
                        // Stock sebelum transaksi (belum di-move)
                        $stockBefore = (int) $edition->stocks()->sum('qty');
                        $hppOld = (int) $edition->harga_beli;
                        $hppNew = $stockBefore + $nItem['qty'] > 0
                            ? (int) round(($stockBefore * $hppOld + $nItem['qty'] * $landedPricePerPcs) / ($stockBefore + $nItem['qty']))
                            : $landedPricePerPcs;
                    } else {
                        // Edition sudah dihitung di item sebelumnya dalam faktur yang sama — snapshot ikut newHpp sebelumnya
                        $stockBefore = (int) $edition->stocks()->sum('qty');
                        $hppOld = (int) $edition->harga_beli;
                        $hppNew = $hppOld;
                    }
                }

                $purchaseItem = $purchase->items()->create([
                    'book_id' => $book->id,
                    'book_edition_id' => $edition?->id,
                    'qty' => $nItem['qty'],
                    'price' => $nItem['price'],
                    'subtotal' => $nItem['subtotal'],
                    'stock_before' => $stockBefore,
                    'hpp_old' => $hppOld,
                    'hpp_new' => $hppNew,
                    'landed_cost' => $edition ? $landedPricePerPcs : null,
                ]);

                foreach ($nItem['allocations'] as $alloc) {
                    $warehouse = $warehousesByKode[$alloc['warehouse_kode']];
                    $purchaseItem->allocations()->create([
                        'warehouse_kode' => $warehouse->kode,
                        'qty' => $alloc['qty'],
                    ]);

                    $this->inventory->move(
                        book: $book,
                        type: MovementType::In,
                        qty: $alloc['qty'],
                        to: $warehouse,
                        reference: $refCode,
                        userId: $userId,
                        notes: "Pembelian dari {$supplier->nama}",
                        edition: $edition,
                    );
                }

                // Update HPP per cetakan (weighted average) jika ada ongkir atau harga beli baru
                if ($edition) {
                    if (! in_array($edition->id, $handledEditionIds, true)) {
                        $handledEditionIds[] = $edition->id;
                        if ($hppNew !== null && $hppOld !== null && $hppNew !== $hppOld) {
                            $edition->update(['harga_beli' => $hppNew]);
                        } elseif ($hppNew !== null && $hppOld === null) {
                            $edition->update(['harga_beli' => $hppNew]);
                        }
                    }
                } else {
                    // Buku tanpa cetakan: update book.harga sebagai fallback HPP jika ada ongkir
                    // Tidak ubah harga_jual, hanya untuk referensi
                }

                // Stok pre-order tersedia → beri tahu customer yang menunggu.
                if ($book->is_preorder) {
                    app(PreorderReadyService::class)->handleStockArrival($book);
                }
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
        int $shippingCost = 0,
    ): SupplierReturn {
        return DB::transaction(function () use ($supplier, $returnDate, $items, $purchaseId, $notes, $userId, $shippingCost): SupplierReturn {
            // Validasi terhadap faktur: kepemilikan, qty, harga, dan total retur.
            $purchase = $this->validateReturnAgainstInvoice($supplier, $purchaseId, $items);

            // Gudang asal retur normal diambil OTOMATIS dari faktur (tujuan
            // gudang saat pembelian) — fallback gudang default tanpa faktur.
            $normalWarehouse = $purchase?->warehouse_kode !== null
                ? Warehouse::query()->where('kode', $purchase->warehouse_kode)->first()
                : null;

            $normalWarehouse ??= Warehouse::default();

            $totalBarang = 0;
            foreach ($items as $item) {
                $totalBarang += $item['qty'] * $item['price'];
            }
            $total = $totalBarang + $shippingCost;

            $return = $supplier->returns()->create([
                'supplier_purchase_id' => $purchase?->id,
                'return_date' => $returnDate,
                'total' => $total,
                'shipping_cost' => $shippingCost,
                'notes' => $notes,
                'user_id' => $userId,
            ]);

            foreach ($items as $item) {
                $book = Book::findOrFail($item['book_id']);
                $editionId = $item['book_edition_id'] ?? null;
                $edition = null;
                if ($editionId) {
                    $edition = BookEdition::find($editionId);
                    if (! $edition || $edition->book_id !== $book->id) {
                        throw new RuntimeException('Cetakan tidak sesuai dengan buku.');
                    }
                }

                $allocations = $item['allocations'] ?? null;
                $source = $item['source'] ?? 'defect';
                $displayTitle = $book->judul.($edition ? " (Cet. {$edition->cetakan_ke})" : '');

                // ── Validasi stok habis dengan kalimat format dialog ──
                if ($source === 'normal' && is_array($allocations) && $allocations !== []) {
                    foreach ($allocations as $alloc) {
                        $wk = $alloc['warehouse_kode'];
                        $qty = (int) ($alloc['qty'] ?? 0);
                        if ($qty <= 0) {
                            continue;
                        }
                        $warehouse = Warehouse::query()->where('kode', $wk)->first();
                        if (! $warehouse) {
                            throw new RuntimeException("Gudang {$wk} tidak ditemukan.");
                        }
                        $available = $edition
                            ? (int) $edition->stocks()->where('warehouse_id', $warehouse->id)->sum('qty')
                            : (int) $book->inventoryStocks()->where('warehouse_id', $warehouse->id)->sum('qty');
                        if ($available < $qty) {
                            throw new RuntimeException("Stok \"{$displayTitle}\" di Gudang {$warehouse->nama} tidak mencukupi untuk retur. Tersedia {$available} pcs, diminta {$qty} pcs. Barang sudah terjual atau dipindahkan.");
                        }
                    }
                } elseif ($source === 'normal') {
                    $available = $edition
                        ? (int) $edition->stocks()->where('warehouse_id', $normalWarehouse->id)->sum('qty')
                        : (int) $book->inventoryStocks()->where('warehouse_id', $normalWarehouse->id)->sum('qty');
                    if ($available < (int) $item['qty']) {
                        throw new RuntimeException("Stok \"{$displayTitle}\" di Gudang {$normalWarehouse->nama} tidak mencukupi untuk retur. Tersedia {$available} pcs, diminta {$item['qty']} pcs. Barang sudah terjual atau dipindahkan.");
                    }
                } else {
                    $defectWarehouse = Warehouse::defect();
                    $available = $edition
                        ? (int) $edition->stocks()->where('warehouse_id', $defectWarehouse->id)->sum('qty')
                        : (int) $book->inventoryStocks()->where('warehouse_id', $defectWarehouse->id)->sum('qty');
                    if ($available < (int) $item['qty']) {
                        throw new RuntimeException("Stok Defect \"{$displayTitle}\" tidak mencukupi. Tersedia {$available} pcs, diminta {$item['qty']} pcs. Barang defect sudah habis.");
                    }
                }

                // Snapshot HPP & stok untuk histori (best practice: HPP master tetap, retur tidak reverse)
                $hppAtReturn = $edition ? (int) $edition->harga_beli : null;
                $stockBefore = null;
                if ($edition) {
                    if ($source === 'defect') {
                        $defectWarehouse = Warehouse::defect();
                        $stockBefore = (int) $edition->stocks()->where('warehouse_id', $defectWarehouse->id)->sum('qty');
                    } else {
                        $stockBefore = (int) $edition->stocks()->whereHas('warehouse', fn ($q) => $q->sellable())->sum('qty');
                    }
                } else {
                    $stockBefore = $source === 'defect'
                        ? (int) $book->inventoryStocks()->whereHas('warehouse', fn ($q) => $q->where('is_defect', 1))->sum('qty')
                        : (int) $book->inventoryStocks()->whereHas('warehouse', fn ($q) => $q->sellable())->sum('qty');
                }

                $returnItem = $return->items()->create([
                    'book_id' => $book->id,
                    'book_edition_id' => $edition?->id,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'reason' => $item['reason'],
                    'subtotal' => $item['qty'] * $item['price'],
                    'stock_before' => $stockBefore,
                    'hpp_at_return' => $hppAtReturn,
                ]);

                if ($source === 'normal' && is_array($allocations) && $allocations !== []) {
                    foreach ($allocations as $alloc) {
                        $wk = $alloc['warehouse_kode'];
                        $qty = (int) ($alloc['qty'] ?? 0);
                        if ($qty <= 0) {
                            continue;
                        }
                        $warehouse = Warehouse::query()->where('kode', $wk)->first();
                        if (! $warehouse) {
                            throw new RuntimeException("Gudang {$wk} tidak ditemukan.");
                        }
                        $returnItem->allocations()->create([
                            'warehouse_kode' => $warehouse->kode,
                            'qty' => $qty,
                        ]);
                        $this->inventory->move(
                            book: $book,
                            type: MovementType::Out,
                            qty: $qty,
                            from: $warehouse,
                            reference: 'RET-'.$return->id,
                            userId: $userId,
                            notes: "Retur ke {$supplier->nama}: {$item['reason']}",
                            edition: $edition,
                        );
                    }
                } elseif ($source === 'normal') {
                    // Fallback single gudang (backward compat)
                    $this->inventory->move(
                        book: $book,
                        type: MovementType::Out,
                        qty: $item['qty'],
                        from: $normalWarehouse,
                        reference: 'RET-'.$return->id,
                        userId: $userId,
                        notes: "Retur ke {$supplier->nama}: {$item['reason']}",
                        edition: $edition,
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
                        edition: $edition,
                    );
                }
            }

            return $return;
        });
    }

    /**
     * Validasi retur terhadap faktur (bila ditautkan):
     * - Buku harus ada di faktur (strict, jika faktur punya items)
     * - Qty per buku/cetakan ≤ sisa (qty beli - qty sudah diretur)
     * - Harga ≤ harga beli
     * - Total retur ≤ sisa faktur (total − retur tertaut sebelumnya).
     *
     * @param  array<int, array{book_id: string, book_edition_id?: string|null, qty: int, price: int}>  $items
     */
    private function validateReturnAgainstInvoice(Supplier $supplier, ?string $purchaseId, array $items): ?SupplierPurchase
    {
        if ($purchaseId === null) {
            return null;
        }

        $purchase = $supplier->purchases()->with(['items.allocations', 'returns.items.allocations'])->find($purchaseId);

        if ($purchase === null) {
            throw new RuntimeException('Faktur tidak ditemukan untuk supplier ini.');
        }

        // Jika faktur punya items, validasi strict per buku/cetakan
        if ($purchase->items->isNotEmpty()) {
            $purchaseItemsByKey = $purchase->items->keyBy(fn ($i) => $i->book_id.':'.($i->book_edition_id ?? 'null'));
            $returnedPerKey = [];
            $returnedPerWarehouse = [];
            foreach ($purchase->returns as $ret) {
                foreach ($ret->items as $rItem) {
                    $k = $rItem->book_id.':'.($rItem->book_edition_id ?? 'null');
                    $returnedPerKey[$k] = ($returnedPerKey[$k] ?? 0) + $rItem->qty;
                    foreach ($rItem->allocations as $alloc) {
                        $wk = $alloc->warehouse_kode.':'.$k;
                        $returnedPerWarehouse[$wk] = ($returnedPerWarehouse[$wk] ?? 0) + $alloc->qty;
                    }
                }
            }

            foreach ($items as $item) {
                $key = $item['book_id'].':'.($item['book_edition_id'] ?? 'null');
                $purchaseItem = $purchaseItemsByKey->get($key) ?? $purchaseItemsByKey->get($item['book_id'].':null');
                // Fallback: jika cetakan tidak match tapi buku sama, cek buku saja
                if ($purchaseItem === null) {
                    $purchaseItem = $purchase->items->firstWhere('book_id', $item['book_id']);
                }
                if ($purchaseItem === null) {
                    throw new RuntimeException("Buku tidak ada di faktur {$purchase->ref_code}.");
                }
                $alreadyReturned = $returnedPerKey[$key] ?? $returnedPerKey[$item['book_id'].':null'] ?? 0;
                $remaining = $purchaseItem->qty - $alreadyReturned;
                if ($item['qty'] > $remaining) {
                    $bookTitle = $purchaseItem->book?->judul ?? $item['book_id'];
                    throw new RuntimeException("Qty retur untuk '{$bookTitle}' melebihi sisa faktur {$purchase->ref_code} (sisa {$remaining}).");
                }
                if ($item['price'] > $purchaseItem->price) {
                    throw new RuntimeException("Harga retur melebihi harga beli di faktur {$purchase->ref_code}.");
                }
                // Validasi per gudang jika allocations ada
                if (! empty($item['allocations']) && is_array($item['allocations'])) {
                    $allocSum = array_sum(array_map(fn ($a) => (int) ($a['qty'] ?? 0), $item['allocations']));
                    if ($allocSum !== (int) $item['qty']) {
                        throw new RuntimeException("Jumlah alokasi gudang untuk '{$purchaseItem->book?->judul}' harus sama dengan qty retur.");
                    }
                    foreach ($item['allocations'] as $alloc) {
                        $wk = $alloc['warehouse_kode'];
                        $qty = (int) ($alloc['qty'] ?? 0);
                        if ($qty === 0) {
                            continue;
                        }
                        $purchaseAllocQty = $purchaseItem->allocations->firstWhere('warehouse_kode', $wk)?->qty ?? 0;
                        if ($purchaseAllocQty === 0 && $purchaseItem->allocations->isNotEmpty()) {
                            throw new RuntimeException("Gudang {$wk} tidak ada di faktur {$purchase->ref_code}.");
                        }
                        $wkKey = $wk.':'.$key;
                        $alreadyForWarehouse = $returnedPerWarehouse[$wkKey] ?? 0;
                        $remainingForWarehouse = $purchaseAllocQty - $alreadyForWarehouse;
                        if ($qty > $remainingForWarehouse) {
                            throw new RuntimeException("Qty retur gudang {$wk} untuk '{$purchaseItem->book?->judul}' melebihi sisa faktur (sisa {$remainingForWarehouse}).");
                        }
                    }
                }
            }
        }

        $returnTotal = 0;
        foreach ($items as $item) {
            $returnTotal += $item['qty'] * $item['price'];
        }
        $remaining = $purchase->total - (int) $purchase->returns()->sum('total');
        if ($returnTotal > $remaining) {
            throw new RuntimeException("Total retur melebihi sisa faktur {$purchase->ref_code}.");
        }

        return $purchase;
    }

    /**
     * Sisa hutang per faktur: (total barang + ongkir) − retur − pembayaran.
     */
    public function sisaPerFaktur(SupplierPurchase $purchase): int
    {
        $grandTotal = $purchase->total + (int) ($purchase->shipping_cost ?? 0);

        return $grandTotal
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
     * Sisa hutang: (total barang + ongkir) − total retur − total pembayaran.
     */
    public function saldoHutang(Supplier $supplier): int
    {
        $purchaseTotal = $supplier->purchases()->sum('total') + $supplier->purchases()->sum('shipping_cost');

        return $purchaseTotal
            - $supplier->returns()->sum('total')
            - $supplier->payments()->sum('amount');
    }
}
