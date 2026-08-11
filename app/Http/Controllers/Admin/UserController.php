<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List user admin (yang bisa login ke panel) + pencarian.
     */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->where('is_admin', true)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Form buat user admin baru.
     */
    public function create(): Response
    {
        return Inertia::render('admin/users/Form', [
            'user' => null,
        ]);
    }

    /**
     * Simpan user admin baru.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_admin'] = true;
        $data['email_verified_at'] = now();

        $user = User::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->name} berhasil dibuat."]);

        return to_route('admin.users.index');
    }

    /**
     * Form edit user admin.
     */
    public function edit(User $user): Response
    {
        abort_if(! $user->is_admin, 403, 'Akun customer tidak dapat diedit dari halaman user.');

        return Inertia::render('admin/users/Form', [
            'user' => $user,
        ]);
    }

    /**
     * Update user admin — whitelist field, `is_admin` tidak pernah bisa diubah.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        abort_if(! $user->is_admin, 403, 'Akun customer tidak dapat diedit dari halaman user.');

        $data = $request->validated();

        // Password opsional saat edit — kosong berarti tidak diubah.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->name} berhasil diperbarui."]);

        return to_route('admin.users.index');
    }

    /**
     * Nonaktifkan/aktifkan user admin — dengan proteksi yang sama seperti
     * form edit: tidak bisa menonaktifkan diri sendiri & admin aktif terakhir.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        abort_if(! $user->is_admin, 403, 'Akun customer tidak dapat diubah dari halaman user.');

        if ($user->is_active && request()->user()?->is($user)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ]);

            return back();
        }

        if ($user->is_active) {
            $activeAdmins = User::query()
                ->where('is_admin', true)
                ->where('is_active', true)
                ->count();

            if ($activeAdmins <= 1) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => 'Tidak dapat menonaktifkan admin aktif terakhir.',
                ]);

                return back();
            }
        }

        $user->update(['is_active' => ! $user->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active
                ? "User {$user->name} berhasil diaktifkan."
                : "User {$user->name} berhasil dinonaktifkan.",
        ]);

        return back();
    }
}
