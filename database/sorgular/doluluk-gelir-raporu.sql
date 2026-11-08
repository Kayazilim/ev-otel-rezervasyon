-- Doluluk ve gelir raporunun JOIN'li veri sorgusu (FR-30, BR-30)
-- Uygulamadaki karşılığı: src/Repository/MySql/MySqlReservationRepository.php -> reportRows()
-- (uygulama aynı sorguyu hazırlıklı ifadeyle, ? parametreleriyle çalıştırır)
--
-- MySQL istemcisinde denemek için:
--   SET @baslangic = '2026-11-01', @bitis_haric = '2026-12-01', @ilan_sahibi = NULL;
--   (dönem yarı açıktır: [@baslangic, @bitis_haric) ; @ilan_sahibi NULL ise tüm ilanlar)

SET @baslangic = '2026-11-01', @bitis_haric = '2026-12-01', @ilan_sahibi = NULL;

-- 1) Rezervasyon başına tahsil edilen ödeme ve iade (ReportService bu satırlardan kırılımları hesaplar)
SELECT r.id, r.reservation_no, pr.name AS ilan, u.name AS birim, r.status, r.check_in, r.check_out,
       COALESCE(SUM(CASE WHEN p.status IN ('basarili', 'kismen_iade', 'iade_edildi') THEN p.amount ELSE 0 END), 0) AS odenen,
       COALESCE(SUM(CASE WHEN p.status IN ('basarili', 'kismen_iade', 'iade_edildi') THEN p.refund_amount ELSE 0 END), 0) AS iade
FROM reservations r
JOIN bookable_units u ON u.id = r.unit_id
JOIN properties pr ON pr.id = u.property_id
LEFT JOIN payments p ON p.reservation_id = r.id
WHERE r.check_in < @bitis_haric AND r.check_out > @baslangic
  AND (@ilan_sahibi IS NULL OR pr.owner_id = @ilan_sahibi)
GROUP BY r.id, r.reservation_no, pr.name, u.name, r.status, r.check_in, r.check_out
ORDER BY r.id;

-- 2) İlan bazında özet: dolu gece, rezervasyon sayısı ve net gelir (BR-30 formülleri)
--    Dolu gece = Onaylandı + Tamamlandı rezervasyonların dönemle kesişen geceleri
--    Net gelir = girişi dönemde olan rezervasyonların tahsil edilen ödemeleri - iadeler
SELECT pr.id, pr.name AS ilan,
       COUNT(DISTINCT CASE WHEN r.check_in >= @baslangic AND r.check_in < @bitis_haric THEN r.id END) AS rezervasyon,
       COALESCE(SUM(CASE WHEN r.status IN ('onaylandi', 'tamamlandi')
                         THEN DATEDIFF(LEAST(r.check_out, @bitis_haric), GREATEST(r.check_in, @baslangic)) END), 0) AS dolu_gece,
       COALESCE(SUM(CASE WHEN r.check_in >= @baslangic AND r.check_in < @bitis_haric THEN o.odenen - o.iade END), 0) AS net_gelir
FROM properties pr
JOIN bookable_units u ON u.property_id = pr.id
LEFT JOIN reservations r ON r.unit_id = u.id AND r.check_in < @bitis_haric AND r.check_out > @baslangic
LEFT JOIN (
    SELECT reservation_id,
           SUM(CASE WHEN status IN ('basarili', 'kismen_iade', 'iade_edildi') THEN amount ELSE 0 END) AS odenen,
           SUM(CASE WHEN status IN ('basarili', 'kismen_iade', 'iade_edildi') THEN refund_amount ELSE 0 END) AS iade
    FROM payments
    GROUP BY reservation_id
) o ON o.reservation_id = r.id
WHERE (@ilan_sahibi IS NULL OR pr.owner_id = @ilan_sahibi)
GROUP BY pr.id, pr.name
ORDER BY pr.id;
