<?php

namespace App\Services;

use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Logika voucher diskon (level order) — dipakai storefront (checkout) dan
 * PricingService. Sumber kebenaran kuota pemakaian: tabel voucher_usages.
 */
final class VoucherService
{
    /**
     * Voucher yang bisa dipakai user untuk subtotal tertentu: aktif, dalam
     * periode berlaku, minimal belanja terpenuhi, dan kuota (global & per
     * user) masih tersisa.
     *
     * @return Collection<int, Voucher>
     */
    public function availableFor(User $user, int $subtotal): Collection
    {
        $today = now()->toDateString();

        return Voucher::query()
            ->withCount('usages')
            ->withCount([
                'usages as user_usages_count' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('end_date')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Voucher $voucher): bool => $this->passesRestrictions($voucher, $subtotal))
            ->values();
    }

    /**
     * Validasi voucher utk dipakai checkout — lempar exception dengan pesan
     * ramah user bila tidak bisa dipakai.
     */
    public function assertUsable(Voucher $voucher, int $subtotal, User $user): void
    {
        if (! $voucher->isActiveToday()) {
            throw new RuntimeException('Voucher tidak berlaku.');
        }

        if ($subtotal < $voucher->min_order_amount) {
            throw new RuntimeException('Voucher belum bisa dipakai — minimal belanja belum terpenuhi.');
        }

        $usedTotal = $voucher->usages()->count();

        if ($voucher->max_uses !== null && $usedTotal >= $voucher->max_uses) {
            throw new RuntimeException('Kuota voucher sudah habis.');
        }

        $usedByUser = $voucher->usages()->where('user_id', $user->getKey())->count();

        if ($voucher->max_uses_per_user !== null && $usedByUser >= $voucher->max_uses_per_user) {
            throw new RuntimeException('Kamu sudah memakai voucher ini maksimal.');
        }
    }

    /**
     * Besaran diskon voucher dari base amount (subtotal item utk scope item,
     * atau ongkos kirim utk scope ongkir). Diskon tidak pernah melebihi base.
     */
    public function discountAmount(Voucher $voucher, int $baseAmount): int
    {
        if ($baseAmount <= 0) {
            return 0;
        }

        return match ($voucher->voucher_type) {
            VoucherType::Percentage => intdiv($baseAmount * ($voucher->discount_percentage ?? 0), 100),
            VoucherType::Fixed => min($voucher->discount_value ?? 0, $baseAmount),
        };
    }

    /**
     * Catat pemakaian voucher untuk sebuah order (dalam transaksi yang sama
     * dengan pembuatan order).
     */
    public function recordUsage(Order $order, Voucher $voucher): void
    {
        VoucherUsage::create([
            'voucher_id' => $voucher->getKey(),
            'order_id' => $order->getKey(),
            'user_id' => $order->user_id,
        ]);
    }

    /**
     * Cek kuota & minimal belanja (tanpa exception — untuk daftar voucher).
     */
    private function passesRestrictions(Voucher $voucher, int $subtotal): bool
    {
        if ($subtotal < $voucher->min_order_amount) {
            return false;
        }

        if ($voucher->max_uses !== null && $voucher->usages_count >= $voucher->max_uses) {
            return false;
        }

        if ($voucher->max_uses_per_user !== null && $voucher->user_usages_count >= $voucher->max_uses_per_user) {
            return false;
        }

        return true;
    }
}
