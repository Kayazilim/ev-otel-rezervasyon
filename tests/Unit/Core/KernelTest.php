<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Kernel;
use ErrorException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Hata kaydı H-05: istek işlenirken oluşan PHP uyarısı sayfaya dosya yolu ve satır numarasıyla basılmaz;
 * istisnaya çevrilir, Kernel onu günlüğe yazar ve kullanıcıya sade bir hata sayfası gösterir.
 */
#[Group('NFR-10')]
final class KernelTest extends TestCase
{
    public function testWarningsBecomeExceptions(): void
    {
        try {
            Kernel::warningToException(E_WARNING, 'Array to string conversion', 'View.php', 152);
            self::fail('Uyarı istisnaya çevrilmeliydi');
        } catch (ErrorException $e) {
            self::assertSame(E_WARNING, $e->getSeverity());
            self::assertSame('Array to string conversion', $e->getMessage());
        }
    }

    public function testSilencedWarningsAndDeprecationsAreLeftAlone(): void
    {
        self::assertFalse(Kernel::warningToException(E_DEPRECATED, 'Eski kullanım', 'x.php', 1));
        // "@" ile susturulmuş ifadede PHP 8 error_reporting() değerini bastırılamayan düzeylere indirir.
        $old = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
        try {
            self::assertFalse(Kernel::warningToException(E_WARNING, 'Susturuldu', 'x.php', 1));
        } finally {
            error_reporting($old);
        }
    }
}
