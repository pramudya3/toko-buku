<?php

namespace App\Enums;

enum FulfillmentMethod: string
{
    case Kirim = 'kirim';
    case Ambil = 'ambil';

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
            self::Kirim => 'Kirim',
            self::Ambil => 'Ambil Sendiri',
        };
    }
}
