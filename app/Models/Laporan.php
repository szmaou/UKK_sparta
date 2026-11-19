<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use PDO;

/**
 * Loan recap for the Petugas report (FR-11). Read-only aggregate over the same
 * joins as sp_rekap_peminjaman; rows are capped so a wide range cannot exhaust
 * memory. Printing reuses this data, so numbers always match the database.
 */
final class Laporan
{
    public const STATUSES = ['', 'pending', 'approved', 'rejected', 'return_requested', 'returned'];
    public const MAX_ROWS = 1000;

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{values: array{from: string, to: string, status: string}, errors: array<string, string>}
     */
    public function parse(mixed $from, mixed $to, mixed $status): array
    {
        $values = [
            'from' => is_string($from) ? trim($from) : '',
            'to' => is_string($to) ? trim($to) : '',
            'status' => is_string($status) ? trim($status) : '',
        ];
        $errors = [];
        $today = date('Y-m-d');
        if (!$this->isDate($values['from'])) {
            $errors['from'] = 'Tanggal awal tidak valid.';
        }
        if (!$this->isDate($values['to'])) {
            $errors['to'] = 'Tanggal akhir tidak valid.';
        }
        if (!isset($errors['from']) && !isset($errors['to']) && $values['from'] > $values['to']) {
            $errors['to'] = 'Tanggal akhir tidak boleh sebelum tanggal awal.';
        }
        if (!isset($errors['to']) && $values['to'] > $today) {
            $values['to'] = $today;
        }
        if (!in_array($values['status'], self::STATUSES, true)) {
            $errors['status'] = 'Status laporan tidak valid.';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @return array{items: list<array<string, mixed>>, summary: array{total: int, unit: int, denda: float, per_status: array<string, int>}, truncated: bool}
     */
    public function rekap(string $from, string $to, string $status): array
    {
        $params = [':from' => $from, ':to' => $to];
        $statusClause = '';
        if ($status !== '') {
            $statusClause = ' AND p.status = :status';
            $params[':status'] = $status;
        }
        $stmt = $this->db->prepare(
            'SELECT p.kode, u.name AS peminjam, a.nama AS alat, k.nama AS kategori,
                    p.jumlah, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
                    COALESCE(pg.denda, 0) AS denda
             FROM peminjaman p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN alat a ON a.id = p.alat_id
             INNER JOIN kategori k ON k.id = a.kategori_id
             LEFT JOIN pengembalian pg ON pg.peminjaman_id = p.id
             WHERE p.tanggal_pinjam BETWEEN :from AND :to' . $statusClause . '
             ORDER BY p.tanggal_pinjam, p.id
             LIMIT ' . (self::MAX_ROWS + 1)
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $truncated = count($rows) > self::MAX_ROWS;
        if ($truncated) {
            $rows = array_slice($rows, 0, self::MAX_ROWS);
        }

        $summary = ['total' => count($rows), 'unit' => 0, 'denda' => 0.0, 'per_status' => []];
        foreach ($rows as $row) {
            $summary['unit'] += (int) $row['jumlah'];
            $summary['denda'] += (float) $row['denda'];
            $key = (string) $row['status'];
            $summary['per_status'][$key] = ($summary['per_status'][$key] ?? 0) + 1;
        }

        return ['items' => array_values($rows), 'summary' => $summary, 'truncated' => $truncated];
    }

    private function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
