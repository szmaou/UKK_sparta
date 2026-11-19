<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Pengembalian;
use App\Services\Auth;
use App\Services\PengembalianService;

/**
 * Admin return maintenance (FR-07). Verified receptions may have condition and
 * reception date corrected; a condition correction applies the stock delta when
 * present. Only unverified requests may be deleted.
 */
final class AdminPengembalianController
{
    private const STATUSES = ['', 'pending', 'verified'];

    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private Pengembalian $returns,
        private PengembalianService $service
    ) {
    }

    public function index(): void
    {
        $q = $this->service->search($_GET['q'] ?? null);
        $status = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : '';
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $page = $this->pageParam();
        $result = $this->returns->adminPaginate($q, $status, $page);
        $this->render('admin/pengembalian/index', [
            'items' => $result['items'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'q' => $q,
            'status' => $status,
            'pageTitle' => 'Data Pengembalian',
        ]);
    }

    public function edit(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        if ($row['tanggal_diterima'] === null) {
            $this->conflict('Pengembalian belum diverifikasi Petugas. Gunakan menu verifikasi pengembalian.');
            return;
        }
        $this->renderForm($row, [
            'tanggal_diterima' => substr((string) ($row['tanggal_diterima'] ?? ''), 0, 10),
            'kondisi' => (string) ($row['kondisi'] ?? 'baik'),
        ]);
    }

    public function update(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        if ($row['tanggal_diterima'] === null) {
            $this->conflict('Pengembalian belum diverifikasi Petugas. Gunakan menu verifikasi pengembalian.');
            return;
        }

        $parsed = $this->service->validateVerify($_POST);
        if ($parsed['errors'] !== []) {
            $this->renderForm($row, [
                'tanggal_diterima' => is_string($_POST['tanggal_diterima'] ?? null) ? trim($_POST['tanggal_diterima']) : '',
                'kondisi' => is_string($_POST['kondisi'] ?? null) ? trim($_POST['kondisi']) : '',
            ], $parsed['errors'], 422);
            return;
        }

        $outcome = $this->returns->adminUpdate(
            (int) $row['loan_id'],
            $parsed['values']['tanggal_diterima'],
            $parsed['values']['kondisi'],
            $this->actorId()
        );
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome === 'not_verified') {
            $this->conflict('Hanya pengembalian terverifikasi yang dapat dikoreksi.');
            return;
        }
        if ($outcome === 'invalid_date') {
            $this->renderForm($row, [
                'tanggal_diterima' => $parsed['values']['tanggal_diterima'],
                'kondisi' => $parsed['values']['kondisi'],
            ], ['tanggal_diterima' => 'Tanggal diterima harus di antara tanggal pinjam/pengajuan dan hari ini.'], 422);
            return;
        }
        if ($outcome === 'stock_conflict') {
            $this->conflict('Stok tersedia tidak cukup untuk koreksi kondisi ini. Periksa jumlah stok alat terlebih dahulu.');
            return;
        }

        $this->session->set('flash_success', 'Data pengembalian berhasil dikoreksi.');
        redirect('/admin/pengembalian');
    }

    public function destroy(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $outcome = $this->returns->adminDelete((int) $row['loan_id'], $this->actorId());
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome !== 'deleted') {
            $this->conflict('Pengembalian yang sudah diverifikasi tidak dapat dihapus karena stok sudah dipulihkan. Hapus hanya pengajuan yang belum diverifikasi.');
            return;
        }
        $this->session->set('flash_success', 'Pengajuan pengembalian berhasil dihapus dan peminjaman kembali berstatus disetujui.');
        redirect('/admin/pengembalian');
    }

    /** @return array<string, mixed>|null */
    private function findOr404(string $id): ?array
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();
            return null;
        }
        $row = $this->returns->adminFind((int) $id);
        if ($row === null) {
            $this->notFound();
            return null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function renderForm(array $row, array $values, array $errors = [], int $status = 200): void
    {
        $this->render('admin/pengembalian/form', [
            'row' => $row,
            'values' => $values,
            'errors' => $errors,
            'today' => date('Y-m-d'),
            'pageTitle' => 'Koreksi Pengembalian',
        ], $status);
    }

    private function pageParam(): int
    {
        $page = $_GET['page'] ?? null;

        return is_string($page) && preg_match('/^[1-9]\d{0,6}$/', $page) === 1 ? (int) $page : 1;
    }

    private function actorId(): int
    {
        $user = $this->auth->user();

        return $user === null ? 0 : (int) $user['id'];
    }

    private function notFound(): void
    {
        $this->abort(404, 'Pengembalian tidak ditemukan', 'Data pengembalian tidak ditemukan.');
    }

    private function conflict(string $message): void
    {
        $this->abort(409, 'Status tidak valid', $message);
    }

    private function abort(int $status, string $title, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><meta charset="utf-8">'
            . '<title>' . e($title) . ' · SPARTA</title>'
            . '<body><h1>' . e((string) $status) . '</h1><p>' . e($message) . '</p>'
            . '<p><a href="/admin/pengembalian">Kembali ke data pengembalian</a></p></body></html>';
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data, int $status = 200): void
    {
        $user = $this->auth->user();
        $success = $this->session->get('flash_success');
        $error = $this->session->get('flash_error');
        $this->session->forget('flash_success');
        $this->session->forget('flash_error');
        $this->view->render($template, $data + [
            'user' => $user,
            'role' => $user === null ? '' : (string) $user['role'],
            'csrfToken' => $this->session->csrfToken(),
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
