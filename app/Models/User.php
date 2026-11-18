<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * User accounts. Admin CRUD lives here; authentication reads stay unchanged.
 * Passwords are only ever stored as hashes and are never written to the audit
 * trail. Account deletion means deactivation so loan history survives.
 */
final class User
{
    public const PER_PAGE = 10;
    public const ROLES = ['admin', 'petugas', 'peminjam'];
    private const MAX_PASSWORD_BYTES = 72;

    public function __construct(private PDO $db, private ActivityLog $logs)
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

    /**
     * Normalise raw form input. The password is returned separately so it can
     * never leak into the re-rendered form values.
     *
     * @param array<string, mixed> $input
     * @return array{values: array<string, string>, errors: array<string, string>, password: string}
     */
    public function parse(array $input, ?int $id = null): array
    {
        $values = [
            'id' => $id === null ? '' : (string) $id,
            'name' => is_string($input['name'] ?? null) ? trim($input['name']) : '',
            'username' => is_string($input['username'] ?? null) ? trim($input['username']) : '',
            'role' => match (true) {
                !array_key_exists('role', $input) => $id === null ? 'peminjam' : '',
                is_string($input['role']) => trim($input['role']),
                default => '',
            },
            // Missing status defaults to active only on create; an edit must
            // send an explicit "0" or "1", and arrays are always rejected.
            'is_active' => match (true) {
                !array_key_exists('is_active', $input) => $id === null ? '1' : '',
                is_string($input['is_active']) => trim($input['is_active']),
                default => '',
            },
        ];
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'] = 'Nama pengguna wajib diisi.';
        } elseif (mb_strlen($values['name']) > 100) {
            $errors['name'] = 'Nama pengguna maksimal 100 karakter.';
        }
        if ($values['username'] === '') {
            $errors['username'] = 'Username wajib diisi.';
        } elseif (mb_strlen($values['username']) > 50) {
            $errors['username'] = 'Username maksimal 50 karakter.';
        } elseif ($this->usernameTaken($values['username'], $id)) {
            $errors['username'] = 'Username sudah digunakan. Gunakan username lain.';
        }
        if ($password === '') {
            if ($id === null) {
                $errors['password'] = 'Password wajib diisi.';
            }
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        } elseif (strlen($password) > self::MAX_PASSWORD_BYTES) {
            // bcrypt silently truncates at 72 bytes; reject instead.
            $errors['password'] = 'Password maksimal 72 byte.';
        }
        if (!in_array($values['role'], self::ROLES, true)) {
            $errors['role'] = 'Role pengguna tidak valid.';
        }
        if (!in_array($values['is_active'], ['0', '1'], true)) {
            $errors['is_active'] = 'Status pengguna tidak valid.';
        }

