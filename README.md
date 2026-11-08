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

GitHub'da her SRS gereksinimi bir issue'dur ve aşamalar milestone olarak izlenir: [milestone'lar](https://github.com/Kayazilim/ev-otel-rezervasyon/milestones) · [issue'lar](https://github.com/Kayazilim/ev-otel-rezervasyon/issues).

## Kurulum (JSON deposu, varsayılan)

Gerekenler: PHP 8.2 veya üzeri (`fileinfo`, `mbstring`, `json` eklentileri) ve Composer. Veritabanı gerekmez.

```bash
composer install
composer seed
composer serve
```

Tarayıcıda `http://localhost:8000` adresini açın. `composer seed` örnek veriyi bugünün tarihine göre yeniden üretir; veri klasörü boşsa ilk istekte de otomatik yüklenir. Sunumdan önce `composer seed` çalıştırmanız önerilir.

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

Örnek veri: 9 kullanıcı, 8 ilan, 16 oda/birim, 6 oda tipi, 18 rezervasyon (tamamlanan, devam eden, bekleyen, iptal, ret, süresi dolan). Ayrıca yorumlar, takvim istisnaları, sezon/hafta sonu/uzun konaklama kuralları, KDV ve temizlik ücreti var.

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

### JSON uç noktası (AJAX)

`GET /api/musaitlik?birim=2&giris=2026-11-20&cikis=2026-11-23&kisi=2` → müsaitlik, gecelik fiyat dökümü, indirim, vergi/ek ücret, toplam, iptal koşulu ve doluysa en çok 3 alternatif tarih (JSON). Hatalar da JSON döner (422, 404, 401, 503). İlan sayfasındaki "Müsaitlik ve fiyatı gör" düğmesi bu ucu sayfa yenilenmeden çağırır. Ayrıntı: [tasarim.md 12.5](docs/tasarim.md).

## Testler ve kod kalitesi

```bash
composer test
```

| Komut | Ne yapar |
| --- | --- |
| `composer test` | Tüm PHPUnit testleri, JSON deposunda (193 test; 50 paralel süreçli eşzamanlılık testleri dahil) |
| `composer test:mysql` | Aynı testler MySQL'de (`evotel_test` veritabanı; `EVOTEL_TEST_DB_HOST/PORT/USER/PASSWORD`). Testler her seferinde tabloları boşalttığı için adı `_test` ile bitmeyen veritabanında çalışmaz. |
| `composer test:hizli` | Eşzamanlılık testleri hariç |
| `composer kapsam` | Satır kapsamı (Xdebug gerekir; HTML rapor `coverage/`) |
| `composer izlenebilirlik` | Testleri koşar, kapsamı ölçer ve [gereksinim–test izlenebilirlik tablosunu](docs/test-izlenebilirlik.md) üretir |
| `composer analiz` | PHPStan statik analiz (seviye 6; `src`, `bin`, `config`, `tests`) |
| `composer stil` / `composer stil:duzelt` | PSR-12 kod stili denetimi / otomatik düzeltme |

Son çalıştırma (08.11.2026): JSON deposunda 193 test / 1277 doğrulama (MySQL'e özel 7 test atlanır), MySQL 8.4'te 193 test / 1331 doğrulama, hepsi geçti. PHPStan ve PHPCS hatasız. NFR-16 satır kapsamı: BookingConflictService %96,1 · ReservationService %99,7 · AvailabilityService %98,4 · PricingService %96,8.

Bir gereksinimin testlerini çalıştırmak için: `vendor/bin/phpunit --group FR-18` (her test doğruladığı gereksinim kodunu `#[Group]` olarak taşır).

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
| **Etkileşim:** JSON döndüren AJAX ucu | `GET /api/musaitlik` (`ApiController`) + 4 kendiliğinden yenilenen bölüm (`/parca/...`) |
| **Test:** çekirdek için PHPUnit, izlenebilirlik tablosu | `tests/` (193 test), [docs/test-izlenebilirlik.md](docs/test-izlenebilirlik.md) |
| **Kod kalitesi:** PHPStan, hatasız çalışma, anlaşılır hata mesajları | `composer analiz` (seviye 6, testler dahil), alan bazlı doğrulama mesajları, JSON uçta JSON hata, depo erişilemezse 503 ve açıklama |
| **Süreç:** GitHub deposu, anlamlı commit geçmişi, Projects backlog, README | Bu depo; her SRS gereksinimi bir issue, 3 aşama milestone'u; GitHub Projects panosu için `gh auth refresh -s project` gerekiyor (issue #71) |

Proje kartı ve ortak şartlar:

| Şart | Karşılandığı yer |
| --- | --- |
| Kayıt, giriş, çıkış, en az 2 rol, her sayfada yetki | 3 rol; erişim kuralı her rotada (`config/routes.php`), sahiplik serviste |
| Kritik işlemler POST + CSRF, dosya yükleme kontrolü | `Kernel` (CSRF), `PhotoService` (gerçek MIME, 2 MB, sahte uzantı, sahiplik; oda bazında fotoğraf) |
| Arama/filtre ve rapor ekranı | `/ara` (konum, tarih, kişi, tür, fiyat, puan, oda tipi, olanak, sıralama), `/panel/rapor`, `/yonetim/rapor` |
| HTML, CSS, Bootstrap, resim/ikon dosyası yok, telefonda kullanılabilir | Bootstrap 5; görseller emoji ve CSS grid (takvim) |
| Sayfanın bir bölümünü kendiliğinden yenileme | Müsaitlik takvimi, gelen rezervasyonlar, rezervasyon durumu, bildirim sayısı ([tasarim.md 12.3](docs/tasarim.md)) |
| Koşullu güncelleme ve etkilenen satır kontrolü | `updateStatusIf` (`UPDATE … WHERE id = ? AND status = ?`, `rowCount() === 1`) |
| Örnek veri (en az 10 oda/ev, 3 oda tipi) | 16 birim, 6 oda tipi |
| Kart: tarih bazlı arama, aylık takvim, gecelik döküm, yaşam döngüsü, doluluk ve gelir raporu, resim yükleme | Hepsi uygulandı |
| Yapay zekâ kullanım günlüğü | [docs/ai-gunlugu.md](docs/ai-gunlugu.md) |

**Bonuslar:** PSR-12 ve PHPStan, ek tasarım desenleri (Strategy ×2, State), şifre değiştirme, oturum zaman aşımı (30 dk), sayfalama, CSV dışa aktarma, yazarken sonuç veren canlı arama, sayfa yenilenmeden gönderilen formlar (favori, fiyat önizleme), transaction + satır kilidi, günlük yedek ve geri yükleme.

## Klasör yapısı

```
public/      ön denetleyici (index.php) ve CSS
config/      app.php (süre, limit, oran, depo ayarları), routes.php (adres → controller + yetki)
src/         Core, Controller, Service, Repository (Json, MySql), Model, Support, Exception, Seed
views/       Bootstrap görünümleri (her çıktı e() ile kaçışlanır)
database/    migrations/ (MySQL şeması, 3NF), sorgular/ (JOIN'li rapor sorgusu)
bin/         seed.php, gorev.php, migrate.php, yedek.php, izlenebilirlik.php
storage/     data (JSON tablolar), uploads, logs, mail (simüle e-posta), backups – web'den erişilemez
tests/       Unit, Integration, Concurrency (+ bin/rezervasyon-isci.php paralel süreç)
docs/        srs.md, tasarim.md, test-izlenebilirlik.md, yedekleme.md, ai-gunlugu.md, arayüz tasarımları
```

## Belgeler

- [Gereksinimler (SRS v2.3)](docs/srs.md) · [kaynak .docx](docs/OTEL_KIRALIK_EV_REZERVASYON_SISTEMI_SRS_v2_3.docx)
- [Tasarım belgesi](docs/tasarim.md) (bölüm 13: Aşama 3 MySQL katmanı)
- [Gereksinim–test izlenebilirlik tablosu](docs/test-izlenebilirlik.md)
- [Yedekleme ve geri yükleme](docs/yedekleme.md)
- [Yapay zekâ kullanım günlüğü](docs/ai-gunlugu.md)
