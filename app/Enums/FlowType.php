<?php

namespace App\Enums;

enum FlowType: string
{
    case Revenue = 'revenue';
    case Shipping = 'shipping';
    case Refund = 'refund';

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
            self::Revenue => 'Revenue',
            self::Shipping => 'Ongkir',
            self::Refund => 'Refund',
        };
    }

    public function isInflow(): bool
    {
        return $this === self::Revenue || $this === self::Shipping;
    }
}
