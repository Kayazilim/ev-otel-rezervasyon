<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Exception\ConflictException;
use App\Exception\ValidationException;
use App\Model\BookableUnit;
use App\Model\BookingMode;
use App\Model\CalendarEntry;
use App\Model\CalendarStatus;
use App\Model\CancellationPolicy;
use App\Model\Property;
use App\Model\PropertyStatus;
use App\Model\PropertyType;
use App\Model\Reservation;
use App\Model\ReservationStatus;
use App\Model\RoomType;
use App\Model\UnitStatus;
use App\Service\BookingConflictService;
use App\Support\DateRange;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemory\InMemoryServices;

/**
 * Çekirdek kural (SRS 4.4): doğrulama, engelleyen durumlar, kapalı geceler, alternatifler, takvim.
 *
 * Birim testi: BookingConflictService bellekte çalışan sahte depolarla (rezervasyon, takvim, ödeme,
 * bildirim, denetim kaydı) kurulur; hiçbir JSON dosyası okunmaz veya yazılmaz, saat sabittir.
 * Rezervasyonlar akıştan geçmeden doğrudan depoya konur; tam akış ReservationFlowTest'tedir.
 */
final class BookingConflictServiceTest extends TestCase
{
    private const HOST = 10;
    private const GUEST = 20;

    private InMemoryServices $mem;
    private Property $property;
    private BookableUnit $unit;
    private int $sequence = 0;

    protected function setUp(): void
    {
        $this->mem = new InMemoryServices('2026-11-01 10:00:00');
        $this->property = new Property(
            1,
            self::HOST,
            'Test Otel',
            PropertyType::Otel,
            'Test ilanı açıklaması yeterince uzun.',
            'Merkez',
            'Trabzon',
            BookingMode::Aninda,
            CancellationPolicy::Orta,
            PropertyStatus::Aktif,
        );
        $this->unit = new BookableUnit(1, 1, '101', RoomType::CiftKisilik, 2, 100000, UnitStatus::Aktif);
    }

    private function conflicts(): BookingConflictService
    {
        return $this->mem->conflicts();
    }

    private function range(string $in, string $out): DateRange
    {
        return DateRange::fromStrings($in, $out);
    }

    private function d(string $ymd): DateTimeImmutable
    {
        return DateRange::parseDate($ymd);
    }

    /** Depoya doğrudan rezervasyon koyar. Engelleyen durumlarda (Beklemede, Onaylandı) geceler de yazılır. */
    private function reservation(ReservationStatus $status, string $in, string $out, int $unitId = 1, ?DateTimeImmutable $expiresAt = null): Reservation
    {
        $range = $this->range($in, $out);
        $now = $this->mem->clock->now();
        $this->sequence++;
        $reservation = new Reservation(
            null,
            'RZ-TEST-' . $this->sequence,
            self::GUEST,
            $unitId,
            $range->checkIn,
            $range->checkOut,
            1,
            $status,
            $expiresAt,
            200000,
            CancellationPolicy::Orta,
            'anahtar-' . $this->sequence,
            'Test Misafir',
            '05550000000',
            null,
            null,
            null,
            0,
            $now,
            $now,
        );
        $blocking = in_array($status, [ReservationStatus::Beklemede, ReservationStatus::Onaylandi], true);

        return $this->mem->reservations->createWithNights($reservation, [], $blocking ? $range->nightDates() : []);
    }

    /** Anında modda 10 dakikalık bekletme (BR-15). */
    private function hold(string $in, string $out): Reservation
    {
        return $this->reservation(ReservationStatus::Beklemede, $in, $out, 1, $this->mem->clock->now()->modify('+10 minutes'));
    }

    #[Group('BR-01')]
    #[Group('BR-02')]
    #[Group('BR-14')]
    #[Group('AC-03')]
    #[Group('FR-11')]
    public function testDateRulesBr01Br02Br14(): void
    {
        $conflicts = $this->conflicts();

        self::assertArrayHasKey('cikis', $conflicts->dateErrors($this->range('2026-11-10', '2026-11-10')), 'aynı gün giriş-çıkış geçersiz');
        self::assertArrayHasKey('cikis', $conflicts->dateErrors($this->range('2026-11-12', '2026-11-10')), 'çıkış girişten önce');
        self::assertArrayHasKey('giris', $conflicts->dateErrors($this->range('2026-10-31', '2026-11-02')), 'dün için giriş reddedilir');
        self::assertSame([], $conflicts->dateErrors($this->range('2026-11-01', '2026-11-02')), 'bugün için giriş kabul edilir (NFR-17)');
        self::assertArrayHasKey('cikis', $conflicts->dateErrors($this->range('2026-11-01', '2026-12-02')), 'en çok 30 gece');
        self::assertArrayHasKey('giris', $conflicts->dateErrors($this->range('2027-11-02', '2027-11-03')), 'en çok 365 gün ileri');
    }

