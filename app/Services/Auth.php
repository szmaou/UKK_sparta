<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\User;

/**
 * Session-backed identity. The stored session holds only the user ID; the role
 * and active flag are re-read from the database on every request so a disabled
 * account loses access immediately.
 */
final class Auth
{
    /**
     * Verified against when no active account matches, so a failed login costs
     * the same bcrypt work whether or not the username exists (timing oracle).
     */
    private const DUMMY_HASH = '$2y$12$QQ2/OAb6SxhGF7815cadzOot8OlB97mVzKFN5l.AS/YqDhd1G1N4i';

    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(
        private Session $session,
        private User $users
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = $this->session->get('user_id');
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        $user = $this->users->findActiveById((int) $id);
        if ($user === null) {
            // Account removed or deactivated since login: drop the session.
            $this->session->destroy();

            return null;
        }

        $this->user = $user;

        return $user;
    }

    /** Verify credentials against the active-account row. */
    public function attempt(string $username, string $password): ?array
    {
        $user = $this->users->findActiveByUsername($username);
        $hash = $user === null ? self::DUMMY_HASH : (string) $user['password_hash'];
        if (!password_verify($password, $hash) || $user === null) {
            return null;
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    public function login(array $user): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->user = $user;
        $this->resolved = true;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
        $this->resolved = true;
    }
}
