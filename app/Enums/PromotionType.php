<?php

namespace App\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case Bundle = 'bundle';

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
            self::Percentage => 'Persentase',
            self::Fixed => 'Harga Tetap',
            self::Bundle => 'Bundle',
        };
    }
}
