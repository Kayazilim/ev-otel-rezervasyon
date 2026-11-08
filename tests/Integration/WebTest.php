<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use Tests\Support\AppTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ön denetleyici üzerinden: CSRF (NFR-14), rol bazlı yetki (FR-05, NFR-03), XSS kaçışı,
 * oturum zaman aşımı (bonus), kısmi yenileme parçası ve CSV dışa aktarma.
 * Veri klasörü boş olduğundan ilk istekte örnek veri yüklenir.
 */
final class WebTest extends AppTestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $this->kernel = new Kernel($this->c);
        $this->get('/'); // örnek veriyi yükle
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    /** @param array<string, mixed> $query */
    private function get(string $path, array $query = []): Response
    {
        return $this->kernel->handle(new Request('GET', $path, $query));
    }

    /** @param array<string, mixed> $post */
    private function post(string $path, array $post, bool $withCsrf = true): Response
    {
        if ($withCsrf) {
            $post['_csrf'] = $_SESSION['_csrf'] ?? '';
        }

        return $this->kernel->handle(new Request('POST', $path, [], $post));
    }

    private function loginAs(string $email): void
    {
        $user = $this->c->users()->findByEmail($email);
        self::assertNotNull($user);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['_last_activity'] = $this->clock->now()->getTimestamp();
        $this->c = new \App\Core\Container($this->settings(), $this->clock);
        $this->kernel = new Kernel($this->c);
    }

    #[Group('AC-29')]
    public function testHomePageListsSeededProperties(): void
    {
        $response = $this->get('/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Konaklamanızı bulun', $response->body);
        self::assertStringContainsString('Öne çıkan konaklamalar', $response->body);
        self::assertGreaterThanOrEqual(10, count($this->c->units()->all()), 'AC-29: en az 10 birim');
    }

    #[Group('NFR-14')]
    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $response = $this->post('/giris', ['eposta' => 'ali@evotel.test', 'parola' => 'x'], false);

        self::assertSame(419, $response->status);
    }

    #[Group('FR-05')]
    #[Group('NFR-03')]
    public function testGuestIsRedirectedToLoginKeepingTheTarget(): void
    {
        $response = $this->get('/rezervasyon/onizleme', ['birim' => '1', 'giris' => '2026-11-20', 'cikis' => '2026-11-22', 'kisi' => '1']);

        self::assertSame(303, $response->status);
        self::assertStringStartsWith('/giris?donus=', $response->headers['Location']);
        self::assertStringContainsString('2026-11-20', urldecode($response->headers['Location']));
    }

    #[Group('FR-03')]
    public function testLogoutEndsTheSession(): void
    {
        $this->loginAs('ali@evotel.test');
        self::assertSame(200, $this->get('/rezervasyonlarim')->status);

        self::assertSame(405, $this->get('/cikis')->status, 'Çıkış yalnızca POST + CSRF ile yapılır');
        $response = $this->post('/cikis', []);
        self::assertSame(303, $response->status);
        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertSame(303, $this->get('/rezervasyonlarim')->status, 'Çıkıştan sonra korumalı sayfa girişe yönlendirir');
    }

    #[Group('FR-09')]
    #[Group('FR-10')]
    #[Group('BR-06')]
    public function testPropertyDetailShowsUnitsPolicyAndBookingMode(): void
    {
        $property = $this->c->properties()->findById(1);
        self::assertNotNull($property);

        $html = $this->get('/ilan/1')->body;

        self::assertStringContainsString(e($property->name), $html);
        self::assertStringContainsString(e($property->city), $html);
        self::assertStringContainsString($property->cancellationPolicy->label(), $html, 'BR-06: iptal koşulu rezervasyondan önce görünür');
        foreach ($this->c->units()->findByProperty(1) as $unit) {
            self::assertStringContainsString(e($unit->name), $html, 'Birimler listelenir');
        }
        self::assertStringContainsString('/api/musaitlik', $html, 'Fiyat ve müsaitlik sayfa yenilenmeden JSON uç noktasından gelir');
    }

    /** NFR-12: form alanlarının her biri bir <label for> veya aria-label ile etiketlidir (ekran okuyucu). */
    #[Group('NFR-12')]
    public function testFormFieldsAreLabelled(): void
    {
        $pages = ['/', '/giris', '/ara?sehir=Trabzon', '/ilan/1', '/parola-sifirla'];
        $this->loginAs('ayse@evotel.test');
        $pages[] = '/panel/ilan/yeni';
        $unlabelled = [];
        foreach ($pages as $page) {
            [$path, $query] = array_pad(explode('?', $page, 2), 2, '');
            parse_str($query, $params);
            $html = $this->get($path, $params)->body;
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $labelled = [];
            foreach ($xpath->query('//label[@for]') ?: [] as $label) {
                if ($label instanceof \DOMElement) {
                    $labelled[$label->getAttribute('for')] = true;
                }
            }
            foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")] | //select | //textarea') ?: [] as $field) {
                if (!$field instanceof \DOMElement) {
                    continue;
                }
                $id = $field->getAttribute('id');
                $wrapped = $field->parentNode instanceof \DOMElement && $field->parentNode->nodeName === 'label';
                if (!($id !== '' && isset($labelled[$id])) && $field->getAttribute('aria-label') === '' && !$wrapped) {
                    $unlabelled[] = $page . ' -> ' . $field->nodeName . '[name=' . $field->getAttribute('name') . ']';
                }
            }
        }

        self::assertSame([], $unlabelled, 'Etiketsiz form alanları');
    }

    #[Group('FR-05')]
    #[Group('NFR-03')]
    #[Group('AC-07')]
    public function testRolesAreEnforcedOnTheServer(): void
    {
        $this->loginAs('ali@evotel.test');
        self::assertSame(403, $this->get('/panel')->status, 'Müşteri ilan sahibi paneline giremez');
        self::assertSame(403, $this->get('/yonetim')->status, 'Müşteri yönetici paneline giremez');

        $this->loginAs('ayse@evotel.test');
        self::assertSame(200, $this->get('/panel')->status);
        self::assertSame(403, $this->get('/yonetim')->status, 'İlan sahibi yönetici paneline giremez');

        $this->loginAs('yonetici@evotel.test');
        self::assertSame(200, $this->get('/yonetim')->status);
    }

    #[Group('NFR-14')]
    public function testUserInputIsEscapedInOutput(): void
    {
        $this->get('/giris');
        $response = $this->post('/kayit', [
            'ad' => '<script>alert(1)</script>',
            'soyad' => 'Test',
            'eposta' => 'xss@example.com',
            'parola' => 'Parola1234',
            'rol' => 'musteri',
        ]);
        self::assertSame(303, $response->status);

        $profile = $this->get('/profil');
        self::assertStringNotContainsString('<script>alert(1)</script>', $profile->body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $profile->body);
    }

    #[Group('FR-10')]
    #[Group('AC-32')]
    public function testCalendarFragmentIsPartialHtml(): void
    {
        $response = $this->get('/parca/takvim', ['birim' => '1', 'ay' => '2026-11']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('class="takvim"', $response->body);
        self::assertStringNotContainsString('<html', $response->body, 'Parça tam sayfa değildir');
    }

    #[Group('FR-06')]
    #[Group('AC-02')]
    #[Group('AC-03')]
    #[Group('UC-01')]
    public function testSearchListsAvailablePropertiesAndValidatesDates(): void
    {
        $ok = $this->get('/ara', ['konum' => 'Trabzon', 'giris' => '2026-11-20', 'cikis' => '2026-11-22', 'kisi' => '2']);
        self::assertStringContainsString('Karadeniz Otel', $ok->body);

        $bad = $this->get('/ara', ['konum' => 'Trabzon', 'giris' => '2026-11-22', 'cikis' => '2026-11-20']);
        self::assertStringContainsString('Çıkış tarihi giriş tarihinden sonra olmalıdır', $bad->body);
    }

    #[Group('FR-30')]
    public function testAdminCanExportReportAsCsv(): void
    {
        $this->loginAs('yonetici@evotel.test');
        $response = $this->get('/yonetim/rapor', ['csv' => '1']);

        self::assertSame(200, $response->status);
        self::assertStringStartsWith('text/csv', $response->headers['Content-Type']);
        self::assertStringContainsString('Doluluk', $response->body);
    }

    #[Group('NFR-15')]
    public function testIdleSessionTimesOut(): void
    {
        $this->loginAs('ali@evotel.test');
        $_SESSION['_last_activity'] = $this->clock->now()->modify('-31 minutes')->getTimestamp();

        $response = $this->get('/rezervasyonlarim');

        self::assertSame(303, $response->status);
        self::assertSame('/giris', $response->headers['Location']);
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        self::assertStringStartsWith('application/json', $response->headers['Content-Type'] ?? '');
        $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /** Etkileşim standardı: JSON döndüren AJAX uç noktası (ilan sayfası sayfa yenilenmeden çağırır). */
    #[Group('FR-10')]
    #[Group('FR-44')]
    #[Group('AC-08')]
    #[Group('BR-07')]
    public function testAvailabilityApiReturnsNightlyBreakdownAsJson(): void
    {
        $unit = $this->c->units()->findById(1);
        self::assertNotNull($unit);
        $query = ['birim' => '1', 'giris' => '2027-05-19', 'cikis' => '2027-05-22', 'kisi' => '1'];

        $data = $this->json($response = $this->get('/api/musaitlik', $query));

        self::assertSame(200, $response->status);
        self::assertTrue($data['basarili']);
        self::assertTrue($data['musait']);
        self::assertSame(3, $data['gece']);
        self::assertCount(3, $data['geceler']);
        $quote = $this->c->pricingService()->quote($unit, $this->range('2027-05-19', '2027-05-22'));
        self::assertSame(\App\Support\Decimal::fromInt($quote->total), $data['toplam']['tutar'], 'API ile rezervasyon aynı fiyat hesabını kullanır');
        self::assertNotSame('', $data['iptal_kosulu']);
    }

    #[Group('NFR-06')]
    #[Group('AC-03')]
    public function testAvailabilityApiReportsErrorsAsJson(): void
    {
        $invalid = $this->get('/api/musaitlik', ['birim' => '1', 'giris' => '2027-05-22', 'cikis' => '2027-05-19', 'kisi' => '1']);
        $data = $this->json($invalid);
        self::assertSame(422, $invalid->status);
        self::assertFalse($data['basarili']);
        self::assertSame('Çıkış tarihi giriş tarihinden sonra olmalıdır.', $data['hatalar']['cikis']);

        $missing = $this->get('/api/musaitlik', ['birim' => '999999', 'giris' => '2027-05-19', 'cikis' => '2027-05-22', 'kisi' => '1']);
        self::assertSame(404, $missing->status);
        self::assertFalse($this->json($missing)['basarili'], 'API hataları da JSON döner, HTML sayfa değil');
    }

    #[Group('FR-36')]
    #[Group('AC-12')]
    public function testAvailabilityApiOffersAlternativesForBookedDates(): void
    {
        $booked = null;
        foreach ($this->c->reservations()->all() as $reservation) {
            if ($reservation->status === \App\Model\ReservationStatus::Onaylandi && $reservation->checkIn >= $this->clock->today()) {
                $booked = $reservation;
                break;
            }
        }
        self::assertNotNull($booked, 'Örnek veride gelecekte onaylı rezervasyon var');

        $data = $this->json($this->get('/api/musaitlik', [
            'birim' => (string) $booked->unitId,
            'giris' => $booked->checkIn->format('Y-m-d'),
            'cikis' => $booked->checkOut->format('Y-m-d'),
            'kisi' => '1',
        ]));

        self::assertTrue($data['basarili']);
        self::assertFalse($data['musait']);
        self::assertNotEmpty($data['alternatifler']);
        self::assertLessThanOrEqual(3, count($data['alternatifler']), 'FR-36: en çok 3 alternatif');
        foreach ($data['alternatifler'] as $alternative) {
            self::assertSame($booked->nights(), $this->range($alternative['giris'], $alternative['cikis'])->nights(), 'Aynı uzunlukta');
        }
    }
}