        return ['values' => $values, 'errors' => $errors, 'password' => $password];
    }

    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function paginate(string $q, int $page): array
    {
        $page = max(1, $page);
        $q = mb_substr(trim($q), 0, 100);
        $where = $q === '' ? '' : ' WHERE (u.name LIKE :qn OR u.username LIKE :qu)';
        $count = $this->db->prepare('SELECT COUNT(*) FROM users u' . $where);
        if ($q !== '') {
            $count->bindValue(':qn', '%' . $q . '%');
            $count->bindValue(':qu', '%' . $q . '%');
        }
        $count->execute();
        $total = (int) $count->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT u.id, u.name, u.username, u.role, u.is_active, u.created_at
             FROM users u' . $where . ' ORDER BY u.name, u.id LIMIT :limit OFFSET :offset'
        );
        if ($q !== '') {
            $stmt->bindValue(':qn', '%' . $q . '%');
            $stmt->bindValue(':qu', '%' . $q . '%');
        }
        $stmt->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => array_values($stmt->fetchAll()),
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / self::PER_PAGE)),
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, username, role, is_active, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, string> $values parsed, error-free values */
    public function create(array $values, string $password, int $actorId): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Password tidak dapat diproses.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $values['name'],
                $values['username'],
                $hash,
                $values['role'],
                (int) $values['is_active'],
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->logs->record(
                $actorId,
                'create',
                'user',
                $id,
                'Tambah pengguna: ' . $values['username'] . ' (' . $values['role'] . ')'
            );
            $this->db->commit();

            return $id;
        } catch (PDOException $exception) {
            $this->db->rollBack();
            throw $this->translate($exception);
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /**
     * Update an account. Guards are evaluated under row locks: every active
     * admin row is locked in id order before the target, so two concurrent
     * demotions cannot both observe another remaining admin.
     *
     * @param array<string, string> $values parsed, error-free values
     * @return bool false when the account no longer exists
     */
    public function update(int $id, array $values, string $password, int $actorId): bool
    {
        $hash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
        if ($password !== '' && !is_string($hash)) {
            throw new RuntimeException('Password tidak dapat diproses.');
        }

        $this->db->beginTransaction();
        try {
            $activeAdmins = $this->lockActiveAdmins();
            $lock = $this->db->prepare(
                'SELECT username, role, is_active FROM users WHERE id = ? FOR UPDATE'
            );
            $lock->execute([$id]);
            $row = $lock->fetch();
            if (!is_array($row)) {
                $this->db->rollBack();

                return false;
            }

            $oldRole = (string) $row['role'];
            $oldActive = (int) $row['is_active'];
            if ($id === $actorId && $values['role'] !== $oldRole) {
                throw new DomainException('Role akun yang sedang dipakai tidak dapat diubah.');
            }
            if ($id === $actorId && (int) $values['is_active'] === 0) {
                throw new DomainException('Akun yang sedang dipakai tidak dapat dinonaktifkan.');
            }
            $wasActiveAdmin = $oldRole === 'admin' && $oldActive === 1;
            $staysActiveAdmin = $values['role'] === 'admin' && (int) $values['is_active'] === 1;
            if ($wasActiveAdmin && !$staysActiveAdmin && count($activeAdmins) <= 1) {
                throw new DomainException('Admin aktif terakhir tidak dapat dihapus atau diturunkan.');
            }

            $sql = 'UPDATE users SET name = ?, username = ?, role = ?, is_active = ?';
            $params = [$values['name'], $values['username'], $values['role'], (int) $values['is_active']];
            if ($hash !== null) {
                $sql .= ', password_hash = ?';
                $params[] = $hash;
            }
            $sql .= ' WHERE id = ?';
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);

            $this->logs->record(
                $actorId,
                'update',
                'user',
                $id,
                'Ubah pengguna: ' . $values['username']
                . '; role ' . $oldRole . ' -> ' . $values['role']
                . '; status ' . $oldActive . ' -> ' . $values['is_active']
                . ($hash === null ? '' : '; password diubah')
            );
            $this->db->commit();

            return true;
        } catch (PDOException $exception) {
            $this->db->rollBack();
            throw $this->translate($exception);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Accounts are always deactivated, never deleted: peminjaman, pengembalian
     * and audit rows reference the account. The row lock plus the active-admin
     * set makes the last-admin guard race-free.
     *
     * @return 'deactivated'|'missing'
     */
    public function delete(int $id, int $actorId): string
    {
        $this->db->beginTransaction();
        try {
            $activeAdmins = $this->lockActiveAdmins();
            $lock = $this->db->prepare(
                'SELECT username, role, is_active FROM users WHERE id = ? FOR UPDATE'
            );
            $lock->execute([$id]);
            $row = $lock->fetch();
            if (!is_array($row)) {
                $this->db->rollBack();

                return 'missing';
            }
            if ($id === $actorId) {
                throw new DomainException('Akun yang sedang dipakai tidak dapat dinonaktifkan.');
            }
            if ($row['role'] === 'admin' && (int) $row['is_active'] === 1 && count($activeAdmins) <= 1) {
                throw new DomainException('Admin aktif terakhir tidak dapat dihapus atau diturunkan.');
            }

            if ((int) $row['is_active'] === 1) {
                $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);
                $this->logs->record(
                    $actorId,
                    'deactivate',
                    'user',
                    $id,
                    'Nonaktifkan pengguna: ' . $row['username']
                );
            }
            $this->db->commit();

            return 'deactivated';
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<int> active admin ids, locked against concurrent demotion */
    private function lockActiveAdmins(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id FOR UPDATE"
        );
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function usernameTaken(string $username, ?int $id): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = ?';
        $params = [$username];
        if ($id !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $id;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function translate(PDOException $exception): \Throwable
    {
        if ($exception->getCode() !== '23000') {
            return $exception;
        }
        if (str_contains($exception->getMessage(), 'uq_users_username')) {
            return new DomainException('Username sudah digunakan. Gunakan username lain.');
        }

        return new DomainException('Data pengguna melanggar aturan integritas.');
    }
}
