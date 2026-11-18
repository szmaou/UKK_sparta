<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Native PHP session with secure defaults. The cookie carries only the session
 * ID: it is HttpOnly, SameSite=Lax and Secure whenever the request is HTTPS.
 *
 * Shortcut: HTTPS detection trusts only $_SERVER['HTTPS']. If the app is put
 * behind a TLS-terminating proxy, set HTTPS=on from the web server or extend
 * start() to inspect X-Forwarded-Proto from a trusted proxy.
 */
final class Session
{
    private const NAME = 'SPARTASESS';
    private const CSRF_KEY = '_csrf';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return $https !== '' && strtolower((string) $https) !== 'off';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Rotate the session ID and issue a fresh CSRF token after login. */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION[self::CSRF_KEY]);
    }

    public function csrfToken(): string
    {
        $token = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return $token;
    }

    public function verifyCsrf(mixed $token): bool
    {
        $expected = $_SESSION[self::CSRF_KEY] ?? null;

        return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $params = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
