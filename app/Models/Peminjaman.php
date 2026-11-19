<?php
declare(strict_types=1);
namespace App\Models;

use PDO;
use Throwable;

/**
 * Loan transactions. Pending rows never reserve stock (BR-03); approval locks
 * the tool first and decrements stok_tersedia exactly once (BR-04, FR-09).
 * Status audit is owned by the database trigger, so decisions only set the
 * trigger's @sparta_actor_id session variable and never insert status_change.
 */
final class Peminjaman
{
    public const PER_PAGE = 10;

    public function __construct(private PDO $db, private ActivityLog $logs)
    {
    }

    /**
     * Insert a pending request for the authenticated borrower. The caller has
     * already validated the tool, quantity, dates and purpose.
     *
     * @param array{alat_id: int, jumlah: int, tanggal_pinjam: string, tanggal_jatuh_tempo: string, tujuan: string} $values
     * @return array{id: int, kode: string}
     */
    public function create(int $userId, array $values, int $actorId): array
    {
        // Random suffix avoids COUNT+1 races; the unique index guards collisions.
        $kode = 'PJM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO peminjaman
                    (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo, status, catatan)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $kode,
                $userId,
                $values['alat_id'],
                $values['jumlah'],
                $values['tanggal_pinjam'],
                $values['tanggal_jatuh_tempo'],
                'pending',
                $values['tujuan'] === '' ? null : $values['tujuan'],
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->logs->record($actorId, 'create', 'peminjaman', $id, 'Buat pengajuan peminjaman: ' . $kode);
            $this->db->commit();
            return ['id' => $id, 'kode' => $kode];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function history(int $userId, int $page): array
    {
        return $this->paginate(
            'SELECT p.id, p.kode, a.nama AS alat_nama, p.jumlah, p.tanggal_pinjam,
                    p.tanggal_jatuh_tempo, p.status, p.catatan, p.created_at
             FROM peminjaman p JOIN alat a ON a.id = p.alat_id
             WHERE p.user_id = :owner
             ORDER BY p.created_at DESC, p.id DESC',
            'SELECT COUNT(*) FROM peminjaman p WHERE p.user_id = :owner',
            [':owner' => $userId],
            $page
        );
    }

    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function pending(int $page): array
    {
        return $this->paginate(
            'SELECT p.id, p.kode, u.name AS peminjam_nama, a.nama AS alat_nama, p.jumlah,
                    p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.catatan, a.stok_tersedia
             FROM peminjaman p
             JOIN users u ON u.id = p.user_id
             JOIN alat a ON a.id = p.alat_id
             WHERE p.status = \'pending\'
             ORDER BY p.created_at ASC, p.id ASC',
            'SELECT COUNT(*) FROM peminjaman p WHERE p.status = \'pending\'',
            [],
            $page
        );
    }

    /**
     * Approve or reject one pending request inside a transaction. The tool row
     * is locked before the loan row, matching Alat update/delete lock order.
     *
     * @return 'approved'|'rejected'|'missing'|'not_pending'|'inactive'|'insufficient'
     */
    public function decide(int $id, string $decision, int $actorId, ?string $note = null): string
    {
        $lookup = $this->db->prepare('SELECT alat_id FROM peminjaman WHERE id = ? LIMIT 1');
        $lookup->execute([$id]);
        $alatId = $lookup->fetchColumn();
        if ($alatId === false) {
            return 'missing';
        }

        $this->db->beginTransaction();
        try {
            $tool = $this->db->prepare(
                'SELECT id, is_active, stok_tersedia FROM alat WHERE id = ? FOR UPDATE'
            );
            $tool->execute([(int) $alatId]);
            $alat = $tool->fetch();
            if (!is_array($alat)) {
                $this->db->rollBack();
                return 'missing';
            }

            $loan = $this->db->prepare(
                'SELECT id, status, jumlah FROM peminjaman WHERE id = ? FOR UPDATE'
            );
            $loan->execute([$id]);
            $row = $loan->fetch();
            if (!is_array($row)) {
                $this->db->rollBack();
                return 'missing';
            }
            if ((string) $row['status'] !== 'pending') {
                $this->db->rollBack();
                return 'not_pending';
            }

            // The trigger reads this user variable for its activity_logs row.
            $this->db->prepare('SET @sparta_actor_id = ?')->execute([$actorId]);

            if ($decision === 'approved') {
                if ((int) $alat['is_active'] !== 1) {
                    $this->db->rollBack();
                    return 'inactive';
                }
                $jumlah = (int) $row['jumlah'];
                if ((int) $alat['stok_tersedia'] < $jumlah) {
                    $this->db->rollBack();
                    return 'insufficient';
                }
                $decrement = $this->db->prepare(
                    'UPDATE alat SET stok_tersedia = stok_tersedia - ?
                     WHERE id = ? AND stok_tersedia >= ?'
                );
                $decrement->execute([$jumlah, (int) $alat['id'], $jumlah]);
                if ($decrement->rowCount() !== 1) {
                    $this->db->rollBack();
                    return 'insufficient';
                }
                $update = $this->db->prepare(
                    'UPDATE peminjaman SET status = \'approved\', approved_by = ?, approved_at = NOW()
                     WHERE id = ? AND status = \'pending\''
                );
                $update->execute([$actorId, $id]);
            } else {
                $update = $this->db->prepare(
                    'UPDATE peminjaman SET status = \'rejected\'
                     WHERE id = ? AND status = \'pending\''
                );
                $update->execute([$id]);
                // Never overwrite the borrower's purpose; keep the decision
                // note as an append-only audit row instead.
                if ($note !== null && $note !== '') {
                    $this->logs->record($actorId, 'reject_note', 'peminjaman', $id, $note);
                }
            }
            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return 'not_pending';
            }
            $this->db->commit();
            return $decision === 'approved' ? 'approved' : 'rejected';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        } finally {
            $this->db->exec('SET @sparta_actor_id = NULL');
        }
    }

