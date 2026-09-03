<?php

namespace App\Enums;

/**
 * Role pengguna (RBAC ringan — 3 role).
 *
 * Penyimpanan: kolom `is_admin` / `is_kasir` (boolean) adalah source of truth,
 * enum ini hanya helper agar kode lebih eksplisit.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Kasir = 'kasir';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Kasir => 'Kasir',
            self::Customer => 'Customer',
        };
    }
}
