<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case Diproses = 'diproses';
    case Dikirim = 'dikirim';
    case Selesai = 'selesai';
    case Batal = 'batal';

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
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi',
            self::Diproses => 'Diproses',
            self::Dikirim => 'Dikirim',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::MenungguKonfirmasi => 'warning',
            self::Diproses, self::Dikirim => 'info',
            self::Selesai => 'success',
            self::Batal => 'danger',
        };
    }
}
