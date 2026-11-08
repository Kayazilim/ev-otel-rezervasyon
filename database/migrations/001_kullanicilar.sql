-- Aşama 3 şeması (1/4): kullanıcılar ve oturum güvenliği
-- ER diyagramı: docs/tasarim.md bölüm 3. Tüm tablolar InnoDB (transaction + satır kilidi), utf8mb4.
-- Çalıştırma: php bin/migrate.php   (dosyalar ad sırasıyla bir kez uygulanır, schema_migrations tablosuna yazılır)

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name    VARCHAR(50)  NOT NULL,
    last_name     VARCHAR(50)  NOT NULL,
    email         VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,                       -- password_hash() çıktısı; parola düz metin saklanmaz
    role          ENUM('musteri', 'ilan_sahibi', 'yonetici') NOT NULL,
    status        ENUM('aktif', 'kisitli') NOT NULL DEFAULT 'aktif',
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NFR-15: art arda hatalı girişte geçici kilit (e-posta başına tek satır)
CREATE TABLE login_attempts (
    email          VARCHAR(254) NOT NULL,
    failed_count   INT UNSIGNED NOT NULL DEFAULT 0,
    last_failed_at DATETIME NOT NULL,
    locked_until   DATETIME NULL,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FR-04: tek kullanımlık parola sıfırlama jetonu; yalnızca SHA-256 özeti saklanır
CREATE TABLE password_reset_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_token_hash (token_hash),
    KEY ix_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
