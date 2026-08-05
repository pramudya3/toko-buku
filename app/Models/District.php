<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $code
 * @property string $city_code
 * @property string $name
 */
class District extends Model
{
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
