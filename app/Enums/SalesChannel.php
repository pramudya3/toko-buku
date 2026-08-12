<?php

namespace App\Enums;

use App\Models\SalesChannel as SalesChannelModel;

enum SalesChannel: string
{
    case Toko = 'toko';
    case Shopee = 'shopee';
    case Tokopedia = 'tokopedia';
    case TiktokShop = 'tiktok_shop';
    case Instagram = 'instagram';
    case Website = 'website';
    case Lainnya = 'lainnya';

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
            self::Toko => 'Toko',
            self::Shopee => 'Shopee',
            self::Tokopedia => 'Tokopedia',
            self::TiktokShop => 'TikTok Shop',
            self::Instagram => 'Instagram',
            self::Website => 'Website',
            self::Lainnya => 'Lainnya',
        };
    }

    /**
     * Label sumber pembelian dari kode — enum dulu, lalu tabel (custom).
     *
     * Dipakai untuk order lama yang channel-nya sudah nonaktif/dihapus.
     */
    public static function labelFor(string $value): string
    {
        if ($enum = self::tryFrom($value)) {
            return $enum->label();
        }

        return SalesChannelModel::query()
            ->where('code', $value)
            ->value('name') ?? $value;
    }
}
