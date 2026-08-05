<?php

namespace App\Enums;

enum Warehouse: string
{
    case Malang = 'malang';
    case Sidoarjo = 'sidoarjo';
    case Defect = 'defect';

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
            self::Malang => 'Malang',
            self::Sidoarjo => 'Sidoarjo',
            self::Defect => 'Defect',
        };
    }

    public function isSellable(): bool
    {
        return $this !== self::Defect;
    }
}
