<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Menunggu = 'menunggu';
    case Lunas = 'lunas';

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
            self::Menunggu => 'Menunggu Pembayaran',
            self::Lunas => 'Lunas',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Menunggu => 'warning',
            self::Lunas => 'success',
        };
    }
}
