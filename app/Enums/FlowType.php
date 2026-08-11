<?php

namespace App\Enums;

enum FlowType: string
{
    case Revenue = 'revenue';
    case Shipping = 'shipping';
    case Refund = 'refund';
    case Income = 'income';
    case Expense = 'expense';

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
            self::Income => 'Uang Masuk',
            self::Expense => 'Uang Keluar',
        };
    }

    public function isInflow(): bool
    {
        return $this === self::Revenue || $this === self::Shipping || $this === self::Income;
    }
}
