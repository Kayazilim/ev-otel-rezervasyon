<?php

declare(strict_types=1);

namespace App\Model;

/** Simüle ödeme durumu (FR-37). Kart verisi saklanmaz. */
enum PaymentStatus: string
{
    use EnumOptions;

    case Beklemede = 'beklemede';
    case Basarili = 'basarili';
    case Basarisiz = 'basarisiz';
    case KismenIade = 'kismen_iade';
    case IadeEdildi = 'iade_edildi';

    public function label(): string
    {
        return match ($this) {
            self::Beklemede => 'Beklemede',
            self::Basarili => 'Başarılı',
            self::Basarisiz => 'Başarısız',
            self::KismenIade => 'Kısmen iade edildi (simüle)',
            self::IadeEdildi => 'İade edildi (simüle)',
        };
    }
}
