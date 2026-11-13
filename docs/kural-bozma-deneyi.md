# Kural Bozma Deneyi

> Bu dosya `php bin/kural-boz.php` ile **otomatik üretilir**. Soru: testler ilgili davranışı gerçekten ölçüyor mu?
> Her deneyde çekirdek problemin bir kuralı kaynak kodda geçici olarak bozuldu, testler (eşzamanlılık testleri hariç) çalıştırıldı,
> kırılan testler kaydedildi ve dosya eski hâline döndürüldü. Tek bir deneyi sunumda canlı göstermek için: `php bin/kural-boz.php --deney=hafta-sonu`.

Son çalıştırma: 13.11.2026 22:24 · PHP 8.3.28 · bozulmadan önce 248 test geçti · 7 deneyin 7 tanesinde ilgili testler kırıldı · geri yüklendikten sonra 248 test, hepsi geçti.

| Deney | Bozulan kural | Bozulma ne demek | Kırılan test |
| --- | --- | --- | ---: |
| `yari-acik-aralik` | BR-11 / BR-13: yarı açık aralık; çıkış günü başka misafirin giriş günü olabilir | Çıkış günü de dolu sayılır; arka arkaya iki rezervasyon çakışıyor görünür. | 2 / 248 |
| `suresi-dolan-bekletme` | BR-12 / BR-15: süresi dolan Beklemede kayıt geceleri engellemez | Süresi dolmuş bekletme de geceleri tutmaya devam eder. | 7 / 248 |
| `hafta-sonu` | BR-23: hafta sonu farkı Cuma ve Cumartesi gecelerine uygulanır | Hafta sonu Cumartesi ve Pazar geceleri sayılır. | 5 / 248 |
| `uzun-konaklama` | BR-24: ulaşılan en yüksek uzun konaklama eşiği uygulanır | En düşük eşik seçilir (28 gecede %20 yerine %10). | 1 / 248 |
| `iade-orani` | BR-17: Orta politikada girişe en az 5 gün kala %100, 1–4 gün kala %50 iade | Eşik bir gün kayar; 4 gün kala da tam iade verilir. | 1 / 248 |
| `kosullu-guncelleme` | Eşzamanlılık: koşullu güncelleme (yalnızca durum hâlâ beklenen değerse değişir) | Durum az önce değişmiş olsa da üzerine yazılır (ör. iptal edilmiş kayıt onaylanır). | 1 / 248 |
| `gece-benzersizligi` | Çift rezervasyon önleme, son savunma: UNIQUE(birim, gece) | Aynı birimin aynı gecesi iki kez yazılabilir. | 3 / 248 |

## `yari-acik-aralik`: BR-11 / BR-13: yarı açık aralık; çıkış günü başka misafirin giriş günü olabilir

Dosya: `src/Repository/Json/JsonReservationRepository.php`

```diff
- && $in < $r['check_out']
+ && $in <= $r['check_out']
```

Kırılan testler (2): `ReservationRepositoryContractTest::testOverlapQueryUsesHalfOpenInterval with data set "JSON deposu"`, `ReservationRepositoryContractTest::testSameNightCannotBeStoredTwice with data set "JSON deposu"`.

## `suresi-dolan-bekletme`: BR-12 / BR-15: süresi dolan Beklemede kayıt geceleri engellemez

Dosya: `src/Service/BookingConflictService.php`

```diff
- ReservationStatus::Beklemede => $reservation->expiresAt === null || $reservation->expiresAt > $now,
+ ReservationStatus::Beklemede => true,
```

Kırılan testler (7): `BookingConflictServiceTest::testExpiredHoldDoesNotBlockEvenBeforeCleanup`, `LoggingAndAuditTest::testHoldExpiresExactlyAtServerTime`, `ReservationFlowTest::testPaymentAfterExpiryIsRejectedAndHoldExpires`, `ReservationFlowTest::testCs10HostCannotApproveAfter24Hours`, `ReservationServiceRulesTest::testCancellingAnExpiredHoldMarksItExpiredInsteadOfCancelled`, `ReservationServiceRulesTest::testHostRejectionRules`, `ReservationServiceRulesTest::testAdminRemovalSkipsPastStaysAndExpiresStaleHolds`.

## `hafta-sonu`: BR-23: hafta sonu farkı Cuma ve Cumartesi gecelerine uygulanır

Dosya: `src/Service/Pricing/NightPrice.php`

```diff
- in_array((int) $this->date->format('N'), [5, 6], true)
+ in_array((int) $this->date->format('N'), [6, 7], true)
```

Kırılan testler (5): `PricingServiceTest::testAc23SeasonAndWeekendNightlyBreakdown`, `PricingServiceTest::testBaseOnlyWeekendOnlyAndSeasonOnly`, `PricingServiceTest::testSeasonBoundaryNightsAreInclusive`, `PricingServiceTest::testCalendarExceptionOverridesSeasonAndWeekend`, `PricingServiceTest::testRoundingIsPerNightAndLinesSumToTotal`.

## `uzun-konaklama`: BR-24: ulaşılan en yüksek uzun konaklama eşiği uygulanır

Dosya: `src/Service/Pricing/LongStayDiscountStrategy.php`

```diff
- if ($best === null || $min > (int) $best->minNights
+ if ($best === null || $min < (int) $best->minNights
```

Kırılan testler (1): `PricingServiceTest::testLongStayThresholds`.

## `iade-orani`: BR-17: Orta politikada girişe en az 5 gün kala %100, 1–4 gün kala %50 iade

Dosya: `src/Service/Refund/ModeratePolicy.php`

```diff
- return $daysLeft >= 5 ? 100 : 50;
+ return $daysLeft >= 4 ? 100 : 50;
```

Kırılan testler (1): `RefundServiceTest::testThresholdDays with data set "Orta 4 gün: %50"`.

## `kosullu-guncelleme`: Eşzamanlılık: koşullu güncelleme (yalnızca durum hâlâ beklenen değerse değişir)

Dosya: `src/Repository/Json/JsonReservationRepository.php`

```diff
- fn (array $r): bool => $r['id'] === $id && $r['status'] === $expected->value,
+ fn (array $r): bool => $r['id'] === $id,
```

Kırılan testler (1): `ReservationRepositoryContractTest::testConditionalUpdateOnlyChangesTheExpectedStatus with data set "JSON deposu"`.

## `gece-benzersizligi`: Çift rezervasyon önleme, son savunma: UNIQUE(birim, gece)

Dosya: `src/Repository/Json/JsonSchema.php`

```diff
- 'unique' => [['unit_id', 'night_date']]
+ 'unique' => []
```

Kırılan testler (3): `JsonDatabaseTest::testUniqueUnitNightIsEnforced`, `ReservationFlowTest::testT07FailureWhileWritingNightsRollsBackEverything`, `ReservationRepositoryContractTest::testSameNightCannotBeStoredTwice with data set "JSON deposu"`.
