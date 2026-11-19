<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Role-scoped KPI counters for the dashboard. Every value is a live database
 * aggregate; the dashboard never invents numbers.
 */
final class Dashboard
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array{label: string, value: int, href: string, desc: string}>
     */
    public function forRole(string $role, int $userId): array
    {
        return match (strtolower($role)) {
            'admin' => $this->admin(),
            'petugas' => $this->petugas(),
            default => $this->peminjam($userId),
        };
    }

    /** @return list<array{label: string, value: int, href: string, desc: string}> */
    private function admin(): array
    {
        $row = $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM alat WHERE is_active = 1) AS alat_aktif,
                (SELECT COUNT(*) FROM users WHERE is_active = 1) AS pengguna_aktif,
                (SELECT COUNT(*) FROM peminjaman WHERE status IN (\'approved\', \'return_requested\')) AS peminjaman_aktif,
                (SELECT COUNT(*) FROM peminjaman WHERE status = \'pending\') AS menunggu_persetujuan'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            [
                'label' => 'Alat aktif',
                'value' => (int) ($row['alat_aktif'] ?? 0),
                'href' => '/admin/alat',
                'desc' => 'Item inventaris yang dapat dipinjam',
            ],
            [
                'label' => 'Pengguna aktif',
                'value' => (int) ($row['pengguna_aktif'] ?? 0),
                'href' => '/admin/users',
                'desc' => 'Akun yang dapat masuk sistem',
            ],
            [
                'label' => 'Peminjaman aktif',
                'value' => (int) ($row['peminjaman_aktif'] ?? 0),
                'href' => '/admin/peminjaman',
                'desc' => 'Sedang dipinjam atau menunggu verifikasi',
            ],
            [
                'label' => 'Menunggu persetujuan',
                'value' => (int) ($row['menunggu_persetujuan'] ?? 0),
                'href' => '/admin/peminjaman?status=pending',
                'desc' => 'Pengajuan yang belum diputuskan',
            ],
        ];
    }

    /** @return list<array{label: string, value: int, href: string, desc: string}> */
    private function petugas(): array
    {
        $row = $this->db->query(
            'SELECT
                COALESCE(SUM(status = \'pending\'), 0) AS menunggu_persetujuan,
                COALESCE(SUM(status = \'approved\'), 0) AS sedang_dipinjam,
                COALESCE(SUM(status = \'return_requested\'), 0) AS menunggu_verifikasi,
                COALESCE(SUM(status = \'approved\' AND tanggal_jatuh_tempo < CURDATE()), 0) AS terlambat
             FROM peminjaman'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            [
                'label' => 'Menunggu persetujuan',
                'value' => (int) ($row['menunggu_persetujuan'] ?? 0),
                'href' => '/petugas/persetujuan',
                'desc' => 'Pengajuan yang perlu diputuskan',
            ],
            [
                'label' => 'Sedang dipinjam',
                'value' => (int) ($row['sedang_dipinjam'] ?? 0),
                'href' => '/petugas/pengembalian?status=approved',
                'desc' => 'Alat masih di tangan peminjam',
            ],
            [
                'label' => 'Menunggu verifikasi',
                'value' => (int) ($row['menunggu_verifikasi'] ?? 0),
                'href' => '/petugas/pengembalian?status=return_requested',
                'desc' => 'Pengembalian menunggu pemeriksaan fisik',
            ],
            [
                'label' => 'Terlambat',
                'value' => (int) ($row['terlambat'] ?? 0),
                'href' => '/petugas/pengembalian?status=approved',
                'desc' => 'Melewati tanggal jatuh tempo',
            ],
        ];
    }

    /** @return list<array{label: string, value: int, href: string, desc: string}> */
    private function peminjam(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COALESCE(SUM(status = \'pending\'), 0) AS menunggu_persetujuan,
                COALESCE(SUM(status = \'approved\'), 0) AS disetujui,
                COALESCE(SUM(status = \'return_requested\'), 0) AS menunggu_verifikasi,
                COALESCE(SUM(status = \'returned\'), 0) AS selesai
             FROM peminjaman
             WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            [
                'label' => 'Menunggu persetujuan',
                'value' => (int) ($row['menunggu_persetujuan'] ?? 0),
                'href' => '/peminjam/peminjaman',
                'desc' => 'Pengajuan Anda yang sedang ditinjau',
            ],
            [
                'label' => 'Disetujui',
                'value' => (int) ($row['disetujui'] ?? 0),
                'href' => '/peminjam/peminjaman',
                'desc' => 'Alat yang sedang Anda pinjam',
            ],
            [
                'label' => 'Menunggu verifikasi',
                'value' => (int) ($row['menunggu_verifikasi'] ?? 0),
                'href' => '/peminjam/pengembalian',
                'desc' => 'Pengembalian menunggu pemeriksaan petugas',
            ],
            [
                'label' => 'Selesai',
                'value' => (int) ($row['selesai'] ?? 0),
                'href' => '/peminjam/peminjaman',
                'desc' => 'Riwayat peminjaman yang telah dikembalikan',
            ],
        ];
    }
}
