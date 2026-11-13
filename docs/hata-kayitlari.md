# Hata Kayıtları

> Ödev 7. bölüm: "İnceleme sırasında tespit edilen sorunları somut biçimde yazın. Geri bildirime yanıtınızı ve yaptığınız düzeltmeleri issue ve pull request bağlantılarıyla raporlayın."

Bu dosyada denemelerde ve incelemelerde bulunan hatalar kayıtlıdır. Her kayıt şunları içerir:

- belirti: kullanıcının gördüğü,
- hatanın nasıl yeniden üretildiği,
- kök neden,
- düzeltme,
- düzeltmeyi doğrulayan test.

Yeni bir hata bulunduğunda aynı biçimde sıradaki numarayla (`H-11`, `H-12`…) eklenir. Numaralar hatanın bulunduğu tarihe göre değil, bu dosyaya yazıldığı sıraya göre verilir.

## Özet

| No | Tarih | Hata | Nasıl bulundu | PR |
| --- | --- | --- | --- | --- |
| H-01 | 12.11 | "Müsait değil" yanıtı dolu geceyi söylemiyordu; çıkış günü kuralı bozuk sanıldı | Kaan Şahin elle denerken | [#88](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/88) |
| H-02 | 12.11 | Parola sıfırlama bağlantısı 60 dakikadan sonra da formu açıyordu | Kaan Şahin elle denerken | [#88](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/88) |
| H-03 | 08.11 | Sıfırlama e-postası görünmüyordu; kayıtlı olmayan adreste de "gönderildi" deniyordu | Kaan Şahin elle denerken | [#81](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/81) |
| H-04 | 08.11 | Gönderilen şikâyetin nereye düştüğü belli değildi | Kaan Şahin elle denerken | [#83](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/83) |
| H-05 | 13.11 | Adreste dizi biçimli değer sayfaya PHP uyarısını dosya yoluyla basıyordu | Otomatik tarama | [Ekip dolduracak: PR bağlantısı] |
| H-06 | 13.11 | İlan sahibi takviminde geçersiz ay sunucu hatası veriyordu | Otomatik tarama | [Ekip dolduracak: PR bağlantısı] |
| H-07 | 13.11 | Aynı vergi ve aynı uzun konaklama eşiği tekrar eklenebiliyordu; vergi iki kez alınıyordu | Otomatik form taraması | [Ekip dolduracak: PR bağlantısı] |
| H-08 | 13.11 | Listede olmayan seçim değeri sessizce varsayılana dönüyordu | Otomatik form taraması | [Ekip dolduracak: PR bağlantısı] |
| H-09 | 13.11 | Profilden parola değişince eski sıfırlama bağlantısı geçerli kalıyordu | Kod okuma | [Ekip dolduracak: PR bağlantısı] |
| H-10 | 13.11 | Şikâyette seçilen ilan rezervasyonun ilanı olmasa da kayıt açılıyordu | Kod okuma | [Ekip dolduracak: PR bağlantısı] |

13.11 denetiminde uygulama, gerçek veriye dokunmadan geçici bir veri klasörüyle çalıştırıldı. Dört rolle (misafir, müşteri, ilan sahibi, yönetici) her sayfa geçersiz tarih, sayı, dizi ve uzun metinle istendi (7332 istek), her form tuhaf alan değerleriyle gönderildi (1351 istek). Başka kullanıcının ilanını, odasını, fiyat kuralını, takvimini ve rezervasyonunu değiştirme denemelerinin (23 deneme) hepsi 403 ile reddedildi; veri dosyaları değişmedi.

## H-01 · "Çıkış günü giriş yapılamıyor"

| Alan | İçerik |
| --- | --- |
| Bildiren, tarih | Kaan Şahin, 12.11.2026 (uygulamayı elle denerken) |
| Belirti | "Bir misafirin çıkış günü başka misafir girebilmeli (BR-13), ama yapılamıyor." Rezervasyon denemesi "Seçtiğiniz tarihler müsait değil" yanıtı aldı. |
| Yeniden üretme | Uygulamanın günlüğü (`storage/logs/app.log`) ve veri dosyaları incelendi. Karadeniz Otel'in 5 numaralı odasında `#13` 11–14 Kasım onaylıydı. Aynı gün 14–15 Kasım rezervasyonu iki kez başarıyla oluşturuldu (`#55` iptal edildi, `#56` onaylandı); yani çıkış günü olan 14'ünde giriş yapılabildi. 14–15 yeniden denendiğinde "müsait değil" yanıtı geldi, çünkü 14 gecesini artık `#56` tutuyordu. Kural sunucunun her katmanında doğru çalışıyordu: arama, JSON ucu, aylık takvim, önizleme ve rezervasyon akışı çıkış gününü girişe açık sayıyordu. Günlükte hiç çakışma reddi yoktu. |
| Kök neden | Kullanılabilirlik hatası: "müsait değil" yanıtı hangi gecenin dolu olduğunu söylemiyordu. Kullanıcı reddin nedenini göremediği için çıkış günü kuralının bozuk olduğunu düşündü. |
| Düzeltme | `BookingConflictService::unavailableNights` aralıktaki dolu geceleri verir: başka rezervasyonların geceleri ve ilan sahibinin kapattığı geceler. Yarı açık aralık gereği bir konaklamanın çıkış günü bu listede yer almaz. "Müsait değil" yanıtı artık dolu geceleri ve kısa bir açıklamayı gösteriyor: ilan sayfasındaki fiyat önizlemesi, `GET /api/musaitlik` yanıtındaki `dolu_geceler` alanı, rezervasyon önizleme sayfası ve onay anındaki çakışma mesajı (`ConflictException::nights`). Örnek: "Dolu gece: 16.11.2026, 17.11.2026. Bir konaklamanın çıkış günü yeni girişe açıktır…" |
| Doğrulayan testler | `BookingConflictServiceTest::testUnavailableNightsNameOnlyTheTakenNights` (10–12 dolu, 13 kapalı → 11–14 sorgusunda yalnızca 11 ve 13; çıkış günü 12 dolu sayılmaz). `testAssertAvailableExpiresStaleHoldsAndRejectsRealConflicts` (çakışma istisnası dolu geceyi taşır). `WebTest::testUnavailableAnswerNamesTheTakenNights` (JSON ucu ve önizleme sayfası). Çıkış günü kuralının kendisi zaten `testCheckOutDayIsFreeForTheNextGuest` ve `ReservationRepositoryContractTest::testOverlapQueryUsesHalfOpenInterval` ile sınanıyordu. |
| Canlı deneme | 2 numaralı odada 15–18 Kasım dolu. 16–19 sorgusu "müsait değil" ve "Dolu gece: 16.11.2026, 17.11.2026" diyor; çıkış günü 18–20 sorgusu müsait. |
| Durum | Düzeltildi: [Kayazilim/ev-otel-rezervasyon](https://github.com/Kayazilim/ev-otel-rezervasyon), dal `hata-cikis-gunu-ve-sifirlama-suresi`, PR [#88](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/88). |

## H-02 · "Parola sıfırlama bağlantısı 60 dakika sonra da geçerli kalıyor"

| Alan | İçerik |
| --- | --- |
| Bildiren, tarih | Kaan Şahin, 12.11.2026 (uygulamayı elle denerken) |
| Belirti | E-postayla gelen parola sıfırlama bağlantısı 60 dakika geçtikten sonra da açılıyor ve parola formunu gösteriyordu; bağlantı "hep geçerli" görünüyordu. |
| Yeniden üretme | Bağlantıyı 60 dakika sonra açınca sayfa yine parola formunu gösterdi. Hata ancak form gönderilince çıkıyordu; o da parola kısaysa önce parola uyarısıydı. |
| Kök neden | Parola formunu gösteren `AuthController::showReset` (`GET /parola-sifirla/{jeton}`) jetonu hiç denetlemiyordu. Süre ve tek kullanımlık denetimi yalnızca form gönderilince (`AuthService::resetPassword`) yapılıyordu; parola kuralı da jetondan önce denetleniyordu. Süresi dolmuş bağlantıyla parola değiştirilemiyordu, ama kullanıcı bunu göremiyordu. Bu açık 10.11 denetiminde "yalnızca raporlanacak" diye not edilmiş, düzeltilmemişti. |
| Düzeltme | Yeni `AuthService::usableResetToken` bağlantının durumunu denetler ve her durum için ayrı, anlaşılır mesaj verir: bağlantı yok, kullanılmış ya da yerine yeni bağlantı istenmiş, süresi dolmuş ("bağlantılar 60 dakika geçerlidir"). Aynı denetim hem sayfa açılırken hem parola gönderilirken yapılır. Bağlantı geçersizse form gösterilmez; yerine neden ve "Yeni bağlantı iste" düğmesi çıkar (HTTP 410). Geçerliyse formun üstünde bağlantının geçerlilik sonu yazar. Parola gönderilirken önce bağlantının durumu, sonra parola kuralı denetlenir. |
| Doğrulayan testler | `AuthServiceTest::testResetLinkValidityIsCheckedFirstAndExplained`: bağlantı 59. dakikada geçerli, 60. dakikada süresi dolmuş; süresi dolmuş bağlantıda kısa parola girilse de önce bağlantı hatası verilir; yenisi istenince eskisi geçersiz olur; kullanılan bağlantı ikinci kez açılmaz. `PasswordResetWebTest::testExpiredOrUsedLinkShowsAMessageInsteadOfTheForm`: geçerli bağlantıda form ve geçerlilik sonu; 61 dakika sonra 410, form yok; kullanılmış bağlantıda 410. |
| Canlı deneme | Geçerli bağlantı 200 döndü ve formun üstünde "geçerlilik sonu: 12.11.2026 20:45" yazdı. Deneme verisinde bitiş zamanı geçmişe alınınca 410 döndü; form yerine "Bu bağlantının süresi dolmuş (bağlantılar 60 dakika geçerlidir)" mesajı ve "Yeni bağlantı iste" düğmesi göründü. |
| Durum | Düzeltildi: [Kayazilim/ev-otel-rezervasyon](https://github.com/Kayazilim/ev-otel-rezervasyon), dal `hata-cikis-gunu-ve-sifirlama-suresi`, PR [#88](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/88). |

## H-03 · "Sıfırlama bağlantısı gelmiyor; kayıtlı olmayan adrese de gönderildi diyor"

| Alan | İçerik |
| --- | --- |
| Bildiren, tarih | Kaan Şahin, 07–08.11.2026 (yerel sunucuda elle denerken). Issue: [#80](https://github.com/Kayazilim/ev-otel-rezervasyon/issues/80) |
| Belirti | "Parolamı unuttum" denince sıfırlama bağlantısı e-posta kutusuna gelmiyordu. Kayıtlı olmayan bir e-posta girilince de "gönderildi" deniyordu. Bir gün önce sorulan "storage/mail klasörü nerede" sorusu da bu yüzdendi. |
| Yeniden üretme | Kayıtlı bir adresle sıfırlama istendi: ileti `storage/mail/` klasörüne dosya olarak yazılıyor, bağlantı açılıyordu, ama tarayıcıdan görmenin yolu yoktu. Klasör yazılamaz yapılınca hata yutuluyor ve yine "gönderildi" deniyordu. |
| Kök neden | Üç ayrı sorun vardı. (1) E-posta yalnızca simüle ediliyordu (dosyaya yazılıyordu) ve uygulama içinde görülemiyordu. (2) Kayıtlı olmayan adreste aynı yanıt bilinçliydi (hesabın var olup olmadığı sızdırılmaz), ama mesaj bunu açıklamıyordu. (3) `FileMailer` dosyayı yazamazsa hatayı yutuyordu. |
| Düzeltme | Simüle e-postalar uygulama içindeki `/posta-kutusu` sayfasında görünür (yalnızca bu bilgisayardan açılır). İsteğe bağlı gerçek gönderim eklendi (`SmtpMailer`, kütüphanesiz; parola git'e girmeyen `config/yerel.php` dosyasında). İleti yazılamazsa "gönderildi" denmez: 503 ve günlük kaydı. Mesaj, kayıtlı olmayan adreste neden aynı yanıtın verildiğini açıklar. |
| Doğrulayan testler | `PasswordResetWebTest::testUnknownAndKnownAddressesGetTheSameAnswer`, `testResetLinkFromTheMailboxSetsANewPassword`, `testMailboxIsOnlyServedLocallyAndWhenEnabled`, `testDeliveryFailureIsReportedAndLogged`, `testSmtpModeUsesTheRealMailerAndHidesTheMailbox`; `FileMailerTest::testFailureIsReportedInsteadOfPretendingTheMailWasSent`; `FileMailboxTest`; `SmtpMailerTest` (sahte SMTP sunucusuyla). |
| Canlı deneme | Kayıt → sıfırlama isteği → posta kutusu → bağlantı → yeni parola → yeni parolayla giriş uçtan uca denendi. Bağlantının ikinci kullanımı reddedildi. |
| Durum | Düzeltildi: PR [#81](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/81). Bağlantının süresi ayrıca H-02'de düzeltildi. |

## H-04 · "Şikâyet edince nereye düşüyor?"

| Alan | İçerik |
| --- | --- |
| Bildiren, tarih | Kaan Şahin, 08.11.2026 (elle denerken). Issue: [#82](https://github.com/Kayazilim/ev-otel-rezervasyon/issues/82) |
| Belirti | Şikâyet gönderildikten sonra kullanıcı onun nereye gittiğini, kimin okuyacağını ve sonucunu nasıl öğreneceğini göremiyordu. |
| Yeniden üretme | Müşteri hesabıyla şikâyet gönderildi. Kayıt veriye yazılıyordu, ama yöneticiye bildirim gitmiyordu ve kullanıcının şikâyetlerini izleyeceği bir sayfa yoktu. |
| Kök neden | Kullanılabilirlik hatası: FR-27'nin "yönetici inceler ve yanıtlar" kısmı arayüzde ve bildirimlerde eksikti. |
| Düzeltme | Şikâyet yönetimin "Şikâyetler" kutusuna düşer ve aktif yöneticilere bildirim gider. Kullanıcı "Şikâyetlerim" sayfasından durumu izler. Yönetici durumu ya da notu değiştirince şikâyet sahibine bildirim gider. Formda ilan seçilebilir; gönderince mesaj şikâyetin nereye düştüğünü söyler. |
| Doğrulayan testler | `ComplaintFlowTest::testComplaintLandsInTheAdminInboxAndNotifiesEveryActiveAdmin`, `testAdminAnswerReachesTheSenderAndMyComplaintsShowsOnlyOwnComplaints`, `testSubmittingFromTheWebRedirectsToMyComplaints`. |
| Durum | Düzeltildi: PR [#83](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/83). |

## H-05 · Adreste dizi biçimli değer sayfaya PHP uyarısı ve dosya yolu basıyor

| Alan | İçerik |
| --- | --- |
| Bulan, tarih | Otomatik tarama, 13.11.2026 |
| Belirti | `/ara?puan[]=1` açılınca sayfanın içine "Warning: Array to string conversion in C:\wamp64\www\…\src\Core\View.php on line 152" yazıyordu. Yönetim sayfalarında `?rol[]=1` ve `?durum[]=1` aynı sonucu veriyordu. Ödevin 4. bölümü kullanıcıya dosya yolu ve teknik ayrıntı gösterilmesini yasaklıyor. |
| Yeniden üretme | Adres çubuğunda bir seçim alanını dizi olarak yazmak yeterli (ör. `puan[]=1`). |
| Kök neden | (1) Görünümdeki `View::selected()` gelen değeri doğrudan metne çeviriyordu; dizi gelince PHP uyarı veriyordu. (2) Uygulama PHP uyarılarını yakalamıyordu; WAMP'ta `display_errors` açık olduğu için uyarı dosya yoluyla sayfaya basılıyordu. |
| Düzeltme | `View::selected()` metin olmayan değerde hiçbir seçeneği seçmez. Genel önlem olarak `Kernel`, istek işlenirken oluşan PHP uyarılarını istisnaya çevirir: ayrıntı günlüğe yazılır, kullanıcı sade hata sayfası görür. `@` ile susturulan uyarılar ve kullanımdan kalkma bildirimleri bunun dışındadır. `public/index.php` de `display_errors` ayarını kapatır. |
| Doğrulayan testler | `WebTest::testArrayValuesInTheQueryStringDoNotLeakWarnings`, `KernelTest::testWarningsBecomeExceptions`, `KernelTest::testSilencedWarningsAndDeprecationsAreLeftAlone`. Düzeltme geri alınınca test "View.php:152 Array to string conversion" uyarısıyla kırıldı. |
| Canlı deneme | `/ara?puan[]=1` 200 döndü, sayfada uyarı yok. Tarama yeniden çalıştırıldı: 7332 istekte sorun yok, günlükte hata yok. |
| Durum | Düzeltildi (dal `hata-kayitlari-gecmis-ve-denetim`). **[Ekip dolduracak: PR bağlantısı]** |

## H-06 · İlan sahibi takviminde geçersiz ay sunucu hatası veriyor

| Alan | İçerik |
| --- | --- |
| Bulan, tarih | Otomatik tarama, 13.11.2026 |
| Belirti | İlan sahibi `/panel/takvim?ay=2026-13` açınca "Beklenmeyen hata" (500) alıyordu; `?ay=2026-00` sayfaya PHP uyarılarını basıyordu. İlan sayfasındaki takvim aynı girdide sorunsuzdu. |
| Yeniden üretme | İlan sahibi hesabıyla takvim adresindeki `ay` değerini 00 ya da 13 yapmak. |
| Kök neden | `HostController` ay değerinin yalnızca biçimine (`YYYY-AA`) bakıyor, 1–12 aralığını denetlemiyordu. 13. ay tarih oluşturulurken, 0. ay ay adı aranırken hata veriyordu. İlan sayfasındaki takvim bu denetimi yapıyordu; aynı iş için iki yerde farklı kural vardı. |
| Düzeltme | Takvim sayfası da yalnızca 1–12 arasındaki ayı kabul eder; geçersizse bu ayı gösterir. |
| Doğrulayan testler | `WebTest::testHostCalendarShowsThisMonthForAnInvalidMonth` (2026-00, 2026-13, 2026-99 → 200 ve "Kasım 2026"; 2027-01 → "Ocak 2027"). |
| Canlı deneme | `?ay=2026-00` ve `?ay=2026-13` 200 döndü ve bu ayı gösterdi. |
| Durum | Düzeltildi (dal `hata-kayitlari-gecmis-ve-denetim`). **[Ekip dolduracak: PR bağlantısı]** |

## H-07 · Aynı vergi ve aynı uzun konaklama eşiği tekrar eklenebiliyor

| Alan | İçerik |
| --- | --- |
| Bulan, tarih | Otomatik form taraması, 13.11.2026 |
| Belirti | Fiyat kuralları sayfasında "Turizm vergisi %2" defalarca eklenebildi. Her vergi fiyata ayrı eklendiği için aynı vergi iki kez eklenince müşteriden iki kez alınıyordu. "10 gece ve üzeri %5" uzun konaklama kuralı da tekrar tekrar eklenebiliyordu; aynı eşikte iki farklı indirim olunca hangisinin uygulanacağı belirsizdi. |
| Yeniden üretme | İlan sahibi aynı vergiyi iki kez ekler, sonra fiyat önizlemesine bakar: vergi satırı iki kez görünür. |
| Kök neden | `PriceRuleService::addPricingRule` ve `addLongStayRule` var olan kuralları denetlemiyordu. Sezon kuralında çakışma, hafta sonu kuralında tekillik denetimi vardı; bu ikisinde yoktu. |
| Düzeltme | Aynı adda (büyük/küçük harf ve boşluk farkı gözetilmeden) ikinci bir vergi/ek ücret eklenemez. Aynı kapsamda (ilan geneli ya da aynı oda) aynı gece eşiği ikinci kez tanımlanamaz. İkisinde de anlaşılır bir mesaj verilir. |
| Doğrulayan testler | `PriceRuleServiceTest::testSameTaxCannotBeAddedTwice` (1 gece 1.000 TL + %10 KDV = 1.100 TL; vergi bir kez), `testSameLongStayThresholdCannotBeAddedTwice`. |
| Durum | Düzeltildi (dal `hata-kayitlari-gecmis-ve-denetim`). **[Ekip dolduracak: PR bağlantısı]** |

## H-08 · Listede olmayan seçim değeri sessizce varsayılana dönüyor

| Alan | İçerik |
| --- | --- |
| Bulan, tarih | Otomatik form taraması, 13.11.2026 |
| Belirti | İlan sahibi formlarında bir seçim alanına listede olmayan değer gönderilince hata verilmiyor, varsayılan seçenek kullanılıyordu. En zararlısı takvimdi: geçersiz durum "Müsait" sayılıyor ve kapatılan geceler açılıyordu. Vergi türü, hesaplama yöntemi, indirim türü ve oda durumu alanları da aynıydı. |
| Yeniden üretme | Takvimde geceleri kapattıktan sonra aynı formu `durum=herkese-acik` ile göndermek: geceler açılıyordu. |
| Kök neden | `HostController` bu alanları `tryFrom(...) ?? varsayılan` ile okuyordu. Ödevin 4. bölümü form girdilerinin sunucuda doğrulanmasını istiyor. |
| Düzeltme | Alan boşsa varsayılan kullanılır. Doluysa listedeki bir değer olmalıdır; değilse "Listeden geçerli bir seçenek seçin." hatası verilir ve kayıt değişmez. Takvim ve oda formunda hata alanın altında gösterilir. |
| Doğrulayan testler | `WebTest::testUnknownChoiceValuesAreRejectedInsteadOfDefaulted` (takvim, vergi ve oda formu; kapatılan geceler açılmaz). |
| Durum | Düzeltildi (dal `hata-kayitlari-gecmis-ve-denetim`). **[Ekip dolduracak: PR bağlantısı]** |

## H-09 · Profilden parola değişince eski sıfırlama bağlantısı geçerli kalıyor

| Alan | İçerik |
| --- | --- |
| Bulan, tarih | Kod okuma, 13.11.2026 |
| Belirti | Kullanıcı sıfırlama bağlantısı istedikten sonra parolasını profil sayfasından değiştirirse, e-postadaki eski bağlantı 60 dakika boyunca çalışmaya devam ediyordu. E-postasına başkası erişebiliyorsa parolayı yeniden değiştirebilirdi. |
| Kök neden | Yeni bir sıfırlama isteği eski bağlantıları geçersiz kılıyordu, ama `AuthService::changePassword` bunu yapmıyordu. |
| Düzeltme | Parola değişince kullanıcının açık sıfırlama bağlantıları kullanılmış sayılır. |
| Doğrulayan testler | `AuthServiceTest::testChangingThePasswordInvalidatesOpenResetLinks`. |
| Durum | Düzeltildi (dal `hata-kayitlari-gecmis-ve-denetim`). **[Ekip dolduracak: PR bağlantısı]** |

## H-10 · Şikâyette seçilen ilan rezervasyonla uyuşmasa da kayıt açılıyor

| Alan | İçerik |
| --- | --- |
| Bulan, tarih | Kod okuma, 13.11.2026 |
| Belirti | Şikâyet formunda bir ilan seçilip başka bir ilana ait kendi rezervasyon numarası yazılınca şikâyet seçilen ilana yazılıyordu. Yönetici yanlış ilanı inceliyordu. |
| Kök neden | `ComplaintService::create` rezervasyonun ilanını yalnızca ilan boş bırakıldığında kullanıyordu; ikisi birden verildiğinde uyuşup uyuşmadıklarına bakmıyordu. |
| Düzeltme | İkisi uyuşmazsa "Seçtiğiniz ilan bu rezervasyonun ilanı değil" hatası verilir ve kayıt açılmaz. İlan boşsa rezervasyonun ilanı kullanılır. |
| Doğrulayan testler | `ComplaintFlowTest::testPropertyMustMatchTheReservation`. |
| Durum | Düzeltildi (dal `hata-kayitlari-gecmis-ve-denetim`). **[Ekip dolduracak: PR bağlantısı]** |

## Önceki incelemelerde bulunan hatalar

Aşağıdaki hatalar Kaan Şahin'in deposundaki ([Kayazilim/ev-otel-rezervasyon](https://github.com/Kayazilim/ev-otel-rezervasyon)) denetimlerde bulunup düzeltildi. Ayrıntılar [ai-gunlugu.md](ai-gunlugu.md) dosyasında.

| Tarih | Hata | Düzeltme | PR |
| --- | --- | --- | --- |
| 09.11 | Örnek verideki GD ile çizilmiş fotoğraflar ders kuralına ("çizim dosyası hazırlanmaz") aykırıydı. | Önce kapatıldı, sonra çizim kodu silindi. | [#84](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/84), [#86](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/86) |
| 10.11 | 30 dakikalık oturum zaman aşımı hiç çalışmıyordu: kendiliğinden yenilenen bölümler oturumu her 5 saniyede bir uzatıyordu. | Arka plan istekleri işlem sayılmıyor; oturumu biten istek 401 alıyor. | [#86](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/86) |
| 10.11 | Parça isteğinde hata olunca bölümün içine tam hata sayfası basılıyordu. | Kısa uyarı parçası dönüyor. | [#86](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/86) |
| 10.11 | Yönetici şikâyet notu 2000 karakteri aşınca 500 hatası veriyordu; listede olmayan durum sessizce "İnceleniyor" oluyordu. | İkisi de anlaşılır mesajla reddediliyor. | [#86](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/86) |
| 10.11 | UTF-8 olmayan girdi 500 hatası veriyordu. | Açıklamalı 400 dönüyor. | [#86](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/86) |
| 10.11 | Kontrast WCAG AA altındaydı (4,47:1), klavye odağı görünmüyordu, seçim listeleri formu kendiliğinden gönderiyordu (NFR-12). | Renk, odak çerçevesi ve "Göster" / "Uygula" düğmeleri. | [#86](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/86) |
| 12.11 | Hatalı formda girilen değerler kayboluyordu (8 form). | 422 ile yeniden gösterim; satır içi formlarda PRG korunarak değerler geri basılıyor. | [#87](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/87) |
| 12.11 | AJAX isteklerinde sunucu hata verince ya da bağlantı kopunca kullanıcı hiçbir mesaj görmüyordu. | Bölümün başında anlaşılır uyarı. | [#87](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/87) |
| 12.11 | Kural bozma deneyi, yarı açık aralık sorgusunun ve koşullu güncellemenin hızlı testlerde testsiz kaldığını gösterdi. | Depo sözleşme testi eklendi. | [#87](https://github.com/Kayazilim/ev-otel-rezervasyon/pull/87) |
