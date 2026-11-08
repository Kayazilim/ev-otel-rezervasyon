<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Container;
use App\Core\Kernel;
use App\Core\Request;
use App\Model\PropertyType;
use App\Model\ReservationStatus;
use App\Seed\DemoPhotoPainter;
use App\Seed\DemoSeeder;
use App\Support\Settings;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AppTestCase;
use Tests\Support\TestSettings;

/**
 * Örnek veri (AC-29): her ilanda birden çok yorum (FR-24), tutarlı rezervasyonlar (T-11)
 * ve GD ile çizilen ilan/oda fotoğrafları (FR-09, FR-46; depoda resim dosyası yok).
 */
#[Group('AC-29')]
final class DemoSeederTest extends AppTestCase
{
    private bool $photos = false;

    protected function settings(): Settings
    {
        return TestSettings::create($this->dir, '', false, $this->photos);
    }

    #[Group('FR-24')]
    #[Group('T-11')]
    public function testEveryPropertyHasSeveralReviewsFromCompletedStays(): void
    {
        $summary = (new DemoSeeder($this->c))->run();

        self::assertGreaterThanOrEqual(40, $summary['yorum']);
        self::assertSame(0, $summary['fotoğraf'], 'Testlerde fotoğraf çizimi kapalı (hız)');
        foreach ($this->c->properties()->all() as $property) {
            $reviews = $this->c->reviews()->findForProperty((int) $property->id);
            self::assertGreaterThanOrEqual(4, count($reviews), $property->name . ' en az 4 yorum');
            foreach ($reviews as $review) {
                $reservation = $this->c->reservations()->findById($review->reservationId);
                self::assertNotNull($reservation);
                self::assertSame(ReservationStatus::Tamamlandi, $reservation->status, 'FR-24: yorum yalnızca tamamlanmış konaklamadan');
                self::assertSame($review->customerId, $reservation->customerId, 'Yorumu konaklayan müşteri yazar');
            }
        }
        self::assertSame([], $this->c->consistencyAuditService()->violations(), 'T-11: örnek veri tutarlı');
    }

    #[Group('FR-09')]
    #[Group('FR-46')]
    #[Group('NFR-19')]
    public function testDemoPhotosAreDrawnStoredAndServed(): void
    {
        if (!DemoPhotoPainter::available()) {
            self::markTestSkipped('GD eklentisi yok.');
        }
        $this->photos = true;
        $this->c = new Container($this->settings(), $this->clock);
        if ($this->c->settings->usesMySql()) {
            $this->c->resetStorage();
        }

        $summary = (new DemoSeeder($this->c))->run();

        self::assertSame(36, $summary['fotoğraf'], '8 ilan × 3 fotoğraf + 12 otel odası');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $cover = null;
        foreach ($this->c->properties()->all() as $property) {
            $photos = $this->c->photos()->findByProperty((int) $property->id);
            self::assertGreaterThanOrEqual(3, count($photos), $property->name);
            self::assertNull($photos[0]->unitId, 'Kapak fotoğrafı ilanın kendi fotoğrafıdır');
            $cover ??= $photos[0];
            $unitPhotos = array_filter($photos, static fn ($p): bool => $p->unitId !== null);
            if ($property->type === PropertyType::Otel) {
                self::assertCount(count($this->c->units()->findByProperty((int) $property->id)), $unitPhotos, 'Otelin her odasının fotoğrafı var');
            }
            foreach ($photos as $photo) {
                $path = $this->dir . '/uploads/' . $photo->fileName;
                self::assertFileExists($path);
                self::assertSame('image/jpeg', $finfo->file($path));
                self::assertLessThanOrEqual(2 * 1024 * 1024, (int) filesize($path), 'NFR-19: en çok 2 MB');
            }
        }

        $_SESSION = [];
        self::assertNotNull($cover);
        $response = (new Kernel($this->c))->handle(new Request('GET', '/foto/' . $cover->id));
        $_SESSION = [];
        self::assertSame(200, $response->status);
        self::assertSame('image/jpeg', $response->headers['Content-Type'] ?? null);
        self::assertStringStartsWith("\xFF\xD8", $response->body, 'JPEG içeriği');
    }
}
