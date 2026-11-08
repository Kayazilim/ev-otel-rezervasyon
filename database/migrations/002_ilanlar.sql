-- Aşama 3 şeması (2/4): ilanlar, birimler, olanaklar, fotoğraflar, takvim ve fiyat kuralları

CREATE TABLE properties (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id            INT UNSIGNED NOT NULL,
    name                VARCHAR(120) NOT NULL,
    type                ENUM('otel', 'kiralik_ev') NOT NULL,
    description         TEXT NOT NULL,
    address             VARCHAR(255) NOT NULL,
    city                VARCHAR(60)  NOT NULL,
    booking_mode        ENUM('aninda', 'onayli') NOT NULL,
    cancellation_policy ENUM('esnek', 'orta', 'kati') NOT NULL,
    status              ENUM('taslak', 'aktif', 'pasif', 'kaldirildi') NOT NULL DEFAULT 'taslak',
    PRIMARY KEY (id),
    KEY ix_properties_owner (owner_id),
    KEY ix_properties_status_city (status, city),
    CONSTRAINT fk_properties_owner FOREIGN KEY (owner_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rezerve edilebilir birim: otelde oda, kiralık evde evin kendisi (SRS 4.1)
CREATE TABLE bookable_units (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id INT UNSIGNED NOT NULL,
    name        VARCHAR(60) NOT NULL,
    room_type   ENUM('tek_kisilik', 'cift_kisilik', 'aile', 'suit', 'mustakil_ev', 'daire') NOT NULL,
    capacity    TINYINT UNSIGNED NOT NULL,
    base_price  DECIMAL(10, 2) NOT NULL,                       -- gecelik taban fiyat (TL)
    status      ENUM('aktif', 'pasif') NOT NULL DEFAULT 'aktif',
    PRIMARY KEY (id),
    UNIQUE KEY uq_units_property_name (property_id, name),
    CONSTRAINT fk_units_property FOREIGN KEY (property_id) REFERENCES properties (id),
    CONSTRAINT ck_units_capacity CHECK (capacity >= 1),
    CONSTRAINT ck_units_price CHECK (base_price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE amenities (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_amenities_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Çoka-çok ara tablo (1NF: olanak listesi tek sütunda tutulmaz)
CREATE TABLE property_amenities (
    property_id INT UNSIGNED NOT NULL,
    amenity_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (property_id, amenity_id),
    KEY ix_pa_amenity (amenity_id),
    CONSTRAINT fk_pa_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_amenity FOREIGN KEY (amenity_id) REFERENCES amenities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FR-46: dosya web kökü dışında, adı rastgele; unit_id doluysa fotoğraf o odaya aittir
CREATE TABLE photos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id INT UNSIGNED NOT NULL,
    unit_id     INT UNSIGNED NULL,
    file_name   VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    mime_type   VARCHAR(50) NOT NULL,
    size_bytes  INT UNSIGNED NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_photos_file (file_name),
    KEY ix_photos_property (property_id, sort_order),
    CONSTRAINT fk_photos_property FOREIGN KEY (property_id) REFERENCES properties (id),
    CONSTRAINT fk_photos_unit FOREIGN KEY (unit_id) REFERENCES bookable_units (id),
    CONSTRAINT ck_photos_size CHECK (size_bytes > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Takvim istisnası: kapalı gün veya o güne özel fiyat (FR-25)
CREATE TABLE calendar_entries (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id       INT UNSIGNED NOT NULL,
    `date`        DATE NOT NULL,
    status        ENUM('acik', 'kapali') NOT NULL,
    special_price DECIMAL(10, 2) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calendar_unit_date (unit_id, `date`),
    CONSTRAINT fk_calendar_unit FOREIGN KEY (unit_id) REFERENCES bookable_units (id),
    CONSTRAINT ck_calendar_price CHECK (special_price IS NULL OR special_price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fiyat kuralları (Strategy deseni: sezon / hafta sonu / uzun konaklama). unit_id NULL: tüm birimler
CREATE TABLE price_rules (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id INT UNSIGNED NOT NULL,
    unit_id     INT UNSIGNED NULL,
    type        ENUM('sezon', 'hafta_sonu', 'uzun_konaklama') NOT NULL,
    name        VARCHAR(80) NOT NULL,
    start_date  DATE NULL,                                    -- yalnızca sezon
    end_date    DATE NULL,                                    -- yalnızca sezon (dahil)
    min_nights  SMALLINT UNSIGNED NULL,                       -- yalnızca uzun konaklama
    `value`     DECIMAL(10, 2) NOT NULL,
    value_type  ENUM('yuzde', 'sabit') NOT NULL,
    priority    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY ix_price_rules_property (property_id),
    CONSTRAINT fk_price_rules_property FOREIGN KEY (property_id) REFERENCES properties (id),
    CONSTRAINT fk_price_rules_unit FOREIGN KEY (unit_id) REFERENCES bookable_units (id),
    CONSTRAINT ck_price_rules_dates CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vergi ve ek ücret kuralları (FR-29)
CREATE TABLE pricing_rules (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id INT UNSIGNED NOT NULL,
    name        VARCHAR(80) NOT NULL,
    type        ENUM('vergi', 'ek_ucret') NOT NULL,
    calc_method ENUM('yuzde', 'gecelik_sabit', 'rezervasyon_basina_sabit') NOT NULL,
    `value`     DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_pricing_rules_property (property_id),
    CONSTRAINT fk_pricing_rules_property FOREIGN KEY (property_id) REFERENCES properties (id),
    CONSTRAINT ck_pricing_rules_value CHECK (`value` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
