-- SPARTA schema (MariaDB / InnoDB).
--
-- One-time import into an empty database. This script is intentionally
-- non-destructive: it never runs DROP DATABASE or DROP TABLE.
--
--   CREATE DATABASE IF NOT EXISTS sparta
--     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mariadb -u <user> -p sparta < database/schema.sql
--
-- Because every table uses CREATE TABLE IF NOT EXISTS, importing this file
-- into an existing SPARTA database is a no-op for tables that already exist.
-- Schema changes after the first import belong in database/migrations/.

CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)    NOT NULL,
    username      VARCHAR(50)     NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('admin', 'petugas', 'peminjam') NOT NULL DEFAULT 'peminjam',
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role),
    KEY idx_users_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kategori (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama       VARCHAR(100) NOT NULL,
    deskripsi  TEXT         NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kategori_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alat (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode           VARCHAR(30)     NOT NULL,
    kategori_id    INT UNSIGNED    NOT NULL,
    nama           VARCHAR(150)    NOT NULL,
    deskripsi      TEXT            NULL,
    stok_total     INT UNSIGNED    NOT NULL DEFAULT 0,
    stok_tersedia  INT UNSIGNED    NOT NULL DEFAULT 0,
    is_active      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alat_kode (kode),
    KEY idx_alat_kategori (kategori_id),
    KEY idx_alat_nama (nama),
    KEY idx_alat_is_active (is_active),
    CONSTRAINT fk_alat_kategori FOREIGN KEY (kategori_id)
        REFERENCES kategori (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_alat_stok CHECK (stok_tersedia <= stok_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peminjaman (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode                VARCHAR(30)     NOT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    alat_id             BIGINT UNSIGNED NOT NULL,
    jumlah              INT UNSIGNED    NOT NULL,
    tanggal_pinjam      DATE            NOT NULL,
    tanggal_jatuh_tempo DATE            NOT NULL,
    status              ENUM('pending', 'approved', 'rejected', 'return_requested', 'returned')
                        NOT NULL DEFAULT 'pending',
    catatan             TEXT            NULL,
    approved_by         BIGINT UNSIGNED NULL,
    approved_at         DATETIME        NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_peminjaman_kode (kode),
    KEY idx_peminjaman_status_jatuh_tempo (status, tanggal_jatuh_tempo),
    KEY idx_peminjaman_created_at (created_at),
    KEY idx_peminjaman_user (user_id),
    KEY idx_peminjaman_alat (alat_id),
    KEY idx_peminjaman_approved_by (approved_by),
    CONSTRAINT fk_peminjaman_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_peminjaman_alat FOREIGN KEY (alat_id)
        REFERENCES alat (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_peminjaman_approved_by FOREIGN KEY (approved_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_peminjaman_jumlah CHECK (jumlah > 0),
    CONSTRAINT chk_peminjaman_tanggal CHECK (tanggal_jatuh_tempo >= tanggal_pinjam)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengembalian (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    peminjaman_id     BIGINT UNSIGNED NOT NULL,
    tanggal_pengajuan DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tanggal_diterima  DATETIME        NULL,
    jumlah            INT UNSIGNED    NOT NULL,
    kondisi           ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang')
                      NOT NULL DEFAULT 'baik',
    hari_terlambat    INT UNSIGNED    NOT NULL DEFAULT 0,
    denda             DECIMAL(12, 2)  NOT NULL DEFAULT 0.00,
    verified_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pengembalian_peminjaman (peminjaman_id),
    KEY idx_pengembalian_verified_by (verified_by),
    CONSTRAINT fk_pengembalian_peminjaman FOREIGN KEY (peminjaman_id)
        REFERENCES peminjaman (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pengembalian_verified_by FOREIGN KEY (verified_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_pengembalian_jumlah CHECK (jumlah > 0),
    CONSTRAINT chk_pengembalian_denda CHECK (denda >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    action      VARCHAR(50)     NOT NULL,
    entity_type VARCHAR(50)     NULL,
    entity_id   BIGINT UNSIGNED NULL,
    description TEXT            NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_logs_user (user_id),
    KEY idx_activity_logs_created_at (created_at),
    KEY idx_activity_logs_entity (entity_type, entity_id),
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
