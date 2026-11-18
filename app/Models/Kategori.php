<?php
declare(strict_types=1);
namespace App\Models;
use DomainException;
use PDO;
use PDOException;
/** Kategori master data. Mutations run in one transaction with their audit row. */
final class Kategori
{
    public const PER_PAGE = 10;
    public function __construct(private PDO $db, private ActivityLog $logs)
    {
    }
    /**
     * Normalise raw form input and collect field errors. Values stay strings so
     * the form can be re-rendered exactly as typed.
     *
     * @param array<string, mixed> $input
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function parse(array $input, ?int $id = null): array
    {
        $values = [
            'id' => $id === null ? '' : (string) $id,
            'nama' => is_string($input['nama'] ?? null) ? trim($input['nama']) : '',
            'deskripsi' => is_string($input['deskripsi'] ?? null) ? trim($input['deskripsi']) : '',
        ];
        $errors = [];
        if ($values['nama'] === '') {
            $errors['nama'] = 'Nama kategori wajib diisi.';
        } elseif (mb_strlen($values['nama']) > 100) {
            $errors['nama'] = 'Nama kategori maksimal 100 karakter.';
        } elseif ($this->nameTaken($values['nama'], $id)) {
            $errors['nama'] = 'Nama kategori sudah digunakan.';
        }
        if (mb_strlen($values['deskripsi']) > 1000) {
            $errors['deskripsi'] = 'Deskripsi maksimal 1000 karakter.';
        }
        return ['values' => $values, 'errors' => $errors];
    }
    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function paginate(string $q, int $page): array
    {
        $page = max(1, $page);
        $q = mb_substr(trim($q), 0, 100);
        $where = $q === '' ? '' : ' WHERE (k.nama LIKE :qn OR k.deskripsi LIKE :qd)';
        $count = $this->db->prepare('SELECT COUNT(*) FROM kategori k' . $where);
        if ($q !== '') {
            $count->bindValue(':qn', '%' . $q . '%');
            $count->bindValue(':qd', '%' . $q . '%');
        }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $sql = 'SELECT k.id, k.nama, k.deskripsi, COUNT(a.id) AS jumlah_alat
                FROM kategori k LEFT JOIN alat a ON a.kategori_id = k.id' . $where
            . ' GROUP BY k.id, k.nama, k.deskripsi ORDER BY k.nama, k.id
                LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        if ($q !== '') {
            $stmt->bindValue(':qn', '%' . $q . '%');
            $stmt->bindValue(':qd', '%' . $q . '%');
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
        $stmt = $this->db->prepare('SELECT id, nama, deskripsi FROM kategori WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
    /** @param array<string, string> $values */
    public function create(array $values, int $actorId): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO kategori (nama, deskripsi) VALUES (?, ?)');
            $stmt->execute([$values['nama'], $values['deskripsi'] === '' ? null : $values['deskripsi']]);
            $id = (int) $this->db->lastInsertId();
            $this->logs->record($actorId, 'create', 'kategori', $id, 'Tambah kategori: ' . $values['nama']);
            $this->db->commit();
            return $id;
        } catch (PDOException $exception) {
            $this->db->rollBack();
            throw $this->translate($exception, 'Nama kategori sudah digunakan.');
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
    /** @param array<string, string> $values */
    public function update(int $id, array $values, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE kategori SET nama = ?, deskripsi = ? WHERE id = ?');
            $stmt->execute([$values['nama'], $values['deskripsi'] === '' ? null : $values['deskripsi'], $id]);
            $this->logs->record($actorId, 'update', 'kategori', $id, 'Ubah kategori: ' . $values['nama']);
            $this->db->commit();
        } catch (PDOException $exception) {
            $this->db->rollBack();
            throw $this->translate($exception, 'Nama kategori sudah digunakan.');
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
    /**
     * Delete only when no alat references the row. The FK is ON DELETE RESTRICT,
     * so a concurrent insert is still refused by the database.
     *
     * @return 'deleted'|'referenced'|'missing'
     */
    public function delete(int $id, int $actorId): string
    {
        $this->db->beginTransaction();
        try {
            // Lock the row first so a concurrent alat insert cannot slip between
            // the reference check and the delete.
            $lock = $this->db->prepare('SELECT id FROM kategori WHERE id = ? FOR UPDATE');
            $lock->execute([$id]);
            if ($lock->fetchColumn() === false) {
                $this->db->rollBack();
                return 'missing';
            }
            $check = $this->db->prepare('SELECT COUNT(*) FROM alat WHERE kategori_id = ?');
            $check->execute([$id]);
            if ((int) $check->fetchColumn() > 0) {
                $this->db->rollBack();
                return 'referenced';
            }
            $stmt = $this->db->prepare('DELETE FROM kategori WHERE id = ?');
            $stmt->execute([$id]);
            $this->logs->record($actorId, 'delete', 'kategori', $id, 'Hapus kategori');
            $this->db->commit();
            return 'deleted';
        } catch (PDOException $exception) {
            $this->db->rollBack();
            if ($exception->getCode() === '23000') {
                return 'referenced';
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
    private function nameTaken(string $nama, ?int $id): bool
    {
        $sql = 'SELECT COUNT(*) FROM kategori WHERE nama = ?';
        $params = [$nama];
        if ($id !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $id;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }
    private function translate(PDOException $exception, string $duplicate): \Throwable
    {
        return $exception->getCode() === '23000' ? new DomainException($duplicate) : $exception;
    }
}
