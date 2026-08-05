<?php

namespace App\Models;

use App\Enums\CustomerTier;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property bool $is_active
 * @property string|null $whatsapp_number
 * @property CustomerTier $status_pelanggan
 * @property string|null $alamat
 * @property string|null $provinsi
 * @property string|null $kabupaten_kota
 * @property string|null $kecamatan
 * @property string|null $kode_pos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'email', 'password', 'is_admin', 'is_active', 'whatsapp_number',
    'status_pelanggan', 'alamat', 'provinsi',
    'kabupaten_kota', 'kecamatan', 'kode_pos',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Role pengguna (admin / customer) — dari kolom is_admin.
     */
    public function role(): UserRole
    {
        return $this->is_admin ? UserRole::Admin : UserRole::Customer;
    }

    public function isCustomer(): bool
    {
        return ! $this->is_admin;
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'status_pelanggan' => CustomerTier::class,
        ];
    }
}
