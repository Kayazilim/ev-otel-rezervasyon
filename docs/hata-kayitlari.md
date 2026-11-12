# Hata Kayıtları

> Ödev 7. bölüm: "İnceleme sırasında tespit edilen sorunları somut biçimde yazın. Geri bildirime yanıtınızı ve yaptığınız düzeltmeleri issue ve pull request bağlantılarıyla raporlayın."

Bu dosyada denemelerde ve incelemelerde bulunan hatalar kayıtlıdır. Her kayıt şunları içerir:

- belirti: kullanıcının gördüğü,
- hatanın nasıl yeniden üretildiği,
- kök neden,
- düzeltme,
- düzeltmeyi doğrulayan test.

Yeni bir hata bulunduğunda aynı biçimde `H-03`, `H-04`… olarak eklenir.

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
