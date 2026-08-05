<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to') : now();

        $orders = Order::query()
            ->with(['user:id,name', 'dropshipper'])
            ->withCount('items')
            ->where('is_dropship', true)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/dropship/Index', [
            'orders' => $orders,
            'filters' => $request->only(['from', 'to', 'status']),
            'statusOptions' => OrderStatus::options(),
        ]);
    }
}
