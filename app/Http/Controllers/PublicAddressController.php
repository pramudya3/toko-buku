<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint cascading data wilayah untuk storefront (tanpa login).
 */
class PublicAddressController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json(
            Province::query()->orderBy('name')->get(['code', 'name']),
        );
    }

    public function cities(Request $request): JsonResponse
    {
        return response()->json(
            City::query()
                ->where('province_code', $request->string('province_code')->toString())
                ->orderBy('name')
                ->get(['code', 'province_code', 'name']),
        );
    }

    public function districts(Request $request): JsonResponse
    {
        return response()->json(
            District::query()
                ->where('city_code', $request->string('city_code')->toString())
                ->orderBy('name')
                ->get(['code', 'city_code', 'name', 'kode_pos']),
        );
    }
}
