-- Application settings (key/value). Currently stores the late-return fine tariff.
CREATE TABLE IF NOT EXISTS pengaturan (
    kunci      VARCHAR(50)  NOT NULL,
    nilai      VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kunci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pengaturan (kunci, nilai) VALUES ('tarif_denda', '0')
ON DUPLICATE KEY UPDATE kunci = kunci;
