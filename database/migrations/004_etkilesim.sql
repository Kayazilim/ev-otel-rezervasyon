-- Aşama 3 şeması (4/4): yorum, favori, bildirim, şikâyet ve denetim kaydı

-- Yorumun ilanı ayrı sütunda tutulmaz (3NF): reviews ⋈ reservations ⋈ bookable_units ile bulunur
CREATE TABLE reviews (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id    INT UNSIGNED NOT NULL,
    customer_id       INT UNSIGNED NOT NULL,
    rating            TINYINT UNSIGNED NOT NULL,
    comment           TEXT NOT NULL,
    moderation_status ENUM('yayinda', 'incelemede', 'gizlendi') NOT NULL DEFAULT 'yayinda',
    created_at        DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_reservation (reservation_id),       -- BR-22: rezervasyon başına tek yorum
    KEY ix_reviews_customer (customer_id),
    CONSTRAINT fk_reviews_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id),
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES users (id),
    CONSTRAINT ck_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
    user_id     INT UNSIGNED NOT NULL,
    property_id INT UNSIGNED NOT NULL,
    added_at    DATETIME NOT NULL,
    PRIMARY KEY (user_id, property_id),
    KEY ix_favorites_property (property_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_favorites_property FOREIGN KEY (property_id) REFERENCES properties (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(40) NOT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE complaints (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id      INT UNSIGNED NOT NULL,
    property_id    INT UNSIGNED NULL,
    reservation_id INT UNSIGNED NULL,
    description    TEXT NOT NULL,
    status         ENUM('yeni', 'inceleniyor', 'cozuldu', 'reddedildi') NOT NULL DEFAULT 'yeni',
    admin_note     TEXT NULL,
    created_at     DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_complaints_status (status),
    CONSTRAINT fk_complaints_sender FOREIGN KEY (sender_id) REFERENCES users (id),
    CONSTRAINT fk_complaints_property FOREIGN KEY (property_id) REFERENCES properties (id),
    CONSTRAINT fk_complaints_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Denetim kaydı (FR-44): durum geçişleri ve yönetici işlemleri; actor_id sistem işlemlerinde NULL
CREATE TABLE audit_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id    INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id   INT UNSIGNED NOT NULL,
    action      VARCHAR(40) NOT NULL,
    old_value   TEXT NULL,
    new_value   TEXT NULL,
    reason      TEXT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_audit_entity (entity_type, entity_id),
    KEY ix_audit_created (created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
