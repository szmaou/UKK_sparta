-- Failed-login throttle: one row per username hash, keyed by account because
-- php-fpm only sees the local nginx proxy as the client address.
CREATE TABLE IF NOT EXISTS login_attempts (
    username_hash CHAR(64)         NOT NULL,
    attempts      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_start  DATETIME         NOT NULL,
    blocked_until DATETIME         NULL,
    updated_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (username_hash),
    KEY idx_login_attempts_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
