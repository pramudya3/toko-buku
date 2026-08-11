<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $code
 * @property string $city_code
 * @property string $name
 */
class District extends Model
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
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_code', 'code');
    }
}
