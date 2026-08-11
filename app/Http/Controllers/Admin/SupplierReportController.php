<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\SupplierXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan barang masuk & retur dari/ke supplier (preview + export .xlsx).
 */
class SupplierReportController extends Controller
{
    public function __construct(private readonly SupplierXlsxExporter $exporter) {}

    /**
     * Halaman laporan: filter + tabel preview (kolom sama dengan .xlsx).
     */
    public function index(Request $request): Response
    {
        [$from, $to, $supplierId, $jenis] = $this->filters($request);

        $rows = $this->exporter->buildRows($from, $to, $supplierId, $jenis);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );

        return Inertia::render('admin/supplier-reports/Index', [
            'rows' => $paginated->withQueryString(),
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'supplier_id' => $supplierId,
                'jenis' => $jenis,
            ],
        ]);
    }

    /**
     * Export laporan barang masuk / retur supplier ke .xlsx.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to, $supplierId, $jenis] = $this->filters($request);

        $rows = $this->exporter->buildRows($from, $to, $supplierId, $jenis);

        return $this->exporter->download($rows, $from, $to, $supplierId, $jenis);
    }

    /**
     * @return array{Carbon, Carbon, int|null, string}
     */
    private function filters(Request $request): array
    {
        $from = $request->filled('from')
            ? Date::parse($request->string('from')->toString())->startOfDay()
            : Date::today()->subDays(30)->startOfDay();

        $to = $request->filled('to')
            ? Date::parse($request->string('to')->toString())->endOfDay()
            : Date::today()->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $supplierId = $request->filled('supplier_id') ? $request->string('supplier_id')->toString() : null;
        $jenis = in_array($request->string('jenis')->toString(), ['pembelian', 'retur'], true)
            ? $request->string('jenis')->toString()
            : 'semua';

        return [$from, $to, $supplierId, $jenis];
    }
}
