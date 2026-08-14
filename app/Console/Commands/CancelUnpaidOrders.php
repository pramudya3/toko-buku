<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:cancel-unpaid')]
#[Description('Batalkan otomatis order website yang belum dibayar setelah 24 jam (kecuali sudah upload bukti transfer).')]
class CancelUnpaidOrders extends Command
{
    public function __construct(private readonly OrderStatusService $statusService)
    {
        parent::__construct();
    }

    /**
     * Batalkan order yang:
     * - masih menunggu konfirmasi & pembayaran,
     * - berumur > 24 jam,
     * - dari channel website/lainnya (bukan toko — barang langsung diterima),
     * - dan belum mengunggah bukti transfer (upload = menunggu verifikasi, tidak dibatalkan).
     */
    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $candidates = Order::query()
            ->where('status', OrderStatus::MenungguKonfirmasi->value)
            ->where('payment_status', PaymentStatus::Menunggu->value)
            ->where('sumber_pembelian', '!=', SalesChannel::Toko->value)
            ->whereNull('bukti_transfer_path')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $cancelled = 0;

        foreach ($candidates as $order) {
            if (! $this->statusService->canTransition($order, OrderStatus::Batal)) {
                continue;
            }

            $this->statusService->transition($order, OrderStatus::Batal);
            $cancelled++;
        }

        $this->info("Order dibatalkan otomatis: {$cancelled}.");

        return self::SUCCESS;
    }
}
