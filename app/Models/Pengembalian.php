<?php
declare(strict_types=1);
namespace App\Models;

use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Return requests and physical reception. A request never moves stock; a
 * verified reception locks tool -> loan -> return and returns the borrowed
 * quantity exactly once — only for condition "baik". Damaged or lost items stay
 * out of available stock. Status audit stays with the database trigger.
 */
final class Pengembalian
{
    public const PER_PAGE = 10;
    private const LOAN_STATUSES = "('approved', 'return_requested', 'returned')";

    public function __construct(private PDO $db, private Pengaturan $pengaturan)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function own(int $userId, int $page): array
    {
        return $this->paginate(
            'SELECT p.id, p.kode, a.nama AS alat_nama, p.jumlah, p.tanggal_pinjam,
                    p.tanggal_jatuh_tempo, p.status, pg.tanggal_pengajuan,
                    pg.tanggal_diterima, COALESCE(pg.denda, 0) AS denda
             FROM peminjaman p
             JOIN alat a ON a.id = p.alat_id
             LEFT JOIN pengembalian pg ON pg.peminjaman_id = p.id
             WHERE p.user_id = :owner AND p.status IN ' . self::LOAN_STATUSES . '
             ORDER BY p.created_at DESC, p.id DESC',
            'SELECT COUNT(*) FROM peminjaman p
             WHERE p.user_id = :owner AND p.status IN ' . self::LOAN_STATUSES,
            [':owner' => $userId],
            $page
        );
    }

    /**
     * Petugas monitoring. "active" is the default lane: approved plus requests
     * waiting for physical reception.
     *
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int}
     */
    public function monitoring(string $status, int $page, string $q = ''): array
    {
        $where = match ($status) {
            'return_requested' => "p.status = 'return_requested'",
            'returned' => "p.status = 'returned'",
            'all' => 'p.status IN ' . self::LOAN_STATUSES,
            default => "p.status IN ('approved', 'return_requested')",
        };
        $params = [];
        $q = mb_substr(trim($q), 0, 100);
        if ($q !== '') {
            $where .= ' AND (p.kode LIKE :qk OR u.name LIKE :qn OR a.nama LIKE :qa)';
            $params[':qk'] = '%' . $q . '%';
            $params[':qn'] = '%' . $q . '%';
            $params[':qa'] = '%' . $q . '%';
        }
        $from = ' FROM peminjaman p
                 JOIN users u ON u.id = p.user_id
                 JOIN alat a ON a.id = p.alat_id
                 LEFT JOIN pengembalian pg ON pg.peminjaman_id = p.id
                 WHERE ' . $where;
        return $this->paginate(
            'SELECT p.id, p.kode, u.name AS peminjam_nama, a.nama AS alat_nama, p.jumlah,
                    p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
                    pg.tanggal_pengajuan, pg.tanggal_diterima, COALESCE(pg.denda, 0) AS denda,
                    pg.kondisi, COALESCE(pg.hari_terlambat, 0) AS hari_terlambat'
                . $from
                . ' ORDER BY p.tanggal_jatuh_tempo ASC, p.id ASC',
            'SELECT COUNT(*)' . $from,
            $params,
            $page
        );
    }

