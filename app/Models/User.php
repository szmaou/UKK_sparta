<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, username, password_hash, role, is_active, created_at
             FROM users WHERE username = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, username, role, is_active, created_at
             FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
