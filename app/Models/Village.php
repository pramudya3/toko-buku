<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $code
 * @property string $district_code
 * @property string $name
 * @property string|null $kode_pos
 */
class Village extends Model
{
    /**
     * Kolom `id` uuid diisi otomatis (model ini memakai `code` sebagai PK).
     */
    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->id = (string) Str::uuid7());
    }

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'district_code', 'name', 'api_code', 'courier_support', 'api_synced_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'courier_support' => 'boolean',
            'api_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }
}
