<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Append-only audit trail. Never store passwords, tokens or session IDs here. */
final class ActivityLog
{
    public function __construct(private PDO $db)
    {
    }

    public function record(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $entityType, $entityId, $description]);
    }
}
