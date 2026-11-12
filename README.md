# EvOtel · Otel / Kiralık Ev Rezervasyon Sistemi

Gümüşhane Üniversitesi · Yazılım Mühendisliği · PHP Programlama dersi projesi (Proje 12, zorluk 4/5).

| Rol | Üye |
| --- | --- |
| Proje Yöneticisi | Kaan Şahin (2407231037) |
| Mimar | Utku Camuzcu (2407231044) |
| Veritabanı ve Test Sorumlusu | İrem Ablak (2407231080) |
| Arayüz ve Dokümantasyon Sorumlusu | Onur Akbalık (2407231014) |

Tesis sahibi oda veya ev ilanı açar ve fiyat kurallarını tanımlar. Misafir tarih aralığına göre müsait yerleri arar, rezervasyon yapar ve iptal eder. Yönetici doluluk ve gelir raporunu görür.

**Çekirdek problem:** tarih aralığı çakışması (10'unda çıkan misafirin yerine aynı gün başka misafir girebilir), sezonluk dinamik fiyat, iptal politikası ve iade hesabı, çift rezervasyonun önlenmesi.

## Aşama durumu

| Aşama | İçerik | Durum |
| --- | --- | --- |
| 1. Projelendirme | [SRS](docs/srs.md), [tasarım belgesi](docs/tasarim.md) | Tamamlandı |
| 2. Veritabanısız geliştirme (vize) | Tüm işlevler; veriler `storage/data` altındaki **JSON dosyalarında** | **Etkin depo** (varsayılan) |
| 3. Veritabanı (final) | Aynı Repository arayüzlerinin PDO + MySQL uygulaması, SQL migration'lar, seed, JOIN'li rapor, transaction + satır kilidi | Kod hazır ve testli; `EVOTEL_STORAGE=mysql` ile çalışır |

GitHub'da her SRS gereksinimi bir issue'dur; aşamalar milestone olarak, iş akışı Projects panosunda (Yapılacak / Yapılıyor / Bitti) izlenir: [milestone'lar](https://github.com/Kayazilim/ev-otel-rezervasyon/milestones) · [issue'lar](https://github.com/Kayazilim/ev-otel-rezervasyon/issues) · [Projects panosu](https://github.com/users/Kayazilim/projects/1). Pano private'tır; ekip üyeleri erişimi depo sahibinden ister.

## Kurulum (JSON deposu, varsayılan)

Gerekenler: PHP 8.2 veya üzeri (`fileinfo`, `mbstring`, `json` eklentileri), Composer ve Git. Veritabanı gerekmez. Komutlar Windows (PowerShell veya Git Bash), macOS ve Linux'ta aynıdır.

1. Depoyu indirin ve klasörüne girin:

```bash
git clone https://github.com/Kayazilim/ev-otel-rezervasyon.git
cd ev-otel-rezervasyon
```

2. Bağımlılıkları kurun (yalnızca test ve kod kalitesi araçları; uygulama çalışırken framework kullanılmaz):

```bash
composer install
```

3. Örnek veriyi yükleyin (`storage/data` altındaki JSON dosyaları bugünün tarihine göre üretilir):

```bash
composer seed
```

4. Uygulamayı başlatın:

```bash
composer serve
```

5. Tarayıcıda `http://localhost:8000` adresini açın ve aşağıdaki demo hesaplarından biriyle giriş yapın. Testleri çalıştırmak için: `composer test` (veya doğrudan `vendor/bin/phpunit`).

`composer seed` örnek veriyi bugünün tarihine göre yeniden üretir; veri klasörü boşsa ilk istekte de otomatik yüklenir. Sunumdan önce `composer seed` çalıştırmanız önerilir.

**WAMP ile:** Proje `C:\wamp64\www\` altındaysa Apache'de `rewrite_module` açıkken `http://localhost/<klasör-adı>/` adresi kullanılabilir. Kökteki `.htaccess` yalnızca `public/` klasörünü web'e açar; `storage/`, `src/`, `config/`, `database/` ve `vendor/` klasörlerine doğrudan erişilemez.

### MySQL ile çalıştırma (Aşama 3, isteğe bağlı)

Gerekenler: MySQL 8 (WAMP'taki MySQL olur) ve `pdo_mysql` eklentisi. Bağlantı ayarları ortam değişkenleriyle verilir (varsayılanlar parantez içinde): `EVOTEL_DB_HOST` (127.0.0.1), `EVOTEL_DB_PORT` (3306), `EVOTEL_DB_NAME` (evotel), `EVOTEL_DB_USER` (root), `EVOTEL_DB_PASSWORD` (boş).

```bash
php bin/migrate.php --fresh --seed
```

```bash
EVOTEL_STORAGE=mysql composer serve
```

PowerShell'de: `$env:EVOTEL_STORAGE='mysql'; composer serve`. WAMP/Apache'de değişken sanal konak yapılandırmasında `SetEnv EVOTEL_STORAGE mysql` ile verilebilir. `bin/migrate.php` veritabanını yoksa oluşturur ve `database/migrations/*.sql` dosyalarını sırayla bir kez uygular. MySQL çalışmıyorsa veya tablolar kurulmamışsa sayfada "Veri deposuna ulaşılamıyor" ve yapılacak işlem yazar.

JSON'daki veriyi MySQL'e taşımak için: `php bin/yedek.php` (JSON) ardından `EVOTEL_STORAGE=mysql php bin/yedek.php --geri-yukle=<dosya> --evet` ([yedekleme.md](docs/yedekleme.md)).

### Demo hesapları

Tüm demo hesaplarının parolası: `Demo1234!` (yalnızca yerel demo verisi, `src/Seed/DemoSeeder.php`).

| Rol | E-posta | Ne denenebilir |
| --- | --- | --- |
| Yönetici | `yonetici@evotel.test` | Kullanıcı kısıtlama, ilan kaldırma (gelecek rezervasyonlar iptal + %100 iade), şikâyet, yorum denetimi, rapor, denetim kaydı, tutarlılık denetimi |
| İlan sahibi | `ayse@evotel.test` | Karadeniz Otel, Uzungöl Dağ Evi (onaylı mod, bekleyen talep var), Gümüşhane Şehir Oteli |
| İlan sahibi | `mehmet@evotel.test`, `zeynep@evotel.test` | Diğer ilanlar |
| Müşteri | `ali@evotel.test`, `can@evotel.test`, `deniz@evotel.test`, `burak@evotel.test`, `selin@evotel.test` | Arama, rezervasyon, simüle ödeme, iptal ve iade, yorum, favori, şikâyet |
| Müşteri (yorumcular) | `elif@`, `emre@`, `merve@`, `okan@`, `gizem@`, `kerem@evotel.test` | Geçmiş konaklamaları ve yorumları olan hesaplar |

Örnek veri: 15 kullanıcı, 8 ilan, 16 oda/birim, 6 oda tipi, 54 rezervasyon (tamamlanan, devam eden, bekleyen, iptal, ret, süresi dolan), 43 yorum (her ilanda 4–7, yalnızca tamamlanmış konaklamalardan). Ayrıca takvim istisnaları, sezon/hafta sonu/uzun konaklama kuralları, KDV ve temizlik ücreti var.

**Fotoğraflar:** ders kuralı gereği resim, ikon veya çizim dosyası hazırlanmaz; örnek veride ilanlar emoji yer tutucuyla görünür. Gerçek fotoğraflar ilan sahibi panelinden (İlanlarım → Fotoğraflar) yüklenir (FR-46). Örnek veri fotoğraf üretmez; çizim üreten kod depoda yoktur.

### Şikâyet akışı

Kullanıcı bir ilan veya rezervasyon için şikâyet gönderince (FR-27):

1. Şikâyet **Yeni** durumuyla yönetim ekibinin **Yönetim → Şikâyetler** kutusuna düşer ve her aktif yöneticiye bildirim gider.
2. Kullanıcı **Şikâyetlerim** (`/sikayetlerim`) sayfasına yönlendirilir; şikâyet numarasını ve durumunu orada izler.
3. Yönetici durumu (İnceleniyor, Çözüldü, Reddedildi) veya notu güncelleyince şikâyet sahibine bildirim gider.

### Parola sıfırlama ve e-posta

Parola sıfırlama bağlantısı (FR-04) 60 dakika geçerli ve tek kullanımlıktır. Bağlantı açıldığında süresi ve kullanılıp kullanılmadığı denetlenir; geçersizse form yerine nedeni ve "Yeni bağlantı iste" düğmesi gösterilir, geçerliyse formun üstünde geçerlilik sonu yazar. Güvenlik nedeniyle kayıtlı olmayan bir adres girildiğinde de aynı mesaj gösterilir; böylece hangi e-postaların kayıtlı olduğu dışarıdan öğrenilemez. Kendi adresinizle denemek için önce o adresle kayıt olun (`composer seed` verileri sıfırladığı için o kayıt da silinir).

| Kip | Ne olur | Nasıl açılır |
| --- | --- | --- |
| Simüle (varsayılan) | E-posta gerçek adrese gitmez. İleti `storage/mail/` klasörüne yazılır ve **Posta kutusu** sayfasında görünür: `http://localhost:8000/posta-kutusu` (bağlantısı giriş sayfasında ve alt bilgide). Sayfa yalnızca bu bilgisayardan açılır. | Ek ayar gerekmez. Bir sunucuya kurulursa `EVOTEL_POSTA_KUTUSU=0` ile kapatın. |
| Gerçek e-posta (SMTP) | İleti alıcının gelen kutusuna gider (ör. Gmail). | `config/yerel.ornek.php` dosyasını `config/yerel.php` adıyla kopyalayıp doldurun. Bu dosya git'e girmez. |

Gmail'de normal parolanız çalışmaz, uygulama şifresi gerekir: Google Hesabı → Güvenlik → 2 Adımlı Doğrulama'yı açın → **Uygulama şifreleri** bölümünden 16 karakterlik bir şifre oluşturup `config/yerel.php` içindeki `password` alanına yazın. Sonra önce bağlantıyı deneyin (ileti göndermez), ardından kendinize bir deneme iletisi gönderin:

```bash
php bin/eposta-dene.php --kontrol
```

```bash
php bin/eposta-dene.php adiniz@gmail.com
```

Gönderim başarısız olursa kullanıcıya "gönderildi" denmez: 503 hatası gösterilir ve ayrıntı `storage/logs/app.log` dosyasına yazılır.

### Zamanlanmış görev ve günlük yedek

`bin/gorev.php` süresi dolan bekletmeleri **Süresi Doldu**, çıkış günü gelen onaylı rezervasyonları **Tamamlandı** yapar ve o gün yedek alınmamışsa **günlük yedeği** alır (NFR-09, son 7 yedek saklanır). Dakikada bir çalıştırılır:

```bash
php bin/gorev.php
```

Windows Görev Zamanlayıcı ile dakikada bir çalıştırmak için:

```bash
schtasks /create /sc minute /mo 1 /tn "EvOtel gorev" /tr "php C:\wamp64\www\ev-otel-rezervasyon-kaan\bin\gorev.php"
```

Doğruluk bu göreve bağlı değildir: çakışma kontrolü süresi dolmuş bekletmeleri zaten temizler (lazy expiry). Yönetici panelindeki **Şimdi çalıştır** düğmesi demo içindir. Yedekleri listelemek ve geri yüklemek: `php bin/yedek.php --liste`, `php bin/yedek.php --geri-yukle=<dosya> --evet` ([yedekleme.md](docs/yedekleme.md), geri yükleme denemesi kaydı dahil).

### JSON uç noktaları (AJAX ve kısmi yenileme)

- `GET /api/musaitlik?birim=2&giris=2026-11-20&cikis=2026-11-23&kisi=2` → müsaitlik, gecelik fiyat dökümü, indirim, vergi/ek ücret, toplam, iptal koşulu ve doluysa en çok 3 alternatif tarih. İlan sayfasındaki "Müsaitlik ve fiyatı gör" düğmesi bu ucu sayfa yenilenmeden çağırır.
- `GET /api/rezervasyon/{no}/durum` → rezervasyon durumu ve kalan bekletme süresi. Rezervasyon sayfası bu ucu 5 saniyede bir çağırıp yalnızca durum bölümünü günceller (kısmi yenileme); ilan sahibi onaylayınca veya süre dolunca sayfa yenilenmeden değişir.

Hatalar da JSON döner (`{"basarili":false,"hata":"..."}`; 400, 401, 403, 404, 422, 503). Sunucu hata verirse ya da ulaşılamazsa sayfa ilgili bölümün başında anlaşılır bir uyarı gösterir, son geçerli bilgi ekranda kalır. Ayrıntı: [tasarim.md 12.3 ve 12.5](docs/tasarim.md).

## Testler ve kod kalitesi

```bash
composer test
```

| Komut | Ne yapar |
| --- | --- |
| `composer test` | Tüm PHPUnit testleri, JSON deposunda (244 test; 50 paralel süreçli eşzamanlılık testleri dahil) |
| `composer test:mysql` | Aynı testler MySQL'de (`evotel_test` veritabanı; `EVOTEL_TEST_DB_HOST/PORT/USER/PASSWORD`). Testler her seferinde tabloları boşalttığı için adı `_test` ile bitmeyen veritabanında çalışmaz. |
| `composer test:hizli` | Eşzamanlılık testleri hariç |
| `composer kapsam` | Satır kapsamı (Xdebug gerekir; HTML rapor `coverage/`) |
| `composer izlenebilirlik` | Testleri koşar, kapsamı ölçer ve [izlenebilirlik tablosunu](docs/izlenebilirlik.md) (kullanıcı hikâyesi / gereksinim → test) üretir |
| `php bin/kural-boz.php` | Kural bozma deneyi: önemli kuralları geçici olarak bozar, kırılan testleri [kural-bozma-deneyi.md](docs/kural-bozma-deneyi.md) dosyasına yazar, dosyaları geri yükler. Tek deney: `--deney=hafta-sonu` |
| `composer analiz` | PHPStan statik analiz (seviye 6; `src`, `bin`, `config`, `tests`) |
| `composer stil` / `composer stil:duzelt` | PSR-12 kod stili denetimi / otomatik düzeltme |

Son çalıştırma (12.11.2026): JSON deposunda 244 test / 1788 doğrulama (MySQL'e özel 7 test atlanır), MySQL 8.4'te 244 test / 1842 doğrulama, hepsi geçti. Kural bozma deneyinde 7 kuralın 7'sinde ilgili testler kırıldı. PHPStan ve PHPCS hatasız. NFR-16 satır kapsamı: BookingConflictService %96,4 · ReservationService %99,7 · AvailabilityService %98,4 · PricingService %96,8.

Bir gereksinimin testlerini çalıştırmak için: `vendor/bin/phpunit --group FR-18` (her test doğruladığı gereksinim kodunu `#[Group]` olarak taşır).

## Aşama 2 ödevi: istenenler ve karşılandığı yer

| Ödev maddesi | Karşılandığı yer |
| --- | --- |
| PHP 8.2+, Composer PSR-4, namespace, PSR-12, framework yok | `composer.json`, `phpcs.xml` (`composer stil`), `ArchitectureTest::testNoFrameworkIsUsed` |
| Tek giriş noktası; router, controller, service, repository, view ayrımı | `public/index.php` → `Kernel` → `Router` → `src/Controller` → `src/Service` → `src/Repository` → `views/` ([tasarim.md 1](docs/tasarim.md)) |
| Form hatasında anlaşılır mesaj ve girilen geçerli değerlerin korunması | Alan bazlı mesajlar; tek formlu sayfalar 422 ile girilen değerlerle, satır içi formlar PRG bozulmadan (`Controller::backWithInput`) — `WebTest::testFormErrorsKeepTheValuesTheUserTyped`, `testInlineFormErrorRedirectsBackKeepingTheTypedValue`, `testCancelFormKeepsTheReasonOnError` |
| POST-Redirect-GET ve flash mesajlar | Başarılı her POST yönlendirmeyle biter; rezervasyonda ayrıca tek kullanımlık anahtar (FR-35) — [tasarim.md 12.6](docs/tasarim.md) |
| Kayıt, giriş, çıkış; `password_hash`; en az iki rol ve sahiplik kontrolü | 3 rol; her rotada erişim kuralı (`config/routes.php`), sahiplik servislerde; `WebTest::testRolesAreEnforcedOnTheServer` |
| Başlangıç verisi betiği | `bin/seed.php` (`composer seed`): 15 kullanıcı, 8 ilan, 16 birim, 54 rezervasyon, 43 yorum |
| JSON dosyalarında veri; her depo için arayüz; servisler arayüze bağımlı | `storage/data/*.json`; `src/Repository/*Interface.php` ve `src/Repository/Json/`; `ArchitectureTest` |
| Okuma/yazma hatalarının ele alınması; `flock`; geçici dosyaya yazıp güvenli değiştirme | `JsonDatabase` (dışlayıcı kilit, geçici dosya + yeniden adlandırma, bozuk dosyada anlaşılır hata ve günlük kaydı) |
| Dosya yüklemede tür ve boyut kontrolü, rastgele dosya adı | `PhotoService` (gerçek MIME, en çok 2 MB, rastgele ad, web kökü dışında saklama) |
| Çekirdek problem testleri; hikâye/gereksinim eşlemesi; geçersiz girdi ve sınır durumları | [docs/izlenebilirlik.md](docs/izlenebilirlik.md) (US-01…US-04 ve her FR/NFR/BR/AC kodu → test) |
| Tekrarlanabilir testler (saat ve rastgelelik denetlenebilir) | `FrozenClock` (sabit saat); rastgele değerler testlerde sonuca etki etmez |
| Mümkün olduğunda bellekte çalışan sahte depo | `tests/Support/InMemory/` (çakışma ve fiyat birim testleri); `ReservationRepositoryContractTest` sahte deponun gerçeğiyle aynı davrandığını gösterir |
| Kural bozulunca ilgili testin kırıldığının gösterilmesi | `php bin/kural-boz.php` → [docs/kural-bozma-deneyi.md](docs/kural-bozma-deneyi.md) |
| En az 10 test; `vendor/bin/phpunit` ile çalışır | Bu belgede "Testler ve kod kalitesi" bölümü |
| Sunucu tarafı doğrulama, URL'deki kimlikle başkasının verisine erişememe, iç ayrıntı göstermeme, `htmlspecialchars` | Servislerde doğrulama ve sahiplik; hata ayrıntısı yalnızca `storage/logs/app.log`; `e()` |
| AJAX'ta beklenmeyen HTTP yanıtında uygun mesaj | `views/layout.php` (`bolumIstegi`, `jsonIstegi`), fiyat önizleme, favori düğmesi — [tasarim.md 12.3](docs/tasarim.md) |
| Kısmi yenileme: JSON yanıtla ekran parçasını güncelleme, hata mesajı | Rezervasyon durumu: `GET /api/rezervasyon/{no}/durum`, 5 saniyede bir; süre kararı sunucuda |
| `docs/ai-gunlugu.md` (yapay zekânın yanıldığı örnek dahil) | [docs/ai-gunlugu.md](docs/ai-gunlugu.md), "Yapay zekânın yanıldığı… durumlar" bölümü |
| `docs/surec-raporu.md` | [docs/surec-raporu.md](docs/surec-raporu.md) |
| İnceleme ve denemelerde bulunan sorunların somut yazılması, düzeltmelerin raporlanması | [docs/hata-kayitlari.md](docs/hata-kayitlari.md) (belirti, yeniden üretme, kök neden, düzeltme, doğrulayan test) |
| Ayrı dallar, PR ile birleştirme, en az bir arkadaştan inceleme, herkesin kendi hesabıyla katkısı | Her iş ayrı dalda ve PR ile birleştirildi; ekip deposuna yükleme her üyenin kendi hesabıyla ve incelemeli PR ile yapılır |

## Ders şartları ve standart tablosu

| Standart | Karşılandığı yer |
| --- | --- |
| **Dil ve yapı:** PHP 8.2+, Composer PSR-4, namespace, PSR-12 | `composer.json` (`App\` → `src/`, `Tests\` → `tests/`), `phpcs.xml`, `.gitattributes` (LF satır sonu) |
| **Mimari:** router, controller, servis, repository, görünüm | `config/routes.php` + `src/Router.php`, `src/Controller`, `src/Service`, `src/Repository`, `views/`; katman kuralları `tests/Unit/ArchitectureTest.php` ile denetlenir |
| **Framework yok** | Çalışma zamanı bağımlılığı yalnızca PHP ve eklentileri (`ArchitectureTest::testNoFrameworkIsUsed`) |
| **Tasarım:** en az 2 desen + gerekçe | Repository, Strategy (fiyat ve iade), State (rezervasyon durumu) — [tasarim.md 5](docs/tasarim.md) |
| **Veri erişimi:** Repository arayüzü; Aşama 2 JSON, Aşama 3 PDO + MySQL | `src/Repository/*Interface.php`, `src/Repository/Json/`, `src/Repository/MySql/`; seçim tek yerde `Container::repository()` |
| **Veritabanı:** 3NF, SQL migration, seed, JOIN'li rapor | `database/migrations/001–004.sql`, `bin/migrate.php --seed`, `MySqlReservationRepository::reportRows()` ve `database/sorgular/doluluk-gelir-raporu.sql` |
| **Eşzamanlılık:** transaction ve satır kilidi | MySQL: `BEGIN … SELECT … FOR UPDATE … COMMIT`, kilit bekleme 5 sn, 2 yeniden deneme; JSON: dosya kilidi; koşullu güncelleme + `UNIQUE(birim, gece)`; 50 süreçli testler |
| **Güvenlik:** hazırlıklı ifadeler, `password_hash`, XSS kaçışı, CSRF, `session_regenerate_id`, her işlemde yetki | `MySqlDatabase` (`ATTR_EMULATE_PREPARES=false`), `AuthService`, `e()`, `Kernel` (CSRF), `Auth::login` ve parola değişiminde oturum kimliği yenileme, rota yetkisi + servislerde sahiplik denetimi |
| **Etkileşim:** JSON döndüren AJAX ucu | `GET /api/musaitlik` (`ApiController`), `GET /api/rezervasyon/{no}/durum` (JSON ile kısmi yenileme) + 3 kendiliğinden yenilenen HTML bölümü (`/parca/...`); beklenmeyen yanıtta kullanıcıya uyarı |
| **Test:** çekirdek için PHPUnit, izlenebilirlik tablosu | `tests/` (244 test), [docs/izlenebilirlik.md](docs/izlenebilirlik.md), [kural bozma deneyi](docs/kural-bozma-deneyi.md) |
| **Kod kalitesi:** PHPStan, hatasız çalışma, anlaşılır hata mesajları | `composer analiz` (seviye 6, testler dahil), alan bazlı doğrulama mesajları, JSON uçta JSON hata, depo erişilemezse 503 ve açıklama |
| **Süreç:** GitHub deposu, anlamlı commit geçmişi, Projects backlog, README | Bu depo; her SRS gereksinimi bir issue, 3 aşama milestone'u, [GitHub Projects panosu](https://github.com/users/Kayazilim/projects/1) (Yapılacak / Yapılıyor / Bitti, SRS önceliği, aşama görünümleri; issue kapanınca veya PR birleşince kart kendiliğinden Bitti'ye geçer), PR ile birleştirme |

Proje kartı ve ortak şartlar:

| Şart | Karşılandığı yer |
| --- | --- |
| Kayıt, giriş, çıkış, en az 2 rol, her sayfada yetki | 3 rol; erişim kuralı her rotada (`config/routes.php`), sahiplik serviste |
| Kritik işlemler POST + CSRF, dosya yükleme kontrolü | `Kernel` (CSRF), `PhotoService` (gerçek MIME, 2 MB, sahte uzantı, sahiplik; oda bazında fotoğraf) |
| Arama/filtre ve rapor ekranı | `/ara` (konum, tarih, kişi, tür, fiyat, puan, oda tipi, olanak, sıralama), `/panel/rapor`, `/yonetim/rapor` |
| HTML, CSS, Bootstrap, resim/ikon dosyası yok, telefonda kullanılabilir | Bootstrap 5 ve resmi minimalist tema (`public/assets/app.css`; renk, yazı ve boşluk değerleri Lovable'da hazırlanan tasarım prototipinden alındı, prototip kodu kullanılmadı). Resim, ikon veya çizim dosyası hazırlanmaz: simgeler Unicode ve emoji, takvim CSS grid; ilan fotoğrafları yalnızca ilan sahibinin yüklediği fotoğraflardır. 375 px genişlikte yatay taşma yok. |
| Sayfanın bir bölümünü kendiliğinden yenileme | Rezervasyon durumu (JSON), müsaitlik takvimi, gelen rezervasyonlar, bildirim sayısı (HTML parça) ([tasarim.md 12.3](docs/tasarim.md)) |
| Koşullu güncelleme ve etkilenen satır kontrolü | `updateStatusIf` (`UPDATE … WHERE id = ? AND status = ?`, `rowCount() === 1`) |
| Örnek veri (en az 10 oda/ev, 3 oda tipi) | 16 birim, 6 oda tipi |
| Kart: tarih bazlı arama, aylık takvim, gecelik döküm, yaşam döngüsü, doluluk ve gelir raporu, resim yükleme | Hepsi uygulandı |
| Yapay zekâ kullanım günlüğü | [docs/ai-gunlugu.md](docs/ai-gunlugu.md) |

**Bonuslar:** PSR-12 ve PHPStan, ek tasarım desenleri (Strategy ×2, State), şifre değiştirme, oturum zaman aşımı (30 dk; kendiliğinden yenilenen bölümler etkinlik sayılmaz), sayfalama, CSV dışa aktarma, yazarken sonuç veren canlı arama, sayfa yenilenmeden gönderilen formlar (favori, fiyat önizleme), transaction + satır kilidi, günlük yedek ve geri yükleme.

## Klasör yapısı

```
public/      ön denetleyici (index.php) ve CSS
config/      app.php (süre, limit, oran, depo ve e-posta ayarları), routes.php (adres → controller + yetki),
             yerel.ornek.php (yerel.php örneği: SMTP gibi git'e girmeyen ayarlar)
src/         Core, Controller, Service, Repository (Json, MySql), Model, Support, Exception, Seed
views/       Bootstrap görünümleri (her çıktı e() ile kaçışlanır)
database/    migrations/ (MySQL şeması, 3NF), sorgular/ (JOIN'li rapor sorgusu)
bin/         seed.php, gorev.php, migrate.php, yedek.php, izlenebilirlik.php, kural-boz.php, eposta-dene.php
storage/     data (JSON tablolar), uploads, logs, mail (simüle e-posta), backups – web'den erişilemez
tests/       Unit, Integration, Concurrency, Support/InMemory (bellekteki sahte depolar) (+ bin/rezervasyon-isci.php paralel süreç, bin/sahte-smtp.php)
docs/        srs.md, tasarim.md, izlenebilirlik.md, kural-bozma-deneyi.md, surec-raporu.md, hata-kayitlari.md, yedekleme.md, ai-gunlugu.md, arayüz tasarımları
```

## Belgeler

- [Gereksinimler (SRS v2.3)](docs/srs.md) · [kaynak .docx](docs/OTEL_KIRALIK_EV_REZERVASYON_SISTEMI_SRS_v2_3.docx)
- [Tasarım belgesi](docs/tasarim.md) (bölüm 13: Aşama 3 MySQL katmanı)
- [İzlenebilirlik: kullanıcı hikâyesi ve gereksinim → test](docs/izlenebilirlik.md)
- [Kural bozma deneyi](docs/kural-bozma-deneyi.md)
- [Süreç raporu](docs/surec-raporu.md)
- [Hata kayıtları: bulunan hatalar ve düzeltmeleri](docs/hata-kayitlari.md)
- [Yedekleme ve geri yükleme](docs/yedekleme.md)
- [Yapay zekâ kullanım günlüğü](docs/ai-gunlugu.md)
