<?php

declare(strict_types=1);

namespace Tests\Unit\Seed;

use App\Seed\DemoPhotoPainter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** FR-46 örnek verisi: sahneler GD ile 1200×750 JPEG olarak çizilir; aynı sahne aynı görseli üretir. */
#[Group('FR-46')]
final class DemoPhotoPainterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!DemoPhotoPainter::available()) {
            self::markTestSkipped('GD eklentisi yok.');
        }
    }

    public function testScenesAreDrawnAsDeterministicJpegs(): void
    {
        $painter = new DemoPhotoPainter();

        foreach ([['deniz-villa', true], ['oda-suit', false]] as [$scene, $dusk]) {
            $jpeg = $painter->paint($scene, $dusk);
            $size = getimagesizefromstring($jpeg);
            self::assertNotFalse($size);
            self::assertSame([DemoPhotoPainter::WIDTH, DemoPhotoPainter::HEIGHT, IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]], $scene);
            self::assertSame($jpeg, $painter->paint($scene, $dusk), 'Aynı sahne aynı görseli üretir');
        }
    }

    public function testUnknownSceneIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new DemoPhotoPainter())->paint('uzay-oteli');
    }
}
