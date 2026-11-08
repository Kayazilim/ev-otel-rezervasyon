-- Aşama 3 şeması (3/4): rezervasyon, geceler, fiyat kalemleri, ödemeler (çekirdek problem)

CREATE TABLE reservations (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_no      VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,   -- RZ-YYYYMMDD-XXXXXX
    customer_id         INT UNSIGNED NOT NULL,
    unit_id             INT UNSIGNED NOT NULL,
    check_in            DATE NOT NULL,                       -- yarı açık aralık [check_in, check_out)
    check_out           DATE NOT NULL,
    guest_count         TINYINT UNSIGNED NOT NULL,
    status              ENUM('beklemede', 'onaylandi', 'iptal_edildi', 'reddedildi', 'suresi_doldu', 'tamamlandi') NOT NULL,
    expires_at          DATETIME NULL,                       -- Beklemede bekletmesinin bitişi (BR-15)
    total_price         DECIMAL(10, 2) NOT NULL,             -- fiyat anlık görüntüsü (BR-16)
    cancellation_policy ENUM('esnek', 'orta', 'kati') NOT NULL, -- politika anlık görüntüsü (BR-29)
    idempotency_key     VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    contact_name        VARCHAR(100) NOT NULL,
    contact_phone       VARCHAR(20)  NOT NULL,
    note                VARCHAR(500) NULL,
    cancel_reason       TEXT NULL,
    cancelled_at        DATETIME NULL,
    refund_amount       DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reservations_no (reservation_no),
    UNIQUE KEY uq_reservations_idempotency (idempotency_key),   -- A4: çift gönderimde tek kayıt
    UNIQUE KEY uq_reservations_id_unit (id, unit_id),           -- reservation_nights bileşik FK hedefi
    KEY ix_reservations_overlap (unit_id, status, check_in, check_out),
    KEY ix_reservations_customer (customer_id),
    KEY ix_reservations_hold (status, expires_at),
    CONSTRAINT fk_reservations_customer FOREIGN KEY (customer_id) REFERENCES users (id),
    CONSTRAINT fk_reservations_unit FOREIGN KEY (unit_id) REFERENCES bookable_units (id),
    CONSTRAINT ck_reservations_dates CHECK (check_out > check_in),
    CONSTRAINT ck_reservations_guests CHECK (guest_count >= 1),
    CONSTRAINT ck_reservations_money CHECK (total_price >= 0 AND refund_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Engelleyen geceler. UNIQUE(unit_id, night_date): aynı birimin aynı gecesi en fazla bir kez tutulabilir.
-- Uygulama kuralına ek olarak veritabanı düzeyinde çift rezervasyona karşı son savunmadır (SRS 4.5, 7.1).
-- unit_id 3NF'ye göre fazlalıktır (bilinçli istisna); tutarsızlığı (reservation_id, unit_id) bileşik FK'si önler.
CREATE TABLE reservation_nights (
    reservation_id INT UNSIGNED NOT NULL,
    unit_id        INT UNSIGNED NOT NULL,
    night_date     DATE NOT NULL,
    PRIMARY KEY (reservation_id, night_date),
    UNIQUE KEY uq_nights_unit_night (unit_id, night_date),
    KEY ix_nights_reservation_unit (reservation_id, unit_id),
    CONSTRAINT fk_nights_reservation FOREIGN KEY (reservation_id, unit_id) REFERENCES reservations (id, unit_id),
    CONSTRAINT fk_nights_unit FOREIGN KEY (unit_id) REFERENCES bookable_units (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fiyat dökümü kalemleri (gece, indirim (eksi), vergi, ek ücret). Toplamları reservations.total_price'a eşittir.
CREATE TABLE reservation_charges (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id INT UNSIGNED NOT NULL,
    type           ENUM('konaklama_gecesi', 'indirim', 'vergi', 'ek_ucret') NOT NULL,
    night_date     DATE NULL,                                -- yalnızca konaklama gecesi
    description    VARCHAR(255) NOT NULL,
    amount         DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_charges_reservation (reservation_id),
    CONSTRAINT fk_charges_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Simüle ödeme (FR-37) ve iadeler
CREATE TABLE payments (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id INT UNSIGNED NOT NULL,
    provider_ref   VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    amount         DECIMAL(10, 2) NOT NULL,
    refund_amount  DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    currency       CHAR(3) NOT NULL DEFAULT 'TRY',
    status         ENUM('beklemede', 'basarili', 'basarisiz', 'kismen_iade', 'iade_edildi') NOT NULL,
    created_at     DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_provider_ref (provider_ref),
    KEY ix_payments_reservation (reservation_id),
    CONSTRAINT fk_payments_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id),
    CONSTRAINT ck_payments_money CHECK (amount >= 0 AND refund_amount >= 0 AND refund_amount <= amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
