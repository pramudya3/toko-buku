<?php

namespace App\Enums;

enum VoucherScope: string
{
    case Item = 'item';
    case Ongkir = 'ongkir';

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
            self::Item => 'Harga Item',
            self::Ongkir => 'Ongkos Kirim',
        };
    }
}
