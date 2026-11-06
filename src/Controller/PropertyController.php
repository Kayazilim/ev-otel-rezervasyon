<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\PropertyType;
use App\Model\RoomType;
use App\Service\AvailabilityService;
use App\Service\Dto\SearchCriteria;
use App\Service\PhotoService;
use App\Service\PropertyService;
use App\Service\ReservationService;
use App\Support\ClockInterface;
use App\Support\DateRange;
use App\Support\Decimal;
use InvalidArgumentException;

/** Arama, ilan detayı, aylık takvim ve fiyat önizlemesi (UC-01; taslak 02 ve 03). */
final class PropertyController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly PropertyService $properties,
        private readonly AvailabilityService $availability,
        private readonly ReservationService $reservations,
        private readonly PhotoService $photos,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function search(Request $request): Response
    {
        $criteria = $this->criteria($request);

        return $this->render('property/search', [
            'title' => 'Arama sonuçları',
            'result' => $this->properties->search($criteria),
            'amenities' => $this->properties->amenities(),
            'types' => PropertyType::options(),
            'roomTypes' => RoomType::options(),
            'q' => $request->query,
        ]);
    }

    /** Bonus: yazarken sonuç veren canlı arama. PHP sonuç listesinin HTML'ini üretir, JS yerine koyar. */
    public function searchFragment(Request $request): Response
    {
        $criteria = $this->criteria($request);
        $this->session->close(); // oturum okundu; aynı oturumdaki diğer istekleri bekletme

        return $this->fragment('property/_results', ['result' => $this->properties->search($criteria), 'q' => $request->query]);
    }

    public function show(Request $request, string $id): Response
    {
        $detail = $this->properties->detail((int) $id, $this->auth->user());
        $unitId = $request->int('birim', (int) ($detail->units[0]->id ?? 0));
        $month = $this->month($request->query('ay', $request->query('giris')));

        return $this->render('property/show', [
            'title' => $detail->property->name,
            'detail' => $detail,
            'unitId' => $unitId,
            'month' => $month,
            'form' => [
                'giris' => $request->query('giris'),
                'cikis' => $request->query('cikis'),
                'kisi' => $request->query('kisi', '1'),
            ],
        ]);
    }

    /**
     * Kısmi yenileme (ders kalıbı): aylık takvim bölümü 5 saniyede bir bu adresten yeniden alınır.
     * Başka bir müşteri rezervasyon yaptığında dolu geceler sayfa yenilenmeden görünür.
     */
    public function calendarFragment(Request $request): Response
    {
        $viewer = $this->auth->user();
        $this->session->close();
        $unitId = $request->int('birim');
        [$unit] = $this->properties->unitWithProperty($unitId, $viewer); // yetki: yayında olmayan ilanın takvimi gizli
        [$year, $month] = $this->month($request->query('ay'));

        return $this->fragment('property/_calendar', [
            'calendar' => $this->availability->monthCalendar((int) $unit->id, $year, $month),
            'showPrices' => $request->query('fiyat') === '1',
        ]);
    }

    /** Bonus: formu sayfa yenilemeden göndermek. Fiyat dökümü PHP'de üretilir, JS yalnızca yerine koyar. */
    public function priceFragment(Request $request): Response
    {
        $viewer = $this->auth->user();
        $this->session->close();
        try {
            $range = DateRange::fromStrings($request->query('giris'), $request->query('cikis'), $this->clock->today()->getTimezone());
            $preview = $this->reservations->preview($viewer?->id, $request->int('birim'), $range, $request->int('kisi', 1));

            return $this->fragment('property/_price', ['preview' => $preview, 'errors' => [], 'viewer' => $viewer]);
        } catch (InvalidArgumentException) {
            return $this->fragment('property/_price', ['preview' => null, 'errors' => ['giris' => 'Giriş ve çıkış tarihlerini seçin.'], 'viewer' => $viewer]);
        } catch (ValidationException $e) {
            return $this->fragment('property/_price', ['preview' => null, 'errors' => $e->errors(), 'viewer' => $viewer]);
        } catch (NotFoundException $e) {
            return $this->fragment('property/_price', ['preview' => null, 'errors' => ['birim' => $e->getMessage()], 'viewer' => $viewer]);
        }
    }

    /** Yüklenen resimler web kökünün dışından bu adresle sunulur (NFR-19). */
    public function photo(Request $request, string $id): Response
    {
        $file = $this->photos->file((int) $id);

        return Response::file($file['path'], $file['mime']);
    }

    private function criteria(Request $request): SearchCriteria
    {
        $price = static function (string $value): ?int {
            $value = trim($value);

            return $value !== '' && Decimal::isValid($value) ? Decimal::toInt($value) : null;
        };
        $rating = trim($request->query('puan'));

        return new SearchCriteria(
            city: trim($request->query('konum')),
            checkIn: trim($request->query('giris')),
            checkOut: trim($request->query('cikis')),
            guests: $request->int('kisi', 1),
            type: PropertyType::tryFrom($request->query('tur')),
            minPrice: $price($request->query('fiyat_min')),
            maxPrice: $price($request->query('fiyat_max')),
            minRating: is_numeric($rating) ? (float) $rating : null,
            roomTypes: array_values(array_filter(array_map(RoomType::tryFrom(...), $request->list('oda_tipi')))),
            amenityIds: array_values(array_map('intval', array_filter($request->list('olanak'), 'ctype_digit'))),
            sort: in_array($request->query('sirala'), ['fiyat_artan', 'fiyat_azalan', 'puan'], true) ? $request->query('sirala') : 'fiyat_artan',
            page: max(1, $request->int('sayfa', 1)),
            viewerId: $this->auth->user()?->id,
        );
    }

    /**
     * "2026-11" veya "2026-11-10" -> [2026, 11]; geçersizse bu ay.
     *
     * @return array{0: int, 1: int}
     */
    private function month(string $value): array
    {
        if (preg_match('/^(\d{4})-(\d{2})/', $value, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return [(int) $m[1], (int) $m[2]];
        }
        $today = $this->clock->today();

        return [(int) $today->format('Y'), (int) $today->format('n')];
    }
}
