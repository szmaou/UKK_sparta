<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Append-only audit trail. Never store passwords, tokens or session IDs here. */
final class ActivityLog
{
    public const PER_PAGE = 20;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Admin audit view (FR-08): newest first, filterable by action and free text.
     *
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int}
     */
    public function paginate(string $q, string $action, int $page): array
    {
        $where = [];
        $params = [];
        $q = mb_substr(trim($q), 0, 100);
        if ($action !== '') {
            $where[] = 'al.action = :action';
            $params[':action'] = $action;
        }
        if ($q !== '') {
            $where[] = '(al.description LIKE :qd OR al.entity_type LIKE :qe OR u.name LIKE :qn OR u.username LIKE :qu)';
            $params[':qd'] = '%' . $q . '%';
            $params[':qe'] = '%' . $q . '%';
            $params[':qn'] = '%' . $q . '%';
            $params[':qu'] = '%' . $q . '%';
        }
        $from = ' FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));

        $count = $this->db->prepare('SELECT COUNT(*)' . $from);
        foreach ($params as $key => $value) {
            $count->bindValue($key, $value);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT al.id, al.action, al.entity_type, al.entity_id, al.description, al.created_at,
                    u.name AS actor_name, u.username AS actor_username'
            . $from
            . ' ORDER BY al.created_at DESC, al.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $page = max(1, $page);
        $stmt->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => array_values($stmt->fetchAll()),
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / self::PER_PAGE)),
        ];
    }

    /** @return list<string> */
    public function actions(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT action FROM activity_logs ORDER BY action');

        return array_map(static fn (array $row): string => (string) $row['action'], $stmt->fetchAll());
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
