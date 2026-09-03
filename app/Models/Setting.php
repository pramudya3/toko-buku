<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use HasUuids;

    /** Baca setting (cache 1 jam), fallback ke default. */
    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    /** Simpan & flush cache. */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    /**
     * Baca nilai rahasia (terenkripsi di database).
     *
     * Nilai disimpan dengan Crypt::encryptString; saat tidak bisa didekripsi
     * (mis. key berubah) fallback ke default — nilai asli tidak pernah bocor.
     */
    public static function getSecret(string $key, ?string $default = null): ?string
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $default;
        }
    }

    /** Simpan nilai rahasia terenkripsi; null untuk menghapus. */
    public static function setSecret(string $key, ?string $value): void
    {
        static::set($key, $value === null ? null : Crypt::encryptString($value));
    }

    /**
     * Baca nilai array (disimpan sebagai JSON).
     *
     * @param  array<int|string, mixed>|null  $default
     * @return array<int|string, mixed>|null
     */
    public static function getJson(string $key, ?array $default = null): ?array
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Simpan nilai array sebagai JSON.
     *
     * @param  array<int|string, mixed>  $value
     */
    public static function setJson(string $key, array $value): void
    {
        static::set($key, json_encode($value));
    }
}
