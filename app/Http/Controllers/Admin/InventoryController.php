<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryMovementRequest;
use App\Models\Book;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * Stok per buku per gudang + filter (INV-01, INV-07).
     */
    public function index(Request $request): Response
    {
        $books = Book::query()
            ->with('inventoryStock')
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

        return Inertia::render('admin/inventory/Index', [
            'books' => $books,
            'filters' => $request->only(['search', 'low_stock']),
            'movementOptions' => MovementType::options(),
            'lowStockThreshold' => config('pricing.low_stock_threshold'),
        ]);
    }

    /**
     * Catat mutasi stok (INV-02, INV-03, INV-08).
     */
    public function store(InventoryMovementRequest $request): RedirectResponse
    {
        $book = Book::findOrFail($request->integer('book_id'));

        try {
            $movement = $this->inventory->move(
                book: $book,
                type: MovementType::from($request->string('type')->toString()),
                qty: $request->integer('qty'),
                from: $request->filled('from_warehouse') ? \App\Enums\Warehouse::from($request->string('from_warehouse')->toString()) : null,
                to: $request->filled('to_warehouse') ? \App\Enums\Warehouse::from($request->string('to_warehouse')->toString()) : null,
                reference: $request->string('reference')->toString() ?: null,
                userId: $request->user()->id,
                notes: $request->string('notes')->toString() ?: null,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Mutasi {$movement->type->label()} untuk {$book->judul} tercatat.",
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }
}