    #[Group('BR-03')]
    #[Group('BR-19')]
    #[Group('AC-03')]
    #[Group('FR-11')]
    public function testCapacityInactivePropertyAndOwnListing(): void
    {
        $conflicts = $this->conflicts();
        try {
            $conflicts->validate($this->property, $this->unit, $this->range('2026-11-10', '2026-11-12'), 3, self::GUEST);
            self::fail('Kapasite aşımı reddedilmeliydi');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('kisi', $e->errors());
        }

        try {
            $conflicts->validate($this->property, $this->unit, $this->range('2026-11-10', '2026-11-12'), 1, self::HOST);
            self::fail('İlan sahibi kendi ilanına rezervasyon yapamamalı (BR-19)');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('ilan', $e->errors());
        }

        $this->property->status = PropertyStatus::Pasif;
        $this->expectException(ValidationException::class);
        $conflicts->validate($this->property, $this->unit, $this->range('2026-11-10', '2026-11-12'), 1, self::GUEST);
    }

    #[Group('BR-12')]
    #[Group('AC-15')]
    #[Group('CS-05')]
    public function testExpiredHoldDoesNotBlockEvenBeforeCleanup(): void
    {
        $hold = $this->hold('2026-11-10', '2026-11-12');
        $conflicts = $this->conflicts();
        $range = $this->range('2026-11-10', '2026-11-12');

        self::assertFalse($conflicts->isAvailable((int) $this->unit->id, $range), 'Beklemede kayıt geceleri tutar');

        // NFR-17: expires_at'ten bir saniye önce geçerli, bir saniye sonra süresi dolmuş
        $this->mem->clock->set($hold->expiresAt?->modify('-1 second') ?? $this->mem->clock->now());
        self::assertTrue($conflicts->isBlocking($hold));
        $this->mem->clock->set($hold->expiresAt?->modify('+1 second') ?? $this->mem->clock->now());
        self::assertFalse($conflicts->isBlocking($hold));
        self::assertTrue($conflicts->isAvailable((int) $this->unit->id, $range), 'Zamanlanmış görev çalışmasa da engellemez (FR-34)');
    }

    #[Group('BR-04')]
    #[Group('BR-22')]
    #[Group('FR-22')]
    public function testClosedNightsBlockBookingButNotOtherUnits(): void
    {
        $this->mem->calendar->upsert(new CalendarEntry(null, (int) $this->unit->id, $this->d('2026-11-11'), CalendarStatus::Kapali, null));
        $conflicts = $this->conflicts();

        self::assertFalse($conflicts->isAvailable((int) $this->unit->id, $this->range('2026-11-10', '2026-11-12')));
        self::assertTrue($conflicts->isAvailable(2, $this->range('2026-11-10', '2026-11-12')), 'Çakışma birim bazındadır (BR-04)');
        self::assertTrue($conflicts->isAvailable((int) $this->unit->id, $this->range('2026-11-12', '2026-11-14')));
    }

    #[Group('BR-11')]
    #[Group('BR-13')]
    #[Group('AC-14')]
    public function testCheckOutDayIsFreeForTheNextGuest(): void
    {
        $this->reservation(ReservationStatus::Onaylandi, '2026-11-10', '2026-11-12');
        $conflicts = $this->conflicts();

        self::assertTrue($conflicts->isAvailable((int) $this->unit->id, $this->range('2026-11-12', '2026-11-14')), 'Çıkış günü (12) yeni misafirin giriş günü olabilir');
        self::assertTrue($conflicts->isAvailable((int) $this->unit->id, $this->range('2026-11-08', '2026-11-10')), 'Önceki misafir giriş günü (10) çıkabilir');
        self::assertFalse($conflicts->isAvailable((int) $this->unit->id, $this->range('2026-11-11', '2026-11-13')), 'Bir gece kesişirse çakışır');
    }

    #[Group('FR-36')]
    #[Group('AC-12')]
    public function testAlternativesHaveSameLengthAndAreFree(): void
    {
        $this->reservation(ReservationStatus::Onaylandi, '2026-11-10', '2026-11-12');
        $conflicts = $this->conflicts();
        $alternatives = $conflicts->suggestAlternatives((int) $this->unit->id, $this->range('2026-11-10', '2026-11-12'));

        self::assertCount(3, $alternatives);
        foreach ($alternatives as $alternative) {
            self::assertSame(2, $alternative->nights());
            self::assertTrue($conflicts->isAvailable((int) $this->unit->id, $alternative));
        }
    }

