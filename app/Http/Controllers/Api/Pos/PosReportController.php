<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosReportController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();

        $orders = Order::where('sumber_pembelian', 'toko')
            ->whereDate('created_at', $date)
            ->withCount('items')
            ->get();

        $totalOmzet = $orders->sum('total');
        $totalTransaksi = $orders->count();
        $byPayment = $orders->groupBy('metode_bayar')->map(fn ($g) => ['count' => $g->count(), 'total' => $g->sum('total')]);

        return response()->json([
            'date' => $date,
            'total_omzet' => $totalOmzet,
            'total_transaksi' => $totalTransaksi,
            'by_payment' => $byPayment,
            'orders' => $orders,
        ]);
    }
}
