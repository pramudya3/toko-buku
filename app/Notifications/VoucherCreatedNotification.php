<?php

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi voucher baru untuk customer (database channel).
 */
class VoucherCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Voucher $voucher) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Voucher baru: {$this->voucher->nama} — diskon {$this->voucher->discount_label}. Berlaku sampai {$this->voucher->end_date->format('d/m/Y')}.",
            'voucher_id' => $this->voucher->getKey(),
            'voucher_name' => $this->voucher->nama,
        ];
    }
}
