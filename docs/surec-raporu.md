# Süreç Raporu · Aşama 2 (Veritabanısız Çalışan Prototip)

Proje 12 · EvOtel · Otel / Kiralık Ev Rezervasyon Sistemi · Gümüşhane Üniversitesi, PHP Programlama
Ekip: Kaan Şahin (proje yöneticisi), Utku Camuzcu (mimar), İrem Ablak (veritabanı ve test), Onur Akbalık (arayüz ve dokümantasyon)
Rapor tarihi: 12.11.2026

> Köşeli parantez içindeki **[Ekip dolduracak: …]** notları, raporu yazanın bilmediği ve uydurmaması gereken bilgilerdir (eleştirmen grubun geri bildirimleri, ekip deposuna yükleme sonrası PR bağlantıları). Teslimden önce doldurulmalıdır.

## 1. Proje ne yapıyor, çekirdek problem ne?

EvOtel, otel odası ve kiralık ev rezervasyonu için bir web uygulamasıdır. İlan sahibi ilan ve oda (birim) açar, fiyat kurallarını ve takvimi yönetir; misafir tarih aralığına göre müsait yerleri arar, gecelik fiyat dökümünü görüp rezervasyon yapar ve iptal eder; yönetici kullanıcıları, ilanları ve şikâyetleri yönetir, doluluk ve gelir raporunu görür. Aşama 2'de veriler `storage/data` altındaki JSON dosyalarındadır; veritabanı yoktur.

Çekirdek problem dört parçadır:

1. **Tarih aralığı çakışması:** aralık yarı açıktır, [giriş, çıkış). 10'unda çıkan misafirin yerine aynı gün başka misafir girebilir (BR-11, BR-13).
2. **Çift rezervasyonun önlenmesi:** aynı anda gelen iki istekten yalnızca biri aynı geceleri alabilir (SRS 4.5).
3. **Sezonluk dinamik fiyat:** gece bazında fiyat hesaplanır; takvim istisnası, sezon, hafta sonu (Cuma ve Cumartesi geceleri) ve uzun konaklama indirimi uygulanır (BR-23 – BR-26).
4. **İptal politikası ve iade hesabı:** Esnek, Orta ve Katı politikalar vardır; iade oranı girişe kalan güne göre belirlenir (BR-17, BR-27 – BR-29).

## 2. Aşama 2 boyunca yapılan işler

Tablodaki bilgiler git geçmişinden alındı. Aşama 2 kodu, Kaan Şahin tarafından Claude Code ile kendi deposunda (`Kayazilim/ev-otel-rezervasyon`; özel depo, ekip üyeleri davetle erişir) PR'larla geliştirildi. Ardından katmanlara bölünüp ekip deposuna (`utkucamuzcu07/ev-otel-rezervasyon`) yüklendi: her üye kendi katmanını kendi hesabıyla ve incelemeli bir PR ile yükledi. Yapay zekâ kullanımı [ai-gunlugu.md](ai-gunlugu.md) dosyasında kayıtlıdır.

