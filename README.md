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
| 1. Projelendirme | [SRS](docs/srs.md), [tasarım belgesi](docs/tasarim.md), GitHub Projects panosu | Tamamlandı |
| 2. Veritabanısız geliştirme | Verileri JSON dosyalarında tutan çalışan prototip ve birim testleri | **Bu sürüm** |
| 3. Veritabanı ve tamamlama | MySQL, SQL şema/örnek veri, test raporu | Sonraki aşama |

## Kurulum

Gerekenler: PHP 8.2 veya üzeri (`fileinfo`, `mbstring`, `json` eklentileri) ve Composer.

```bash
composer install
composer seed
composer serve
```

Tarayıcıda `http://localhost:8000` adresini açın. `composer seed` örnek veriyi bugünün tarihine göre yeniden üretir; veri klasörü boşsa ilk istekte de otomatik yüklenir. Sunumdan önce `composer seed` çalıştırmanız önerilir.

**WAMP ile:** Proje `C:\wamp64\www\` altındaysa Apache'de `rewrite_module` açıkken `http://localhost/<klasör-adı>/` adresi kullanılabilir. Kökteki `.htaccess` yalnızca `public/` klasörünü web'e açar; `storage/`, `src/`, `config/` ve `vendor/` klasörlerine doğrudan erişilemez.

### Demo hesapları

Tüm demo hesaplarının parolası: `Demo1234!` (yalnızca yerel demo verisi, `src/Seed/DemoSeeder.php`).

| Rol | E-posta | Ne denenebilir |
| --- | --- | --- |
| Yönetici | `yonetici@evotel.test` | Kullanıcı kısıtlama, ilan kaldırma (gelecek rezervasyonlar iptal + %100 iade), şikâyet, yorum denetimi, rapor, denetim kaydı, tutarlılık denetimi |
| İlan sahibi | `ayse@evotel.test` | Karadeniz Otel, Uzungöl Dağ Evi (onaylı mod, bekleyen talep var), Gümüşhane Şehir Oteli |
| İlan sahibi | `mehmet@evotel.test`, `zeynep@evotel.test` | Diğer ilanlar |
| Müşteri | `ali@evotel.test`, `can@evotel.test`, `deniz@evotel.test`, `burak@evotel.test`, `selin@evotel.test` | Arama, rezervasyon, simüle ödeme, iptal ve iade, yorum, favori, şikâyet |

Örnek veri: 9 kullanıcı, 8 ilan, 16 oda/birim, 6 oda tipi, 18 rezervasyon (tamamlanan, devam eden, bekleyen, iptal, ret, süresi dolan). Ayrıca yorumlar, takvim istisnaları, sezon/hafta sonu/uzun konaklama kuralları, KDV ve temizlik ücreti var.

### Zamanlanmış görev

Süresi dolan bekletmeleri **Süresi Doldu**, çıkış günü gelen onaylı rezervasyonları **Tamamlandı** yapar. Dakikada bir çalıştırılır:

```bash
php bin/gorev.php
```

Windows Görev Zamanlayıcı ile dakikada bir çalıştırmak için:

```bash
schtasks /create /sc minute /mo 1 /tn "EvOtel gorev" /tr "php C:\wamp64\www\ev-otel-rezervasyon-kaan\bin\gorev.php"
```

Doğruluk bu göreve bağlı değildir: çakışma kontrolü süresi dolmuş bekletmeleri zaten temizler (lazy expiry). Yönetici panelindeki **Şimdi çalıştır** düğmesi demo içindir.

## Testler ve kod kalitesi

```bash
composer test
```

| Komut | Ne yapar |
| --- | --- |
| `composer test` | Tüm PHPUnit testleri (137 test, 429 doğrulama), 50 paralel süreçli eşzamanlılık testi dahil |
| `composer test:hizli` | Eşzamanlılık testleri hariç |
| `composer analiz` | PHPStan statik analiz (seviye 6) |
| `composer stil` / `composer stil:duzelt` | PSR-12 kod stili denetimi / otomatik düzeltme |

