<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use Tests\Support\AppTestCase;

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

    public function testHomePageListsSeededProperties(): void
    {
        $response = $this->get('/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Konaklamanızı bulun', $response->body);
        self::assertStringContainsString('Öne çıkan konaklamalar', $response->body);
        self::assertGreaterThanOrEqual(10, count($this->c->units()->all()), 'AC-29: en az 10 birim');
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $response = $this->post('/giris', ['eposta' => 'ali@evotel.test', 'parola' => 'x'], false);

        self::assertSame(419, $response->status);
    }

    public function testGuestIsRedirectedToLoginKeepingTheTarget(): void
    {
        $response = $this->get('/rezervasyon/onizleme', ['birim' => '1', 'giris' => '2026-11-20', 'cikis' => '2026-11-22', 'kisi' => '1']);

        self::assertSame(303, $response->status);
        self::assertStringStartsWith('/giris?donus=', $response->headers['Location']);
        self::assertStringContainsString('2026-11-20', urldecode($response->headers['Location']));
    }

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

    public function testCalendarFragmentIsPartialHtml(): void
    {
        $response = $this->get('/parca/takvim', ['birim' => '1', 'ay' => '2026-11']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('class="takvim"', $response->body);
        self::assertStringNotContainsString('<html', $response->body, 'Parça tam sayfa değildir');
    }

    public function testSearchListsAvailablePropertiesAndValidatesDates(): void
    {
        $ok = $this->get('/ara', ['konum' => 'Trabzon', 'giris' => '2026-11-20', 'cikis' => '2026-11-22', 'kisi' => '2']);
        self::assertStringContainsString('Karadeniz Otel', $ok->body);

        $bad = $this->get('/ara', ['konum' => 'Trabzon', 'giris' => '2026-11-22', 'cikis' => '2026-11-20']);
        self::assertStringContainsString('Çıkış tarihi giriş tarihinden sonra olmalıdır', $bad->body);
    }

    public function testAdminCanExportReportAsCsv(): void
    {
        $this->loginAs('yonetici@evotel.test');
        $response = $this->get('/yonetim/rapor', ['csv' => '1']);

        self::assertSame(200, $response->status);
        self::assertStringStartsWith('text/csv', $response->headers['Content-Type']);
        self::assertStringContainsString('Doluluk', $response->body);
    }

    public function testIdleSessionTimesOut(): void
    {
        $this->loginAs('ali@evotel.test');
        $_SESSION['_last_activity'] = $this->clock->now()->modify('-31 minutes')->getTimestamp();

        $response = $this->get('/rezervasyonlarim');

        self::assertSame(303, $response->status);
        self::assertSame('/giris', $response->headers['Location']);
    }
}
