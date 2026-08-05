<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerStoreRequest;
use App\Http\Requests\Admin\CustomerUpdateRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * List customer + pencarian (CUST-01).
     */
    public function index(Request $request): Response
    {
        $customers = User::query()
            ->where('is_admin', false)
            ->withCount('orders')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('whatsapp_number', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Form buat pelanggan baru.
     */
    public function create(): Response
    {
        return Inertia::render('admin/customers/Form', [
            'customer' => null,
            'tierOptions' => CustomerTier::options(),
        ]);
    }

    /**
     * Simpan pelanggan baru.
     */
    public function store(CustomerStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_admin'] = false;
        $data['email_verified_at'] = now();

        $user = User::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pelanggan {$user->name} berhasil dibuat."]);

        return to_route('admin.customers.index');
    }

    /**
     * Form edit customer (CUST-02).
     */
    public function edit(User $user): Response
    {
        abort_if($user->is_admin, 403, 'Akun admin tidak dapat diedit dari halaman customer.');

        return Inertia::render('admin/customers/Form', [
            'customer' => $user,
            'tierOptions' => CustomerTier::options(),
        ]);
    }

    /**
     * Update customer — whitelist field, `is_admin` tidak pernah bisa diubah (CUST-04).
     */
    public function update(CustomerUpdateRequest $request, User $user): RedirectResponse
    {
        abort_if($user->is_admin, 403, 'Akun admin tidak dapat diedit dari halaman customer.');

        $data = $request->validated();

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Data pelanggan {$user->name} berhasil diperbarui."]);

        return to_route('admin.customers.index');
    }

    /**
     * Ringkasan transaksi customer (CUST-03).
     */
    public function summary(User $user): \Illuminate\Http\JsonResponse
    {
        abort_if($user->is_admin, 403);

        $totals = Order::where('user_id', $user->id)
            ->where('status', 'selesai')
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent')
            ->first();

        return response()->json([
            'order_count' => (int) $totals->getAttribute('order_count'),
            'total_spent' => (int) $totals->getAttribute('total_spent'),
        ]);
    }
}