    /** @return array<string, mixed>|null */
    public function findForVerify(int $loanId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.kode, p.user_id, u.name AS peminjam_nama, a.nama AS alat_nama,
                    p.jumlah, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
                    pg.tanggal_pengajuan, pg.tanggal_diterima, pg.kondisi,
                    pg.hari_terlambat, pg.denda, a.stok_total, a.stok_tersedia
             FROM peminjaman p
             JOIN users u ON u.id = p.user_id
             JOIN alat a ON a.id = p.alat_id
             LEFT JOIN pengembalian pg ON pg.peminjaman_id = p.id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$loanId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Borrower requests one full return. The loan row is locked so duplicate
     * requests serialize; stock is untouched until Petugas verifies reception.
     *
     * @return 'created'|'missing'|'forbidden'|'not_approved'|'duplicate'
     */
    public function requestReturn(int $loanId, int $userId, int $actorId): string
    {
        $lookup = $this->db->prepare('SELECT id FROM peminjaman WHERE id = ? LIMIT 1');
        $lookup->execute([$loanId]);
        if ($lookup->fetchColumn() === false) {
            return 'missing';
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT id, user_id, jumlah, status FROM peminjaman WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$loanId]);
            $loan = $stmt->fetch();
            if (!is_array($loan)) {
                $this->db->rollBack();
                return 'missing';
            }
            if ((int) $loan['user_id'] !== $userId) {
                $this->db->rollBack();
                return 'forbidden';
            }
            if ((string) $loan['status'] !== 'approved') {
                $this->db->rollBack();
                return 'not_approved';
            }

            try {
                $insert = $this->db->prepare(
                    'INSERT INTO pengembalian (peminjaman_id, tanggal_pengajuan, jumlah, kondisi)
                     VALUES (?, NOW(), ?, \'baik\')'
                );
                $insert->execute([$loanId, (int) $loan['jumlah']]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $this->db->rollBack();
                    return 'duplicate';
                }
                throw $exception;
            }

            $this->db->prepare('SET @sparta_actor_id = ?')->execute([$actorId]);
            $update = $this->db->prepare(
                "UPDATE peminjaman SET status = 'return_requested'
                 WHERE id = ? AND status = 'approved'"
            );
            $update->execute([$loanId]);
            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return 'not_approved';
            }
            $this->db->commit();
            return 'created';
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
     * Verify reception. Rows are locked tool first, then loan, then return, so
     * concurrent verifications cannot double stock. The accepted date is
     * revalidated against the locked loan and request row.
     *
     * @return 'verified'|'missing'|'no_record'|'not_requested'|'conflict'|'invalid_date'|'stock_conflict'
     */
    public function verify(int $loanId, int $actorId, string $acceptedDate, string $kondisi): string
    {
        $lookup = $this->db->prepare('SELECT alat_id FROM peminjaman WHERE id = ? LIMIT 1');
        $lookup->execute([$loanId]);
        $alatId = $lookup->fetchColumn();
        if ($alatId === false) {
            return 'missing';
        }

        $this->db->beginTransaction();
        try {
            $tool = $this->db->prepare(
                'SELECT id, stok_total, stok_tersedia FROM alat WHERE id = ? FOR UPDATE'
            );
            $tool->execute([(int) $alatId]);
            $alat = $tool->fetch();
            if (!is_array($alat)) {
                $this->db->rollBack();
                return 'missing';
            }

            $loanStmt = $this->db->prepare(
                'SELECT id, jumlah, status, tanggal_pinjam, tanggal_jatuh_tempo
                 FROM peminjaman WHERE id = ? FOR UPDATE'
            );
            $loanStmt->execute([$loanId]);
            $loan = $loanStmt->fetch();
            if (!is_array($loan)) {
                $this->db->rollBack();
                return 'missing';
            }

            $returnStmt = $this->db->prepare(
                'SELECT id, tanggal_pengajuan, tanggal_diterima
                 FROM pengembalian WHERE peminjaman_id = ? FOR UPDATE'
            );
            $returnStmt->execute([$loanId]);
            $return = $returnStmt->fetch();
            if (!is_array($return)) {
                $this->db->rollBack();
                return 'no_record';
            }
            if ((string) $loan['status'] !== 'return_requested') {
                $this->db->rollBack();
                return 'not_requested';
            }
            if ($return['tanggal_diterima'] !== null) {
                $this->db->rollBack();
                return 'conflict';
            }

            $pinjam = substr((string) $loan['tanggal_pinjam'], 0, 10);
            $pengajuan = substr((string) $return['tanggal_pengajuan'], 0, 10);
            if (!self::isDate($acceptedDate)
                || $acceptedDate < $pinjam
                || $acceptedDate < $pengajuan
                || $acceptedDate > date('Y-m-d')) {
                $this->db->rollBack();
                return 'invalid_date';
            }

            $jumlah = (int) $loan['jumlah'];
            $due = (string) $loan['tanggal_jatuh_tempo'];
            $hariTerlambat = self::lateDays($due, $acceptedDate);
            $dendaStmt = $this->db->prepare('SELECT fn_hitung_denda(?, ?, ?, ?)');
            $dendaStmt->execute([$due, $acceptedDate, $jumlah, $this->pengaturan->tarifDenda()]);
            $denda = $dendaStmt->fetchColumn();
            if ($denda === false) {
                $denda = 0;
            }

            $this->db->prepare('SET @sparta_actor_id = ?')->execute([$actorId]);
            $receive = $this->db->prepare(
                'UPDATE pengembalian
                 SET tanggal_diterima = ?, jumlah = ?, kondisi = ?, hari_terlambat = ?,
                     denda = ?, verified_by = ?
                 WHERE id = ? AND tanggal_diterima IS NULL'
            );
            $receive->execute([
                $acceptedDate . ' 00:00:00',
                $jumlah,
                $kondisi,
                $hariTerlambat,
                $denda,
                $actorId,
                (int) $return['id'],
            ]);
            if ($receive->rowCount() !== 1) {
                $this->db->rollBack();
                return 'conflict';
            }

            $increment = $this->db->prepare(
                'UPDATE alat SET stok_tersedia = stok_tersedia + ?
                 WHERE id = ? AND stok_tersedia + ? <= stok_total'
            );
            $restore = self::restoredStock($kondisi, $jumlah);
            if ($restore > 0) {
                $increment->execute([$restore, (int) $alat['id'], $restore]);
                if ($increment->rowCount() !== 1) {
                    $this->db->rollBack();
                    return 'stock_conflict';
                }
            }

            $update = $this->db->prepare(
                "UPDATE peminjaman SET status = 'returned'
                 WHERE id = ? AND status = 'return_requested'"
            );
            $update->execute([$loanId]);
            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return 'not_requested';
            }

            $this->db->commit();
            return 'verified';
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
     * Admin return records (FR-07): pending reception vs already verified.
     *
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int}
     */
    public function adminPaginate(string $q, string $status, int $page): array
    {
        $where = [];
        $params = [];
        $q = mb_substr(trim($q), 0, 100);
        $where[] = match ($status) {
            'pending' => 'pg.tanggal_diterima IS NULL',
            'verified' => 'pg.tanggal_diterima IS NOT NULL',
            default => '1 = 1',
        };
        if ($q !== '') {
            $where[] = '(p.kode LIKE :qk OR u.name LIKE :qn OR a.nama LIKE :qa)';
            $params[':qk'] = '%' . $q . '%';
            $params[':qn'] = '%' . $q . '%';
            $params[':qa'] = '%' . $q . '%';
        }
        $from = ' FROM pengembalian pg
                  JOIN peminjaman p ON p.id = pg.peminjaman_id
                  JOIN users u ON u.id = p.user_id
                  JOIN alat a ON a.id = p.alat_id
                  WHERE ' . implode(' AND ', $where);

        return $this->paginate(
            'SELECT p.id AS loan_id, p.kode, u.name AS peminjam_nama, a.nama AS alat_nama,
                    pg.jumlah, p.status, pg.tanggal_pengajuan, pg.tanggal_diterima, pg.kondisi,
                    pg.hari_terlambat, pg.denda'
                . $from
                . ' ORDER BY pg.tanggal_pengajuan DESC, pg.id DESC',
            'SELECT COUNT(*)' . $from,
            $params,
            $page
        );
    }

    /** @return array<string, mixed>|null */
    public function adminFind(int $loanId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id AS loan_id, p.kode, p.user_id, u.name AS peminjam_nama, a.nama AS alat_nama,
                    p.jumlah AS jumlah_pinjam, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
                    pg.id AS return_id, pg.jumlah, pg.tanggal_pengajuan, pg.tanggal_diterima,
                    pg.kondisi, pg.hari_terlambat, pg.denda
             FROM pengembalian pg
             JOIN peminjaman p ON p.id = pg.peminjaman_id
             JOIN users u ON u.id = p.user_id
             JOIN alat a ON a.id = p.alat_id
             WHERE pg.peminjaman_id = ? LIMIT 1'
        );
        $stmt->execute([$loanId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Correct a verified reception. Condition changes move stock by the delta
     * between the old and new restorable quantity; date/condition metadata and
     * the fine are recomputed from the stored loan dates. The delta update is
     * guarded so stok_tersedia can never leave 0..stok_total.
     *
     * @return 'updated'|'missing'|'not_verified'|'invalid_date'|'stock_conflict'
     */
    public function adminUpdate(int $loanId, string $acceptedDate, string $kondisi, int $actorId): string
    {
        $this->db->beginTransaction();
        try {
            // Lock the tool before the loan, same order as verify(), to avoid deadlocks.
            $toolStmt = $this->db->prepare(
                'SELECT id, stok_total, stok_tersedia FROM alat
                 WHERE id = (SELECT alat_id FROM peminjaman WHERE id = ?) FOR UPDATE'
            );
            $toolStmt->execute([$loanId]);
            $alat = $toolStmt->fetch();
            if (!is_array($alat)) {
                $this->db->rollBack();

                return 'missing';
            }

            $loanStmt = $this->db->prepare(
                'SELECT id, jumlah, status, tanggal_pinjam, tanggal_jatuh_tempo
                 FROM peminjaman WHERE id = ? FOR UPDATE'
            );
            $loanStmt->execute([$loanId]);
            $loan = $loanStmt->fetch();

            $returnStmt = $this->db->prepare(
                'SELECT id, tanggal_pengajuan, tanggal_diterima, jumlah, kondisi, hari_terlambat, denda
                 FROM pengembalian WHERE peminjaman_id = ? FOR UPDATE'
            );
            $returnStmt->execute([$loanId]);
            $return = $returnStmt->fetch();
            if (!is_array($loan) || !is_array($return)) {
                $this->db->rollBack();

                return 'missing';
            }
            if ((string) $loan['status'] !== 'returned' || $return['tanggal_diterima'] === null) {
                $this->db->rollBack();

                return 'not_verified';
            }

            $pinjam = substr((string) $loan['tanggal_pinjam'], 0, 10);
            $pengajuan = substr((string) $return['tanggal_pengajuan'], 0, 10);
            if (!self::isDate($acceptedDate)
                || $acceptedDate < $pinjam
                || $acceptedDate < $pengajuan
                || $acceptedDate > date('Y-m-d')) {
                $this->db->rollBack();

                return 'invalid_date';
            }

            $jumlah = (int) $loan['jumlah'];
            $due = (string) $loan['tanggal_jatuh_tempo'];
            // Notes-only corrections keep the historical fine; a date correction reprices at the current tariff.
            if (substr((string) $return['tanggal_diterima'], 0, 10) === $acceptedDate) {
                $hariTerlambat = (int) $return['hari_terlambat'];
                $denda = (string) $return['denda'];
            } else {
                $hariTerlambat = self::lateDays($due, $acceptedDate);
                $dendaStmt = $this->db->prepare('SELECT fn_hitung_denda(?, ?, ?, ?)');
                $dendaStmt->execute([$due, $acceptedDate, $jumlah, $this->pengaturan->tarifDenda()]);
                $denda = $dendaStmt->fetchColumn();
                if ($denda === false) {
                    $denda = 0;
                }
            }

            $update = $this->db->prepare(
                'UPDATE pengembalian
                 SET tanggal_diterima = ?, kondisi = ?, hari_terlambat = ?, denda = ?
                 WHERE id = ?'
            );
            $update->execute([
                $acceptedDate . ' 00:00:00',
                $kondisi,
                $hariTerlambat,
                $denda,
                (int) $return['id'],
            ]);
            $delta = self::restoredStock($kondisi, $jumlah)
                - self::restoredStock((string) $return['kondisi'], $jumlah);
            if ($delta !== 0) {
                $adjust = $this->db->prepare(
                    'UPDATE alat SET stok_tersedia = stok_tersedia + ?
                     WHERE id = ? AND stok_tersedia + ? BETWEEN 0 AND stok_total'
                );
                $adjust->execute([$delta, (int) $alat['id'], $delta]);
                if ($adjust->rowCount() !== 1) {
                    $this->db->rollBack();

                    return 'stock_conflict';
                }
            }
            $this->db->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
                 VALUES (?, \'update\', \'pengembalian\', ?, ?)'
            )->execute([$actorId, $loanId, 'Koreksi data pengembalian']);
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
     * Delete an unverified request and return the loan to "approved". Verified
     * receptions are kept because deleting them would desync stock history.
     *
     * @return 'deleted'|'missing'|'not_pending'|'verified_conflict'
     */
    public function adminDelete(int $loanId, int $actorId): string
    {
        $this->db->beginTransaction();
        try {
            $loanStmt = $this->db->prepare('SELECT kode, status FROM peminjaman WHERE id = ? FOR UPDATE');
            $loanStmt->execute([$loanId]);
            $loan = $loanStmt->fetch();

            $returnStmt = $this->db->prepare(
                'SELECT id, tanggal_diterima FROM pengembalian WHERE peminjaman_id = ? FOR UPDATE'
            );
            $returnStmt->execute([$loanId]);
            $return = $returnStmt->fetch();
            if (!is_array($loan) || !is_array($return)) {
                $this->db->rollBack();

                return 'missing';
            }
            if ((string) $loan['status'] !== 'return_requested' || $return['tanggal_diterima'] !== null) {
                $this->db->rollBack();

                return 'verified_conflict';
            }

            $this->db->prepare('DELETE FROM pengembalian WHERE id = ?')->execute([(int) $return['id']]);
            $this->db->prepare('SET @sparta_actor_id = ?')->execute([$actorId]);
            $update = $this->db->prepare(
                "UPDATE peminjaman SET status = 'approved' WHERE id = ? AND status = 'return_requested'"
            );
            $update->execute([$loanId]);
            if ($update->rowCount() !== 1) {
                $this->db->rollBack();

                return 'not_pending';
            }
            $this->db->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
                 VALUES (?, \'delete\', \'pengembalian\', ?, ?)'
            )->execute([$actorId, $loanId, 'Hapus pengajuan pengembalian: ' . $loan['kode']]);
            $this->db->commit();

            return 'deleted';
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
     * @param array<string, int|string> $params
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int}
     */
    private function paginate(string $select, string $countSql, array $params, int $page): array
    {
        $page = max(1, $page);
        $count = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $count->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $stmt = $this->db->prepare($select . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
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

    /** Whole calendar days late; independent of seconds or DST between dates. */
    private static function lateDays(string $due, string $accepted): int
    {
        $diff = (new DateTimeImmutable($due))->diff(new DateTimeImmutable($accepted));
        return $diff->invert === 1 ? 0 : $diff->days;
    }

    /**
     * Only an undamaged return re-enters available stock. Damaged or lost items
     * stay out of circulation until the physical asset is resolved by an Admin.
     */
    private static function restoredStock(string $kondisi, int $jumlah): int
    {
        return $kondisi === 'baik' ? max(0, $jumlah) : 0;
    }
    private static function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
