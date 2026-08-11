<?php

namespace App\Enums;

use App\Models\PaymentMethod as PaymentMethodModel;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Cod = 'cod';
    case Cash = 'cash';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer',
            self::Cod => 'COD',
            self::Cash => 'Cash',
        };
    }

    /**
     * Label metode bayar dari kode — enum dulu, lalu tabel (custom).
     *
     * Dipakai untuk order lama yang metodenza sudah nonaktif/dihapus.
     */
    public static function labelFor(string $value): string
    {
        if ($enum = self::tryFrom($value)) {
            return $enum->label();
        }

        return PaymentMethodModel::query()
            ->where('code', $value)
            ->value('name') ?? $value;
    }
}