    /** Kesin karar (FR-11): süresi dolmuş bekletme temizlenir; gerçek çakışmada alternatiflerle reddedilir. */
    #[Group('FR-11')]
    #[Group('FR-34')]
    #[Group('FR-36')]
    #[Group('CS-05')]
    public function testAssertAvailableExpiresStaleHoldsAndRejectsRealConflicts(): void
    {
        $stale = $this->hold('2026-11-10', '2026-11-12');
        $this->mem->clock->advance('+11 minutes');

        $this->conflicts()->assertAvailable((int) $this->unit->id, $this->range('2026-11-10', '2026-11-12'));

        self::assertSame(ReservationStatus::SuresiDoldu, $this->mem->reservations->findById((int) $stale->id)?->status, 'Bekletme süresi doldu olarak işaretlendi');
        self::assertSame([], $this->mem->reservations->nightsOf((int) $stale->id), 'Geceleri serbest bırakıldı');
        self::assertCount(1, $this->mem->notifications->findByUser(self::GUEST), 'Müşteriye bildirim gitti');

        $this->reservation(ReservationStatus::Onaylandi, '2026-11-10', '2026-11-12');
        try {
            $this->conflicts()->assertAvailable((int) $this->unit->id, $this->range('2026-11-11', '2026-11-13'));
            self::fail('Çakışma reddedilmeliydi');
        } catch (ConflictException $e) {
            self::assertSame('Seçtiğiniz tarihler artık müsait değil.', $e->getMessage());
            self::assertNotEmpty($e->alternatives());
            self::assertLessThanOrEqual(3, count($e->alternatives()));
            self::assertSame(['2026-11-11'], array_map(fn (DateTimeImmutable $n): string => $n->format('Y-m-d'), $e->nights()), 'Hangi gecenin dolu olduğu söylenir');
        }
    }

    /**
     * Hata kaydı H-01 (docs/hata-kayitlari.md): müsait olmayan aralıkta hangi gecelerin dolu olduğu söylenir
     * (başka rezervasyonun geceleri ve ilan sahibinin kapattığı geceler). Çıkış günü listede yer almaz.
     */
    #[Group('BR-11')]
    #[Group('BR-13')]
    #[Group('BR-22')]
    public function testUnavailableNightsNameOnlyTheTakenNights(): void
    {
        $this->reservation(ReservationStatus::Onaylandi, '2026-11-10', '2026-11-12');
        $this->mem->calendar->upsert(new CalendarEntry(null, (int) $this->unit->id, $this->d('2026-11-13'), CalendarStatus::Kapali, null));
        $conflicts = $this->conflicts();
        $days = fn (array $nights): array => array_map(fn (DateTimeImmutable $n): string => $n->format('Y-m-d'), $nights);

        self::assertSame(['2026-11-11', '2026-11-13'], $days($conflicts->unavailableNights((int) $this->unit->id, $this->range('2026-11-11', '2026-11-14'))));
        self::assertSame([], $days($conflicts->unavailableNights((int) $this->unit->id, $this->range('2026-11-12', '2026-11-13'))), 'Çıkış günü (12) dolu sayılmaz');
        self::assertSame(['2026-11-10', '2026-11-11'], $days($conflicts->unavailableNights((int) $this->unit->id, $this->range('2026-11-09', '2026-11-12'))));
    }

    #[Group('FR-10')]
    #[Group('AC-32')]
    public function testNightStatusesForCalendar(): void
    {
        $this->reservation(ReservationStatus::Onaylandi, '2026-11-10', '2026-11-12');
        $this->mem->calendar->upsert(new CalendarEntry(null, (int) $this->unit->id, $this->d('2026-11-20'), CalendarStatus::Kapali, null));

        $statuses = $this->conflicts()->nightStatuses((int) $this->unit->id, $this->d('2026-11-01'), $this->d('2026-12-01'));

        self::assertSame('dolu', $statuses['2026-11-10']);
        self::assertSame('dolu', $statuses['2026-11-11']);
        self::assertSame('musait', $statuses['2026-11-12'], 'Çıkış günü yeni girişe açık');
        self::assertSame('kapali', $statuses['2026-11-20']);
        self::assertCount(30, $statuses);
    }

    /** BR-12: İptal, ret, süre dolumu ve tamamlanma geceleri engellemez. Tam iptal akışı ReservationFlowTest'tedir. */
    #[Group('BR-12')]
    #[Group('AC-16')]
    public function testCancelledReservationDoesNotBlock(): void
    {
        foreach ([ReservationStatus::IptalEdildi, ReservationStatus::Reddedildi, ReservationStatus::SuresiDoldu, ReservationStatus::Tamamlandi] as $status) {
            $this->reservation($status, '2026-11-20', '2026-11-22');
        }

        self::assertTrue($this->conflicts()->isAvailable((int) $this->unit->id, $this->range('2026-11-20', '2026-11-22')));
    }
}
