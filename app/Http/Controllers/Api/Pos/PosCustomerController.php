<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $query = User::where('is_admin', false)->where('is_active', true);

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('whatsapp_number', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->limit($limit)->get(['id', 'name', 'email', 'whatsapp_number', 'status_pelanggan', 'alamat', 'provinsi', 'kabupaten_kota', 'kecamatan', 'kelurahan', 'kode_pos']);

        return response()->json(['data' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status_pelanggan' => ['nullable', 'string', 'in:reguler,bazaf,guru,reseller'],
            'alamat' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? 'pos-'.now()->format('YmdHis').'-'.rand(100, 999).'@pos.local',
            'password' => bcrypt(str()->random(16)),
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            'status_pelanggan' => $validated['status_pelanggan'] ?? 'reguler',
            'alamat' => $validated['alamat'] ?? null,
            'is_admin' => false,
            'is_kasir' => false,
            'is_active' => true,
        ]);

        return response()->json(['data' => $user], 201);
    }
}
