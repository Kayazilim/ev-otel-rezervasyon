<?php

declare(strict_types=1);

namespace App\Service\Dto;

use App\Model\BookableUnit;
use App\Model\BookingMode;
use App\Model\Property;
use App\Model\Reservation;
use App\Model\ReservationCharge;
use App\Model\ReservationStatus;
use App\Model\Review;
use App\Model\User;
use DateTimeImmutable;

/** Rezervasyonun ekranda gösterilen bütün hâli (rezervasyon + birim + ilan + taraflar). */
final class ReservationView
{
    /** @param list<ReservationCharge> $charges */
    public function __construct(
        public readonly Reservation $reservation,
        public readonly BookableUnit $unit,
        public readonly Property $property,
        public readonly ?User $customer,
        public readonly ?User $host,
        public readonly array $charges = [],
        public readonly int $paid = 0,
        public readonly int $refunded = 0,
        public readonly ?Review $review = null,
        public readonly bool $canPay = false,
        public readonly bool $canCancel = false,
        public readonly bool $canReview = false,
        public readonly bool $isHold = false,
    ) {
    }

    /**
     * Rezervasyon durumu bölümünün içeriği. Sayfa ilk açılışta bunu HTML olarak basar, sonra 5 saniyede bir
     * aynı veriyi JSON olarak ister (GET /api/rezervasyon/{no}/durum). Süre kararı sunucudadır: kalan süre
     * burada hesaplanır, tarayıcı yalnızca gelen değeri yazar.
     *
     * @return array{durum: string, etiket: string, rozet: string, kalan_saniye: int|null, kalan_metin: string|null, bitis: string|null, mesaj: string, saat: string}
     */
    public function statusSummary(DateTimeImmutable $now): array
    {
        $res = $this->reservation;
        // Süresi dolmuş ama zamanlanmış görevin henüz işlemediği bekletme, kullanıcıya "Süresi Doldu" olarak gösterilir.
        $status = $this->isHold || $res->status !== ReservationStatus::Beklemede ? $res->status : ReservationStatus::SuresiDoldu;
        $expiresAt = $this->isHold ? $res->expiresAt : null;
        $left = $expiresAt === null ? null : max(0, $expiresAt->getTimestamp() - $now->getTimestamp());
        $message = match (true) {
            $this->isHold && $this->paid === 0 => 'Geceleriniz tutuluyor. Ödemeyi bekletme süresi dolmadan tamamlayın; süre dolarsa geceler serbest kalır.',
            $this->isHold && $this->property->bookingMode === BookingMode::Onayli => 'Ödemeniz alındı (simüle). İlan sahibinin onayı bekleniyor.',
            $res->status === ReservationStatus::Onaylandi => 'Rezervasyonunuz kesinleşti. İyi konaklamalar!',
            $res->status === ReservationStatus::Tamamlandi => 'Konaklama tamamlandı.',
            $status === ReservationStatus::SuresiDoldu => 'Bekletme süresi doldu; geceler serbest bırakıldı.',
            default => $res->cancelReason ?? '',
        };

        return [
            'durum' => $status->value,
            'etiket' => $status->label(),
            'rozet' => $status->badge(),
            'kalan_saniye' => $left,
            'kalan_metin' => $left === null ? null : sprintf('%02d:%02d:%02d', intdiv($left, 3600), intdiv($left % 3600, 60), $left % 60),
            'bitis' => $expiresAt?->format('d.m.Y H:i'),
            'mesaj' => $message,
            'saat' => $now->format('H:i:s'),
        ];
    }
}
