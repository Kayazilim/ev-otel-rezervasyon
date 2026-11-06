<?php

declare(strict_types=1);

namespace App\Model;

/** Şikâyet durumu (FR-27). */
enum ComplaintStatus: string
{
    use EnumOptions;

    case Yeni = 'yeni';
    case Inceleniyor = 'inceleniyor';
    case Cozuldu = 'cozuldu';
    case Reddedildi = 'reddedildi';

    public function label(): string
    {
        return match ($this) {
            self::Yeni => 'Yeni',
            self::Inceleniyor => 'İnceleniyor',
            self::Cozuldu => 'Çözüldü',
            self::Reddedildi => 'Reddedildi',
        };
    }
}
