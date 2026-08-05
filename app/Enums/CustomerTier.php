<?php

namespace App\Enums;

enum CustomerTier: string
{
    case Reguler = 'reguler';
    case Bazaf = 'bazaf';
    case Guru = 'guru';
    case Reseller = 'reseller';

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
            self::Reguler => 'Reguler',
            self::Bazaf => 'Bazaf',
            self::Guru => 'Guru',
            self::Reseller => 'Reseller',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Reguler => 'neutral',
            self::Bazaf => 'info',
            self::Guru => 'success',
            self::Reseller => 'warning',
        };
    }
}
