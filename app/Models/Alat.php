<?php
declare(strict_types=1);
namespace App\Models;
use DomainException;
use PDO;
use PDOException;
/**
 * Alat master data. Stock invariant: stok_tersedia = stok_total - unit yang
 * sedang dipinjam/dialokasikan. A form may only submit stok_total; the borrowed
 * amount is read under a row lock and preserved. stok_tersedia is never taken
 * from request input.
 */
final class Alat
{
    public const PER_PAGE = 10;
    private const MAX_UNSIGNED = 4294967295;
    public function __construct(private PDO $db, private ActivityLog $logs, private Kategori $kategori)
    {
    }
    /**
     * @param array<string, mixed> $input
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function parse(array $input, ?int $id = null): array
    {
        $values = [
            'id' => $id === null ? '' : (string) $id,
            'kode' => is_string($input['kode'] ?? null) ? trim($input['kode']) : '',
            'nama' => is_string($input['nama'] ?? null) ? trim($input['nama']) : '',
            'kategori_id' => is_string($input['kategori_id'] ?? null) ? trim($input['kategori_id']) : '',
            'deskripsi' => is_string($input['deskripsi'] ?? null) ? trim($input['deskripsi']) : '',
            'stok_total' => is_string($input['stok_total'] ?? null) ? trim($input['stok_total']) : '',
            // Missing status defaults to active only on create; any other value,
            // including an array, must be an explicit "0" or "1".
            'is_active' => match (true) {
                !array_key_exists('is_active', $input) => $id === null ? '1' : '',
                is_string($input['is_active']) => trim($input['is_active']),
                default => '',
            },
        ];
        $errors = [];
        if ($values['kode'] === '') {
            $errors['kode'] = 'Kode alat wajib diisi.';
        } elseif (mb_strlen($values['kode']) > 30) {
            $errors['kode'] = 'Kode alat maksimal 30 karakter.';
        } elseif ($this->codeTaken($values['kode'], $id)) {
            $errors['kode'] = 'Kode alat sudah digunakan. Gunakan kode lain.';
        }
        if ($values['nama'] === '') {
            $errors['nama'] = 'Nama alat wajib diisi.';
        } elseif (mb_strlen($values['nama']) > 150) {
            $errors['nama'] = 'Nama alat maksimal 150 karakter.';
        }
        $kategoriId = $this->positiveId($values['kategori_id']);
        if ($kategoriId === null) {
            $errors['kategori_id'] = 'Kategori wajib dipilih.';
        } elseif ($this->kategori->find($kategoriId) === null) {
            $errors['kategori_id'] = 'Kategori tidak ditemukan.';
        }
        if (mb_strlen($values['deskripsi']) > 1000) {
            $errors['deskripsi'] = 'Deskripsi maksimal 1000 karakter.';
        }
        if ($values['stok_total'] === '' || preg_match('/^\d{1,10}$/', $values['stok_total']) !== 1
            || (int) $values['stok_total'] > self::MAX_UNSIGNED) {
            $errors['stok_total'] = 'Stok total harus bilangan bulat 0 atau lebih.';
        }
        if (!in_array($values['is_active'], ['0', '1'], true)) {
            $errors['is_active'] = 'Status alat tidak valid.';
        }
        return ['values' => $values, 'errors' => $errors];
    }
    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function paginate(string $q, int $page): array
    {
        $page = max(1, $page);
        $q = mb_substr(trim($q), 0, 100);
        $compare = $q === '' ? '' : ' WHERE (a.kode LIKE :qk OR a.nama LIKE :qn)';
        $count = $this->db->prepare('SELECT COUNT(*) FROM alat a' . $compare);
        if ($q !== '') {
            $count->bindValue(':qk', '%' . $q . '%');
            $count->bindValue(':qn', '%' . $q . '%');
        }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $stmt = $this->db->prepare(
            'SELECT a.id, a.kode, a.nama, a.kategori_id, k.nama AS kategori_nama, a.deskripsi,
                    a.stok_total, a.stok_tersedia, a.stok_total - a.stok_tersedia AS stok_dipinjam,
                    a.is_active
             FROM alat a JOIN kategori k ON k.id = a.kategori_id' . $compare
            . ' ORDER BY a.nama, a.id LIMIT :limit OFFSET :offset'
        );
        if ($q !== '') {
            $stmt->bindValue(':qk', '%' . $q . '%');
            $stmt->bindValue(':qn', '%' . $q . '%');
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
            'SELECT id, kode, nama, kategori_id, deskripsi, stok_total, stok_tersedia,
                    stok_total - stok_tersedia AS stok_dipinjam, is_active
             FROM alat WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
    /**
     * Create with stok_tersedia equal to stok_total. Insert and audit commit together.
     *
     * @param array<string, string> $values parsed, error-free values
     */
    public function create(array $values, int $actorId): int
    {
        $total = (int) $values['stok_total'];
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO alat (kode, kategori_id, nama, deskripsi, stok_total, stok_tersedia, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $values['kode'],
                (int) $values['kategori_id'],
                $values['nama'],
                $values['deskripsi'] === '' ? null : $values['deskripsi'],
                $total,
                $total,
                (int) $values['is_active'],
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->logs->record($actorId, 'create', 'alat', $id, 'Tambah alat: ' . $values['kode']);
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
     * Update master data. The alat row is locked before reading the borrowed
     * amount; the new total must cover it, and stok_tersedia is recomputed.
     *
     * @param array<string, string> $values parsed, error-free values
     * @return array{stok_dipinjam: int, stok_tersedia: int}|null null when the row vanished
     */
    public function update(int $id, array $values, int $actorId): ?array
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare(
                'SELECT stok_total, stok_tersedia FROM alat WHERE id = ? FOR UPDATE'
            );
            $lock->execute([$id]);
            $row = $lock->fetch();
            if (!is_array($row)) {
                $this->db->rollBack();
                return null;
            }
            $borrowed = (int) $row['stok_total'] - (int) $row['stok_tersedia'];
            $total = (int) $values['stok_total'];
            if ($total < $borrowed) {
                $this->db->rollBack();
                throw new DomainException(
                    'Stok total tidak boleh kurang dari ' . $borrowed . ' unit yang sedang dipinjam.'
                );
            }
            $stmt = $this->db->prepare(
                'UPDATE alat SET kode = ?, kategori_id = ?, nama = ?, deskripsi = ?,
                        stok_total = ?, stok_tersedia = ?, is_active = ? WHERE id = ?'
            );
            $stmt->execute([
                $values['kode'],
                (int) $values['kategori_id'],
                $values['nama'],
                $values['deskripsi'] === '' ? null : $values['deskripsi'],
                $total,
                $total - $borrowed,
                (int) $values['is_active'],
                $id,
            ]);
            $this->logs->record(
                $actorId,
                'update',
                'alat',
                $id,
                'Ubah alat: ' . $values['kode'] . '; stok total ' . $row['stok_total'] . ' -> ' . $total
            );
            $this->db->commit();
            return ['stok_dipinjam' => $borrowed, 'stok_tersedia' => $total - $borrowed];
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
     * Refuse to erase history: a tool referenced by any peminjaman row is
     * deactivated instead (audited). Otherwise the row is deleted.
     *
     * @return 'deleted'|'deactivated'|'missing'
     */
    public function delete(int $id, int $actorId): string
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT kode FROM alat WHERE id = ? FOR UPDATE');
            $lock->execute([$id]);
            $kode = $lock->fetchColumn();
            if ($kode === false) {
                $this->db->rollBack();
                return 'missing';
            }
            $refs = $this->db->prepare('SELECT COUNT(*) FROM peminjaman WHERE alat_id = ?');
            $refs->execute([$id]);
            if ((int) $refs->fetchColumn() > 0) {
                $stmt = $this->db->prepare('UPDATE alat SET is_active = 0 WHERE id = ?');
                $stmt->execute([$id]);
                $this->logs->record(
                    $actorId,
                    'deactivate',
                    'alat',
                    $id,
                    'Nonaktifkan alat ' . $kode . ': sudah dipakai transaksi peminjaman'
                );
                $this->db->commit();
                return 'deactivated';
            }
            $stmt = $this->db->prepare('DELETE FROM alat WHERE id = ?');
            $stmt->execute([$id]);
            $this->logs->record($actorId, 'delete', 'alat', $id, 'Hapus alat: ' . $kode);
            $this->db->commit();
            return 'deleted';
        } catch (PDOException $exception) {
            $this->db->rollBack();
            if ($exception->getCode() === '23000') {
                throw new DomainException('Alat masih direferensikan transaksi. Nonaktifkan alat sebagai gantinya.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
    /** @return list<array{id: int, nama: string}> */
    public function categories(): array
    {
        $stmt = $this->db->query('SELECT id, nama FROM kategori ORDER BY nama, id');
        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'nama' => (string) $row['nama']],
            $stmt->fetchAll()
        );
    }
    /** Positive integer ID from user input; "0", "-1", "1abc" and "" are rejected. */
    public function positiveId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || preg_match('/^[1-9]\d{0,18}$/', $value) !== 1) {
            return null;
        }
        return (int) $value;
    }
    private function codeTaken(string $kode, ?int $id): bool
    {
        $sql = 'SELECT COUNT(*) FROM alat WHERE kode = ?';
        $params = [$kode];
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
        $message = $exception->getMessage();
        if (str_contains($message, 'uq_alat_kode')) {
            return new DomainException('Kode alat sudah digunakan. Gunakan kode lain.');
        }
        if (str_contains($message, 'fk_alat_kategori')) {
            return new DomainException('Kategori tidak ditemukan.');
        }
        return new DomainException('Data alat melanggar aturan integritas.');
    }
}