    /**
     * Admin transaction list (FR-06): every status, searchable, newest first.
     *
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int}
     */
    public function adminPaginate(string $q, string $status, int $page): array
    {
        $where = [];
        $params = [];
        $q = mb_substr(trim($q), 0, 100);
        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        if ($q !== '') {
            $where[] = '(p.kode LIKE :qk OR u.name LIKE :qn OR a.nama LIKE :qa)';
            $params[':qk'] = '%' . $q . '%';
            $params[':qn'] = '%' . $q . '%';
            $params[':qa'] = '%' . $q . '%';
        }
        $from = ' FROM peminjaman p
                  JOIN users u ON u.id = p.user_id
                  JOIN alat a ON a.id = p.alat_id'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));

        $count = $this->db->prepare('SELECT COUNT(*)' . $from);
        foreach ($params as $key => $value) {
            $count->bindValue($key, $value);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $stmt = $this->db->prepare(
            'SELECT p.id, p.kode, u.name AS peminjam_nama, a.nama AS alat_nama, p.jumlah,
                    p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status, p.catatan, p.created_at,
                    a.stok_tersedia
             ' . $from . '
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
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
    public function adminFind(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.kode, u.name AS peminjam_nama, a.nama AS alat_nama, p.jumlah,
                    p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status, p.catatan,
                    a.stok_tersedia, a.is_active AS alat_aktif
             FROM peminjaman p
             JOIN users u ON u.id = p.user_id
             JOIN alat a ON a.id = p.alat_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Administrative correction (FR-06). Only pending rows may change: stock is
     * untouched while pending, so no availability invariant can break. Approved
     * or returned rows are immutable here by design.
     *
     * @param array{jumlah: int, tanggal_pinjam: string, tanggal_jatuh_tempo: string, catatan: ?string} $values
     * @return 'updated'|'missing'|'not_pending'|'insufficient'
     */
    public function adminUpdate(int $id, array $values, int $actorId): string
    {
        $lookup = $this->db->prepare('SELECT alat_id FROM peminjaman WHERE id = ? LIMIT 1');
        $lookup->execute([$id]);
        $alatId = $lookup->fetchColumn();
        if ($alatId === false) {
            return 'missing';
        }

        $this->db->beginTransaction();
        try {
            $tool = $this->db->prepare('SELECT id, stok_tersedia FROM alat WHERE id = ? FOR UPDATE');
            $tool->execute([(int) $alatId]);
            $alat = $tool->fetch();

            $loan = $this->db->prepare('SELECT id, status FROM peminjaman WHERE id = ? FOR UPDATE');
            $loan->execute([$id]);
            $row = $loan->fetch();
            if (!is_array($row) || !is_array($alat)) {
                $this->db->rollBack();

                return 'missing';
            }
            if ((string) $row['status'] !== 'pending') {
                $this->db->rollBack();

                return 'not_pending';
            }
            if ($values['jumlah'] > (int) $alat['stok_tersedia']) {
                $this->db->rollBack();

                return 'insufficient';
            }

            $update = $this->db->prepare(
                'UPDATE peminjaman
                 SET jumlah = ?, tanggal_pinjam = ?, tanggal_jatuh_tempo = ?, catatan = ?
                 WHERE id = ? AND status = \'pending\''
            );
            $update->execute([
                $values['jumlah'],
                $values['tanggal_pinjam'],
                $values['tanggal_jatuh_tempo'],
                $values['catatan'],
                $id,
            ]);
            $this->logs->record($actorId, 'update', 'peminjaman', $id, 'Koreksi pengajuan peminjaman');
            $this->db->commit();

            return 'updated';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Pending and rejected rows hold no stock, so deleting them cannot corrupt
     * availability. Rows past pending are kept as history.
     *
     * @return 'deleted'|'missing'|'not_deletable'
     */
    public function adminDelete(int $id, int $actorId): string
    {
        $this->db->beginTransaction();
        try {
            $loan = $this->db->prepare('SELECT kode, status FROM peminjaman WHERE id = ? FOR UPDATE');
            $loan->execute([$id]);
            $row = $loan->fetch();
            if (!is_array($row)) {
                $this->db->rollBack();

                return 'missing';
            }
            if (!in_array((string) $row['status'], ['pending', 'rejected'], true)) {
                $this->db->rollBack();

                return 'not_deletable';
            }
            $this->db->prepare('DELETE FROM peminjaman WHERE id = ?')->execute([$id]);
            $this->logs->record($actorId, 'delete', 'peminjaman', $id, 'Hapus peminjaman: ' . $row['kode']);
            $this->db->commit();

            return 'deleted';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, int> $params
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int}
     */
    private function paginate(string $select, string $countSql, array $params, int $page): array    {
        $page = max(1, $page);
        $count = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $count->bindValue($key, $value, PDO::PARAM_INT);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $stmt = $this->db->prepare($select . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
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
}
