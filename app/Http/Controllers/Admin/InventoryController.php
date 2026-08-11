<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryMovementRequest;
use App\Models\Book;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Stok per buku per gudang + filter (INV-01, INV-07).
     */
    public function index(Request $request): Response
    {
        $books = Book::query()
            ->with(['inventoryStocks.warehouse', 'editions.stocks.warehouse'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(function ($query) use ($request): void {
                    $search = $request->string('search')->toString();

                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('kode_sku', "%{$search}%");
                });
            })
            ->when($request->boolean('low_stock'), function ($query): void {
                $query->where('stok', '<=', config('pricing.low_stock_threshold'));
            })
            ->orderBy('judul')
            ->paginate(10)
            ->withQueryString();

        $books->getCollection()->transform(function (Book $book): Book {
            $book->stock_map = $book->inventoryStocks
                ->mapWithKeys(fn ($stock) => [$stock->warehouse->kode => $stock->qty])
                ->all();

            // Rincian stok per cetakan utk dropdown & tampilan.
            $book->edition_stocks = $book->editions
                ->map(fn ($edition) => [
                    'id' => $edition->id,
                    'cetakan_ke' => $edition->cetakan_ke,
                    'nama' => $edition->nama,
                    'is_active' => $edition->is_active,
                    'harga_beli' => $edition->harga_beli,
                    'harga_jual' => $edition->harga_jual,
                    'stocks' => $edition->stocks
                        ->mapWithKeys(fn ($stock) => [$stock->warehouse->kode => $stock->qty])
                        ->all(),
                ])
                ->values()
                ->all();

            $book->unsetRelation('inventoryStocks');
            $book->unsetRelation('editions');

            return $book;
        });

        return Inertia::render('admin/inventory/Index', [
            'books' => $books,
            'warehouses' => Warehouse::query()->orderBy('is_defect')->orderBy('nama')->get(['id', 'kode', 'nama', 'is_defect', 'is_active']),
            'filters' => $request->only(['search', 'low_stock']),
            'movementOptions' => collect(MovementType::options())->except('adjustment')->all(),
            'lowStockThreshold' => config('pricing.low_stock_threshold'),
        ]);
    }

    /**
     * Catat mutasi stok (INV-02, INV-03, INV-08).
     */
    public function store(InventoryMovementRequest $request): RedirectResponse
    {
        $book = Book::findOrFail($request->string('book_id')->toString());
        $edition = $request->filled('book_edition_id')
            ? $book->editions()->findOrFail($request->string('book_edition_id')->toString())
            : null;

        try {
            $movement = $this->inventory->move(
                book: $book,
                type: MovementType::from($request->string('type')->toString()),
                qty: $request->integer('qty'),
                from: $request->filled('from_warehouse') ? Warehouse::findOrFail($request->string('from_warehouse')->toString()) : null,
                to: $request->filled('to_warehouse') ? Warehouse::findOrFail($request->string('to_warehouse')->toString()) : null,
                reference: $request->string('reference')->toString() ?: null,
                userId: $request->user()->id,
                notes: $request->string('notes')->toString() ?: null,
                edition: $edition,
            );

            $label = $book->judul;

            if ($edition !== null) {
                $label .= " (Cetakan ke-{$edition->cetakan_ke})";
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Mutasi {$movement->type->label()} untuk {$label} tercatat.",
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }
}
