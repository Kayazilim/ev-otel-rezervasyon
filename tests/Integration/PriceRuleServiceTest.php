<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exception\ValidationException;
use App\Model\CalcMethod;
use App\Model\PricingRuleType;
use App\Model\Role;
use App\Model\ValueType;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AppTestCase;

/**
 * İlan sahibinin fiyat kuralları (FR-43) ve vergi/ek ücretleri.
 * Hata kaydı H-07: aynı vergi ve aynı uzun konaklama eşiği ikinci kez eklenemez.
 */
#[Group('FR-43')]
final class PriceRuleServiceTest extends AppTestCase
{
    /** @return array<string, string> */
    private function errorsOf(callable $action): array
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        self::fail('ValidationException bekleniyordu');
    }

    /** Her vergi fiyata ayrı eklenir; aynı ad (büyük/küçük harf ve boşluk farkı dahil) ikinci kez eklenseydi vergi iki kez alınırdı. */
    #[Group('FR-14')]
    public function testSameTaxCannotBeAddedTwice(): void
    {
        $host = $this->user(Role::IlanSahibi);
        $property = $this->property((int) $host->id);
        $unit = $this->unit((int) $property->id, 1000);
        $rules = $this->c->priceRuleService();
        $rules->addPricingRule((int) $host->id, (int) $property->id, 'KDV', PricingRuleType::Vergi, CalcMethod::Yuzde, 1000);

        $errors = $this->errorsOf(fn () => $rules->addPricingRule((int) $host->id, (int) $property->id, ' kdv ', PricingRuleType::Vergi, CalcMethod::Yuzde, 1000));

        self::assertArrayHasKey('ad', $errors);
        self::assertCount(1, $this->c->pricingRules()->findByProperty((int) $property->id));
        // 1 gece 1.000 TL + %10 KDV = 1.100 TL (vergi bir kez)
        self::assertSame(110000, $this->c->pricingService()->quote($unit, $this->range('2026-11-10', '2026-11-11'))->total);

        $rules->addPricingRule((int) $host->id, (int) $property->id, 'Temizlik ücreti', PricingRuleType::EkUcret, CalcMethod::RezervasyonBasinaSabit, 20000);
        self::assertCount(2, $this->c->pricingRules()->findByProperty((int) $property->id), 'Farklı adlı kalem eklenebilir');
    }

    /** Aynı kapsamda (ilan geneli ya da aynı oda) aynı gece eşiği iki kez tanımlanamaz; farklı eşik ya da farklı oda olur. */
    #[Group('BR-24')]
    public function testSameLongStayThresholdCannotBeAddedTwice(): void
    {
        $host = $this->user(Role::IlanSahibi);
        $property = $this->property((int) $host->id);
        $unit = $this->unit((int) $property->id, 1000);
        $rules = $this->c->priceRuleService();
        $rules->addLongStayRule((int) $host->id, (int) $property->id, null, 7, 1000, ValueType::Yuzde);

        $errors = $this->errorsOf(fn () => $rules->addLongStayRule((int) $host->id, (int) $property->id, null, 7, 2000, ValueType::Yuzde));

        self::assertArrayHasKey('en_az_gece', $errors);
        $rules->addLongStayRule((int) $host->id, (int) $property->id, null, 14, 1500, ValueType::Yuzde);
        $rules->addLongStayRule((int) $host->id, (int) $property->id, (int) $unit->id, 7, 1200, ValueType::Yuzde);
        self::assertCount(3, $this->c->priceRules()->findByProperty((int) $property->id));
    }
}
