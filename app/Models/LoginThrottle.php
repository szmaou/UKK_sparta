<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Failed-login throttle. One row per username hash: MAX_ATTEMPTS failures inside
 * WINDOW_MINUTES block that username for the same span. Keyed by account, not by
 * client address, because php-fpm only ever sees the local nginx proxy.
 *
 * Trade-off: an attacker who knows a username can lock it for 15 minutes. The
 * short window keeps that denial small and is preferable to unlimited guessing.
 */
final class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_MINUTES = 15;

    public function __construct(private PDO $db)
    {
    }

    /** Seconds left on the lock, 0 when the username may try again. */
    public function lockedFor(string $username): int
    {
        $stmt = $this->db->prepare('SELECT blocked_until FROM login_attempts WHERE username_hash = ? LIMIT 1');
        $stmt->execute([self::key($username)]);
        $until = $stmt->fetchColumn();
        if (!is_string($until) || $until === '') {
            return 0;
        }

        return max(0, strtotime($until) - time());
    }

    public function recordFailure(string $username): void
    {
        $key = self::key($username);
        $now = time();
        $nowSql = date('Y-m-d H:i:s', $now);
        $cutoff = date('Y-m-d H:i:s', $now - self::WINDOW_MINUTES * 60);

        $this->db->beginTransaction();
        try {
            // Create the row without counting, then read it under lock so
            // concurrent failures cannot lose an increment.
            $this->db->prepare(
                'INSERT INTO login_attempts (username_hash, attempts, window_start) VALUES (?, 0, ?)
                 ON DUPLICATE KEY UPDATE username_hash = username_hash'
            )->execute([$key, $nowSql]);
            $stmt = $this->db->prepare(
                'SELECT attempts, window_start, blocked_until FROM login_attempts
                 WHERE username_hash = ? FOR UPDATE'
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            if (!is_array($row)) {
                $attempts = 1;
                $windowStart = $nowSql;
                $blockedUntil = null;
            } else {
                $expired = (string) $row['window_start'] < $cutoff;
                $attempts = $expired ? 1 : (int) $row['attempts'] + 1;
                $windowStart = $expired ? $nowSql : (string) $row['window_start'];
                $blockedUntil = $attempts >= self::MAX_ATTEMPTS
                    ? date('Y-m-d H:i:s', $now + self::WINDOW_MINUTES * 60)
                    : ($expired ? null : $row['blocked_until']);
            }

            $this->db->prepare(
                'UPDATE login_attempts SET attempts = ?, window_start = ?, blocked_until = ?
                 WHERE username_hash = ?'
            )->execute([$attempts, $windowStart, $blockedUntil, $key]);

            // Keep the table bounded when someone rotates usernames.
            $this->db->exec('DELETE FROM login_attempts WHERE updated_at < NOW() - INTERVAL 1 DAY LIMIT 200');
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function clear(string $username): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE username_hash = ?')->execute([self::key($username)]);
    }

    public static function key(string $username): string
    {
        return hash('sha256', mb_strtolower(trim($username)));
    }
}
