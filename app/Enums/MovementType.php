<?php

namespace App\Enums;

enum MovementType: string
{
    case Transfer = 'transfer';
    case In = 'in';
    case Out = 'out';
    case Defect = 'defect';
    case Return = 'return';
    case Adjustment = 'adjustment';

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
            self::In => 'Stok Masuk',
            self::Out => 'Stok Keluar',
            self::Defect => 'Defect',
            self::Return => 'Retur Supplier',
            self::Adjustment => 'Stok Adjustment',
        };
    }
}
