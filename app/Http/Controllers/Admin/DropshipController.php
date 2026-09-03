<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DropshipController extends Controller
{
    /**
     * Laporan order dropship (DROP-02, DROP-03).
     */
    public function index(Request $request): Response
    {
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        $orders = Order::query()
            ->with(['user:id,name', 'dropshipper'])
            ->withCount('items')
            ->where('is_dropship', true)
            ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]))
            ->when($from && ! $to, fn ($q) => $q->whereDate('created_at', '>=', $from->toDateString()))
            ->when(! $from && $to, fn ($q) => $q->whereDate('created_at', '<=', $to->toDateString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('created_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/dropship/Index', [
            'orders' => $orders,
            'filters' => $request->only(['from', 'to', 'status']),
            'statusOptions' => OrderStatus::options(),
        ]);
    }
}
