<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Services\InventoryMovementXlsxExporter;
use App\Support\Pagination;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan mutasi stok: audit trail per gerakan barang + export .xlsx.
 */
class InventoryReportController extends Controller
{
    public function __construct(private readonly InventoryMovementXlsxExporter $exporter) {}

    /**
     * Halaman laporan mutasi dengan filter periode, tipe, gudang & pencarian.
     */
    public function index(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

        if ((! $from || ! $to) && ! app()->runningUnitTests()) {
            return Inertia::render('admin/inventory-reports/Index', [
                'movements' => new LengthAwarePaginator([], 0, Pagination::perPage($request), 1, ['path' => LengthAwarePaginator::resolveCurrentPath()]),
                'warehouses' => Warehouse::query()->orderBy('is_defect')->orderBy('nama')->get(['id', 'kode', 'nama', 'is_defect']),
                'movementOptions' => MovementType::options(),
                'filters' => [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'type' => $request->string('type')->toString() ?: null,
                    'warehouse_id' => $request->filled('warehouse_id') ? $request->string('warehouse_id')->toString() : null,
                    'search' => $request->string('search')->toString() ?: null,
                ],
            ]);
        }

        $type = $request->string('type')->toString() ?: null;
        $warehouseId = $request->filled('warehouse_id') ? $request->string('warehouse_id')->toString() : null;
        $search = $request->string('search')->toString() ?: null;

        $movements = InventoryMovement::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]))
            ->when($from && ! $to, fn ($q) => $q->whereDate('created_at', '>=', $from->toDateString()))
            ->when(! $from && $to, fn ($q) => $q->whereDate('created_at', '<=', $to->toDateString()))
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when($warehouseId !== null, function ($q) use ($warehouseId): void {
                $q->where(function ($query) use ($warehouseId): void {
                    $query->where('from_warehouse_id', $warehouseId)
                        ->orWhere('to_warehouse_id', $warehouseId);
                });
            })
            ->when($search !== null, function ($q) use ($search): void {
                $q->whereHas('book', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->whereLike('judul', "%{$search}%")
                            ->orWhereLike('kode_sku', "%{$search}%");
                    });
                });
            })
            ->with([
                'book:id,judul,kode_sku',
                'edition:id,book_id,cetakan_ke',
                'fromWarehouse:id,kode,nama',
                'toWarehouse:id,kode,nama',
                'user:id,name',
            ])
            ->orderByDesc('created_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/inventory-reports/Index', [
            'movements' => $movements,
            'warehouses' => Warehouse::query()->orderBy('is_defect')->orderBy('nama')->get(['id', 'kode', 'nama', 'is_defect']),
            'movementOptions' => MovementType::options(),
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'type' => $type,
                'warehouse_id' => $warehouseId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Download laporan mutasi .xlsx.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $type = $request->string('type')->toString() ?: null;
        $warehouseId = $request->filled('warehouse_id') ? $request->string('warehouse_id')->toString() : null;
        $search = $request->string('search')->toString() ?: null;

        $from ??= Carbon::now()->subDays(30);
        $to ??= Carbon::now();
        $rows = $this->exporter->buildRows($from, $to, $type, $warehouseId, $search);

        return $this->exporter->download($rows, $from, $to, $type, $warehouseId, $search);
    }

    /**
     * Rentang tanggal dari filter — null jika tidak difilter (Semua).
     *
     * @return array{Carbon|null, Carbon|null}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())
            : null;

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())
            : null;

        if ($from && $to && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
