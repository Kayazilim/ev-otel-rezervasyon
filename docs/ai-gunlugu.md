# Yapay Zekâ Kullanım Günlüğü

Ders kuralı: kodlamada yapay zekâ kullanılabilir, ancak grubun her üyesi projenin her bölümünü açıklayabilmelidir. Bu dosyada her yapay zekâ kullanımı; kimin, ne zaman, hangi araçla, ne amaçla kullandığı ve çıktının nasıl doğrulandığıyla birlikte kayıt altına alınır. Her üye kendi kullanımını yeni bir satır/başlık olarak ekler.

## Kayıt biçimi

| Alan | Açıklama |
| --- | --- |
| Tarih | Kullanım tarihi |
| Üye | Aracı kullanan grup üyesi |
| Araç / model | Örn. Claude Code (Claude Opus 5.5), ChatGPT, Copilot |
| Amaç | Ne için kullanıldı |
| İstem özeti | Yapay zekâya ne verildi (bağlam: SRS, tasarım, ders kuralları) |
| Çıktı | Ne üretildi (dosyalar) |
| Doğrulama | Üyenin nasıl kontrol ettiği, neyi değiştirdiği |

## Kayıtlar

### 07.11.2026 · Kaan Şahin · Claude Code (Claude Opus 5.5)

| Alan | İçerik |
| --- | --- |
| Amaç | Aşama 2 (veritabanısız geliştirme) prototipinin yazılması ve Aşama 1 belgelerindeki eksiklerin giderilmesi |
| İstem özeti | Ders proje belgesindeki ortak şartlar (zorunlu ve bonus), proje 12 kartı (zorunlu özellikler), depodaki SRS v2.3, tasarim.md ve 6 arayüz taslağı bağlam olarak verildi. Yalnızca Aşama 2'ye uyan zorunlu ve bonus maddelerin yapılması, SRS ve ders kurallarından sapılmaması istendi. Mimari tablo (Controller → Service → Repository) ve SRS Bölüm 4.7 sınıf eşlemesi her adımda kural olarak uygulandı (risk R7). |
| Çıktı | `src/` (katmanlı mimari, JSON depo, çekirdek servisler, Strategy ve State desenleri), `views/` (Bootstrap arayüz), `config/`, `bin/`, `tests/` (137 PHPUnit testi), `docs/srs.md` (SRS'in .docx'ten Markdown'a dönüşümü), `docs/tasarim.md` düzeltmeleri, `README.md` |
| Doğrulama | Tüm testler çalıştırıldı (137 test, 429 doğrulama; 50 paralel süreçle çift rezervasyon testi dahil). PHPStan seviye 6 ve PSR-12 denetimi hatasız. Uygulama tarayıcıda uçtan uca denendi: arama, ilan detayı, takvim, giriş, rezervasyon, simüle ödeme, iptal ve iade, ilan sahibi onayı, resim yükleme, rapor ve CSV. SRS örnek hesapları (AC-23: 4.420,00 TL; AC-24: 9.523,80 TL; iade örnekleri) testlerle birebir doğrulandı. |
| Dikkat edilecekler | Her üyenin sunumda açıklayabilmesi için kodun okunması gerekir. Önerilen okuma sırası: `config/routes.php` → `src/Core/Kernel.php` → `src/Controller/ReservationController.php` → `src/Service/ReservationService.php` → `src/Service/BookingConflictService.php` → `src/Service/PricingService.php` ve `Pricing/` → `src/Service/RefundService.php` → `src/Service/State/` → `src/Repository/Json/JsonDatabase.php`. SRS'nin açık bıraktığı noktalarda verilen kararlar tasarim.md bölüm 12.2'dedir; ekip ve ders yürütücüsüyle teyit edilmelidir. |
