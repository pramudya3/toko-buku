<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $code
 * @property string $name
 */
class Province extends Model
{
    /**
     * Kolom `id` uuid diisi otomatis (model ini memakai `code` sebagai PK).
     */
    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->id = (string) Str::uuid7());
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'code';

    public $timestamps = false;

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'province_code', 'code');
    }
}
