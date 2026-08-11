<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint cascading data wilayah (provinsi → kota/kab → kecamatan).
 */
class AddressController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json(
            Province::query()->orderBy('name')->get(['code', 'name']),
        );
    }

    public function cities(Request $request): JsonResponse
    {
        $provinceCode = $request->string('province_code')->toString();

        return response()->json(
            City::query()
                ->where('province_code', $provinceCode)
                ->orderBy('name')
                ->get(['code', 'province_code', 'name']),
        );
    }

    public function districts(Request $request): JsonResponse
    {
        $cityCode = $request->string('city_code')->toString();

        return response()->json(
            District::query()
                ->where('city_code', $cityCode)
                ->orderBy('name')
                ->get(['code', 'city_code', 'name', 'kode_pos']),
        );
    }

    public function villages(Request $request): JsonResponse
    {
        return response()->json(
            Village::query()
                ->where('district_code', $request->string('district_code')->toString())
                ->orderBy('name')
                ->get(['code', 'district_code', 'name', 'kode_pos']),
        );
    }
}
