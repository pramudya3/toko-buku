<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast tanggal yang hanya menyimpan & menampilkan Y-m-d.
 *
 * Akses atribut mengembalikan string tanggal polos (bukan Carbon) sehingga
 * serialisasi JSON tidak terganggu konversi zona waktu UTC (mis. preorder_eta
 * untuk <input type="date"> yang menolak nilai ISO 8601).
 *
 * @implements CastsAttributes<string, string>
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value !== null ? Carbon::parse($value)->toDateString() : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value !== null ? Carbon::parse($value)->toDateString() : null;
    }
}
