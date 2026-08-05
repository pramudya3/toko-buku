<?php

namespace App\Enums;

/**
 * Role pengguna (RBAC ringan — 2 role).
 *
 * Penyimpanan: kolom `is_admin` (boolean) adalah source of truth,
 * enum ini hanya helper agar kode lebih eksplisit.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Customer => 'Customer',
        };
    }
}
