<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\VoucherCreatedNotification;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Notification;

final class VoucherObserver
{
    public function created(Voucher $voucher): void
    {
        ActivityLogger::log(ActivityAction::VoucherCreate, "Voucher '{$voucher->nama}' dibuat", $voucher);

        // Kabari semua customer aktif — hanya voucher yang langsung berlaku
        // (aktif & sudah masuk periode). Voucher yang baru mulai nanti
        // tidak perlu di-notifikasi sekarang.
        if ($voucher->is_active && $voucher->start_date->lte(now()->startOfDay())) {
            $customers = User::query()
                ->where('is_admin', false)
                ->where('is_active', true)
                ->get();

            Notification::send($customers, new VoucherCreatedNotification($voucher));
        }
    }

    public function updated(Voucher $voucher): void
    {
        ActivityLogger::log(ActivityAction::VoucherUpdate, "Voucher '{$voucher->nama}' diperbarui", $voucher);
    }

    public function deleted(Voucher $voucher): void
    {
        ActivityLogger::log(ActivityAction::VoucherDelete, "Voucher '{$voucher->nama}' dihapus", $voucher);
    }
}