| Hafta | Yapılan iş | Kim | Kanıt |
| --- | --- | --- | --- |
| 13–19 Ekim (Aşama 1) | SRS ve tasarım belgeleri, sayfa haritası, ilişki açıklamaları, yapay zekâ günlüğü şablonu | Onur (9 commit), Utku (8), Kaan (5), İrem (3) | Ekip deposu, PR #14 – #31 |
| 20–26 Ekim | SRS'nin depoya eklenmesi, tasarım belgesinin güncellenmesi | Onur | Ekip deposu, PR #34, #35 |
| 3–9 Kasım | Belge düzeltmeleri | İrem | Ekip deposu, PR #32 |
| 3–9 Kasım | JSON prototipi (çekirdek servisler, JSON deposu, arayüz, testler); ders standartlarına uyum; parola sıfırlama; arayüz ve örnek veri; çizim fotoğraflarının kapatılması; bildirim aralığı | Kaan (Claude Code ile) | Kaan'ın deposu, PR #1, #78, #79, #81, #83, #84, #85 |
| 10–16 Kasım | Aşama 2 denetimi (oturum zaman aşımı, form doğrulamaları, erişilebilirlik); ödev metnine uyum (form değerlerinin korunması, JSON ile kısmi yenileme, sahte depolu testler, kural bozma deneyi, belgeler) | Kaan (Claude Code ile) | Kaan'ın deposu, PR #86 ve bu raporla gelen PR |
| 10–16 Kasım | Kodun ekip deposuna 20 commit hâlinde yüklenmesi: Utku 4 (altyapı), Onur 4 (model ve depo), İrem 4 (servisler), Kaan 8 (controller, görünüm, örnek veri, belgeler) | Dört üye | **[Ekip dolduracak: dört PR'ın bağlantıları ve birleşme tarihleri]** |

## 3. Tasarım ve uygulama kararları

| Karar | Değerlendirilen alternatif | Gerekçe |
| --- | --- | --- |
| **Çift rezervasyona üç katmanlı güvence:** servis kuralı (`BookingConflictService`) + işlem içinde birim kilidi + gece tablosunda `UNIQUE(birim, gece)` | Yalnızca "önce kontrol et, sonra kaydet" | İki istek aynı anda kontrolü geçip ikisi de kaydedebilir (yarış durumu). Kilit istekleri sıraya sokar; benzersizlik kısıtı da kod hatasına karşı son savunmadır. 50 gerçek paralel süreçle sınandı (T-05: tam 1 başarı). |
| **JSON deposunda yazma:** tek dışlayıcı dosya kilidi (`flock`) altında işlem; veri önce geçici dosyaya yazılıp sonra asıl dosyanın yerine konur | `file_put_contents` ile doğrudan yazma; SQLite | Doğrudan yazmada aynı anda yazan iki süreç dosyayı bozabilir; sunucu yazarken kesilirse dosya yarım kalır. Ödev MySQL ve SQLite'a izin vermez. |
| **Repository deseni:** servisler yalnızca arayüzü bilir; depo seçimi tek yerde (`Container::repository`) | Servislerin dosyaları doğrudan okuması | Aşama 3'te yalnızca depo sınıfları eklenir, servisler değişmez. Testlerde bellekteki sahte depo kullanılabilir. |
| **Kısmi yenilemede iki biçim:** takvim, gelen rezervasyonlar ve bildirim sayısı ders kalıbıyla (HTML parça); rezervasyon durumu JSON ile | Hepsini HTML parça yapmak; hepsini JSON yapmak | Ders kalıbı HTML parçayı, ödev metni JSON'u istiyor. Rezervasyon durumu seçildi, çünkü bekletme süresi çekirdek problemin parçasıdır. Süre sunucuda hesaplanır, tarayıcı yalnızca gösterir. |
| **Satır içi formlarda hata:** PRG korunur; girilen değer ve hata oturumda bir kez saklanıp formun bulunduğu sayfaya dönülür | Hata sayfasını `422` ile yeniden çizmek | Yönetici listelerinde onlarca satır içi form vardır; yeniden çizimde filtre ve sayfa kaybolur. Yönlendirme sayesinde sayfa yenilense de işlem tekrarlanmaz. |

## 4. Yapay zekâ günlüğünden üç örnek

1. **Yapay zekânın yanıldığı örnek (kural ihlali):** "İlanların resimleri olsun" isteği üzerine yapay zekâ, örnek veriye PHP GD ile çizilmiş 36 fotoğraf ekledi. Bu, ders kuralına ("Resim, ikon veya çizim dosyası hazırlanmaz") aykırıydı ve yapay zekâ çelişkiyi söylemedi. 09.11'deki kural denetiminde fark edildi. Önce kapatıldı (PR #84), sonra kod depodan silindi (PR #86).
2. **Yapay zekânın yazdığı koddaki hata:** 5 saniyede bir yenilenen bölümler oturumun son işlem zamanını her seferinde ilerletiyordu; bu yüzden 30 dakikalık oturum zaman aşımı hiç çalışmıyordu. Kod incelemesinde fark edildi. Zaman aşımı geçici sunucuda 1 dakikaya indirilip tarayıcıda denendi; düzeltildi ve testi yazıldı. 12.11'de aynı hata yeni JSON ucunda tekrarlandı, gözden geçirmede yakalandı.
3. **Testlerin yetersiz kaldığı örnek:** Çekirdek birim testleri bellekteki sahte depoya taşındığında, JSON deposunun yarı açık aralık sorgusunu ve koşullu güncellemesini sınayan test kalmadı. `bin/kural-boz.php` deneyinde kural bozulunca 0 test kırıldı. Aynı testleri hem JSON deposunda hem sahte depoda çalıştıran sözleşme testi eklendi; deney yeniden çalıştırıldığında 7 kuralın 7'si yakalandı.

## 5. Testler

- **Sayı:** ekip deposundaki yalnızca JSON sürümünde 237 test (1747 doğrulama); MySQL depo sınıflarının da bulunduğu Kaan Şahin deposunda JSON ile 244 test (1788 doğrulama; MySQL'e özel 7 test JSON'da atlanır) ve MySQL 8.4 ile 244 test (1842 doğrulama), hepsi geçti. Komut: `vendor/bin/phpunit` (veya `composer test`).
- **Önemli sınır durumları:**
  - Çıkış günü yeni girişe açıktır: 10–12 rezervasyonu varken 12–14 kabul edilir, 11–13 reddedilir (`BookingConflictServiceTest::testCheckOutDayIsFreeForTheNextGuest`, `ReservationRepositoryContractTest::testOverlapQueryUsesHalfOpenInterval`).
  - Bekletme süresi saniyesi saniyesine sınanır: bitişten 1 saniye önce geçerli, 1 saniye sonra geçersizdir (`testExpiredHoldDoesNotBlockEvenBeforeCleanup`).
  - Orta politikada 5 gün kala %100, 4 gün kala %50 iade (`RefundServiceTest`).
- **İzlenebilirlik tablosu:** [izlenebilirlik.md](izlenebilirlik.md). Kullanıcı hikâyeleri (US-01 – US-04) ve her FR/NFR/BR/AC/CS/T kodu, onu doğrulayan testlere bağlanır; tablo `composer izlenebilirlik` ile koddan üretilir.
- **Tekrarlanabilirlik:** saat sabittir (`FrozenClock`). Çakışma ve fiyat birim testleri bellekteki sahte depoyla çalışır; entegrasyon testleri her test için ayrı geçici klasör kullanır; projenin `storage/data` dosyaları hiçbir testte açılmaz.
- **Testlerin gerçekten ölçtüğünün kanıtı:** [kural-bozma-deneyi.md](kural-bozma-deneyi.md). 7 önemli kural (yarı açık aralık, süresi dolan bekletme, hafta sonu, uzun konaklama eşiği, iade oranı, koşullu güncelleme, gece benzersizliği) geçici olarak bozuldu; her birinde ilgili testler kırıldı.
- **Statik analiz:** PHPStan seviye 6 (`src`, `bin`, `config`, `tests`) hatasız; PHPCS (PSR-12) hatasız.

## 6. Karşılaşılan üç önemli sorun ve çözümü

1. **Oturum zaman aşımı hiç çalışmıyordu.** Neden: kendiliğinden yenilenen bölümlerin istekleri kullanıcı işlemi sayılıyordu. Çözüm: `/parca/` ve `GET /api/` istekleri son işlem zamanını ilerletmiyor; oturumu biten arka plan isteği `401` alıyor ve sayfa kullanıcıyı giriş sayfasına götürüyor.
2. **Ders kuralına aykırı örnek veri.** Neden: örnek verideki çizilmiş fotoğraflar "çizim dosyası hazırlanmaz" kuralını çiğniyordu. Çözüm: çizim kodu silindi; ilanlar emoji yer tutucuyla görünüyor, gerçek fotoğrafı ilan sahibi yüklüyor. Ders kuralıyla çelişen istekler artık uygulanmadan önce sorgulanıyor.
3. **Bazı çekirdek kurallar testsiz kalmıştı.** Neden: kural bozma deneyi, veri katmanındaki iki güvencenin (yarı açık aralık sorgusu ve koşullu güncelleme) hızlı testlerde sınanmadığını gösterdi. Çözüm: depo sözleşme testi eklendi; deney yeniden çalıştırılınca 7 kuralın hepsi yakalandı.

Elle denemelerde bulunan hatalar ve düzeltmeleri ayrıca [hata-kayitlari.md](hata-kayitlari.md) dosyasında tutulur. 12.11'de iki hata kaydedildi. H-01'de çıkış günü kuralı çalışıyordu, ama "müsait değil" yanıtı hangi gecenin dolu olduğunu söylemiyordu. H-02'de parola sıfırlama bağlantısı açılırken süresi denetlenmiyordu.

## 7. Eleştirmen grubun geri bildirimleri

**[Ekip dolduracak:** eleştirmen grubun yazdığı geri bildirimler ve ilgili GitHub Issue bağlantıları; kabul edip uyguladığımız öneriler ve düzeltmeyi içeren PR bağlantıları; katılmadığımız öneriler ve gerekçeleri.**]**

## 8. Grup üyelerinin katkıları

| Üye | Aşama 1 (ekip deposu) | Aşama 2 |
| --- | --- | --- |
| Kaan Şahin | 5 commit (sayfa haritası, tasarım belgesi düzeltmeleri, yapay zekâ günlüğü) | Prototipin geliştirilmesi (Claude Code ile, kendi deposunda 9 PR). Ekip deposuna 8 commit: controller'lar, görünümler, örnek veri ve araçlar, belgeler. |
| Utku Camuzcu | 8 commit, depo sahibi ve PR birleştiren | Ekip deposuna 4 commit: proje iskeleti, yardımcı sınıflar, HTTP çekirdeği, Container ve test altyapısı. PR'ları inceleyip birleştirdi. |
| Onur Akbalık | 11 commit (SRS, tasarım belgesi) | Ekip deposuna 4 commit: modeller, istisnalar ve depo arayüzleri, JSON deposu, eşzamanlılık testleri |
| İrem Ablak | 3 commit (belgeler) | Ekip deposuna 4 commit: fiyatlandırma, iptal ve iade, rezervasyon çekirdeği, diğer servisler |

**[Ekip dolduracak:** yükleme PR'larının bağlantıları, her PR'ı kimin incelediği ve her üyenin kendi katmanı için `ai-gunlugu.md` dosyasına eklediği inceleme notu.**]**

## 9. Aşama 3'e (MySQL) geçiş

- **Değişecek sınıflar:** `src/Repository/Json/` altındaki 17 depo sınıfının her biri için aynı arayüzü uygulayan bir MySQL karşılığı yazılır (`MySqlReservationRepository` vb.). Ayrıca:
  - `JsonDatabase` yerine `MySqlDatabase` (PDO, gerçek hazırlıklı ifadeler) gelir.
  - `JsonTransactionManager` yerine `MySqlTransactionManager` gelir.
  - `JsonDataSnapshot` yerine `MySqlDataSnapshot` (yedek) gelir.
  - Bunlara 3NF şemayı kuran SQL migration dosyaları eklenir.
  - Seçim yalnızca `Container::repository()` içinde yapılır.

  Servisler, controller'lar ve görünümler değişmez. Bu sınıflar Kaan Şahin'in çalışma deposunda hazırlandı ve aynı testlerle MySQL 8.4'te sınandı; ekip deposuna final aşamasında eklenecek.
- **Riskler ve önlemler:**
  - **Kilit ve yalıtım:** birim satırı `SELECT … FOR UPDATE` ile kilitlenir. Kilit bekleme süresi 5 saniyeyle sınırlıdır, en çok 2 kez yeniden denenir. Yalıtım düzeyi READ COMMITTED olur.
  - **Para alanları:** `DECIMAL(10,2)` kullanılır; kayan nokta kullanılmaz.
  - **Veri taşıma:** JSON verisi yedekten MySQL'e aynı biçimle yüklenir (`bin/yedek.php`).
  - **Test veritabanı:** ayrı bir `_test` veritabanı kullanılır; testler her seferinde tabloları boşaltır.
- **Mimarinin desteği:** depo arayüzleri ve sözleşme testi. `ReservationRepositoryContractTest`'e bir MySQL veri seti eklendiğinde, yeni deponun eskisiyle aynı davrandığı aynı testlerle kanıtlanır.
