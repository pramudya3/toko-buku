<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    public const ALLOWED = [10, 20, 50, 100];

    public const DEFAULT = 10;

    /**
     * Resolve per_page dari query dengan whitelist 10/20/50/100.
     *
     * Contoh: $perPage = Pagination::perPage($request);
     *        $perPage = Pagination::perPage($request, 20);
     */
    public static function perPage(Request $request, int $default = self::DEFAULT): int
    {
        $perPage = (int) $request->integer('per_page', $default);

        return in_array($perPage, self::ALLOWED, true) ? $perPage : $default;
    }

    /**
     * Daftar opsi untuk select di frontend.
     *
     * @return int[]
     */
    public static function options(): array
    {
        return self::ALLOWED;
    }
}