SRS'teki test gereksinimlerinin (T-01 – T-15) hangi dosyada olduğu [tasarim.md bölüm 12.4](docs/tasarim.md#124-testler-srs-48--410--411-eşlemesi)'tedir.

## Ders şartları (Aşama 2)

| Şart | Karşılandığı yer |
| --- | --- |
| PHP 8.2+, Composer otomatik yükleme, framework yok | `composer.json` (PSR-4 `App\` → `src/`) |
| Controller / Service / Repository / View katmanları | `src/Controller`, `src/Service`, `src/Repository`, `views/` |
| Repository yapısı (Aşama 2 JSON) | `src/Repository/*Interface.php` + `src/Repository/Json/` |
| Tasarım deseni | Repository, Strategy (fiyat ve iade), State (rezervasyon durumu) |
| Kayıt, giriş, çıkış, en az 2 rol, her sayfada yetki | 3 rol; erişim kuralı her rotada (`config/routes.php`), sahiplik serviste |
| `password_hash`, `htmlspecialchars`, POST + CSRF, dosya yükleme kontrolü | `AuthService`, `e()`, `Kernel` (CSRF), `PhotoService` (tür, boyut, sahte uzantı) |
| Arama/filtre ve rapor ekranı | `/ara` (konum, tarih, kişi, tür, fiyat, puan, oda tipi, olanak, sıralama), `/panel/rapor`, `/yonetim/rapor` |
| HTML, CSS, Bootstrap, resim/ikon dosyası yok, telefonda kullanılabilir | Bootstrap 5; görseller emoji ve CSS grid (takvim) |
| Sayfanın bir bölümünü kendiliğinden yenileme | Müsaitlik takvimi, gelen rezervasyonlar, rezervasyon durumu, bildirim sayısı ([tasarim.md 12.3](docs/tasarim.md#123-sayfanın-bir-bölümünü-kendiliğinden-yenileme-ders-kalıbı)) |
| Eşzamanlılık: koşullu güncelleme ve etkilenen satır kontrolü | `ReservationRepositoryInterface::updateStatusIf` + dosya kilidi + `UNIQUE(birim, gece)` |
| Çekirdek problem için en az 5 PHPUnit testi | 137 test |
| Örnek veri (en az 10 oda/ev, 3 oda tipi) | 16 birim, 6 oda tipi |
| Kart: tarih bazlı arama, aylık takvim, gecelik döküm, yaşam döngüsü, doluluk ve gelir raporu, resim yükleme | Hepsi uygulandı |

**Aşama 2'ye uyan bonuslar:** PSR-12 ve PHPStan, ek tasarım desenleri (Strategy ×2, State), şifre değiştirme, oturum zaman aşımı (30 dk), sayfalama, CSV dışa aktarma, yazarken sonuç veren canlı arama (arama sayfası ve yönetici kullanıcı listesi), sayfa yenilenmeden gönderilen formlar (favori düğmesi, fiyat önizleme), 137 test.

**Aşama 3'e kalanlar:** MySQL/PDO depo sınıfları (`src/Repository/MySql/`), tabloları oluşturan ve örnek veriyi yükleyen SQL dosyaları, JOIN'li sorgu, indeksler, veritabanı transaction'ı ve satır kilidi, test raporu.

## Klasör yapısı

```
public/      ön denetleyici (index.php) ve CSS
config/      app.php (süre, limit, oran ayarları), routes.php (adres → controller + yetki)
src/         Core, Controller, Service, Repository (Json), Model, Support, Exception, Seed
views/       Bootstrap görünümleri (her çıktı e() ile kaçışlanır)
bin/         seed.php, gorev.php
storage/     data (JSON tablolar), uploads, logs, mail (simüle e-posta) – web'den erişilemez
tests/       Unit, Integration, Concurrency
docs/        srs.md, tasarim.md, ai-gunlugu.md, arayüz tasarımları
```

## Belgeler

- [Gereksinimler (SRS v2.3)](docs/srs.md) · [kaynak .docx](docs/OTEL_KIRALIK_EV_REZERVASYON_SISTEMI_SRS_v2_3.docx)
- [Tasarım belgesi](docs/tasarim.md)
- [Yapay zekâ kullanım günlüğü](docs/ai-gunlugu.md)
