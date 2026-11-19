<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Peminjaman;
use App\Services\Auth;
use App\Services\PeminjamanService;

/**
 * Admin transaction maintenance (FR-06). Corrections are limited to pending
 * requests and deletions to pending/rejected rows, so stock stays consistent.
 */
final class AdminPeminjamanController
{
    private const STATUSES = ['', 'pending', 'approved', 'rejected', 'return_requested', 'returned'];

    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private Peminjaman $loans,
        private PeminjamanService $service
    ) {
    }

    public function index(): void
    {
        $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $status = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : '';
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $page = $this->pageParam();
        $result = $this->loans->adminPaginate($q, $status, $page);
        $this->render('admin/peminjaman/index', [
            'items' => $result['items'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'q' => $q,
            'status' => $status,
            'pageTitle' => 'Data Peminjaman',
        ]);
    }

    public function edit(string $id): void
    {
        $loan = $this->findOr404($id);
        if ($loan === null) {
            return;
        }
        $this->renderForm($loan, [
            'jumlah' => (string) $loan['jumlah'],
            'tanggal_pinjam' => (string) $loan['tanggal_pinjam'],
            'tanggal_jatuh_tempo' => (string) $loan['tanggal_jatuh_tempo'],
            'catatan' => (string) ($loan['catatan'] ?? ''),
        ]);
    }

    public function update(string $id): void
    {
        $loan = $this->findOr404($id);
        if ($loan === null) {
            return;
        }
        if ((string) $loan['status'] !== 'pending') {
            $this->conflict('Hanya pengajuan berstatus menunggu yang dapat dikoreksi. Perubahan setelah persetujuan harus melalui alur Petugas.');
            return;
        }

        $parsed = $this->service->validateAdmin($_POST);
        if ($parsed['errors'] !== []) {
            $this->renderForm($loan, [
                'jumlah' => is_string($_POST['jumlah'] ?? null) ? trim($_POST['jumlah']) : '',
                'tanggal_pinjam' => is_string($_POST['tanggal_pinjam'] ?? null) ? trim($_POST['tanggal_pinjam']) : '',
                'tanggal_jatuh_tempo' => is_string($_POST['tanggal_jatuh_tempo'] ?? null) ? trim($_POST['tanggal_jatuh_tempo']) : '',
                'catatan' => is_string($_POST['catatan'] ?? null) ? trim($_POST['catatan']) : '',
            ], $parsed['errors'], 422);
            return;
        }

        $outcome = $this->loans->adminUpdate((int) $loan['id'], $parsed['values'], $this->actorId());
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome === 'not_pending') {
            $this->conflict('Status pengajuan sudah berubah. Muat ulang data.');
            return;
        }
        if ($outcome === 'insufficient') {
            $this->renderForm($loan, [
                'jumlah' => is_string($_POST['jumlah'] ?? null) ? trim($_POST['jumlah']) : '',
                'tanggal_pinjam' => is_string($_POST['tanggal_pinjam'] ?? null) ? trim($_POST['tanggal_pinjam']) : '',
                'tanggal_jatuh_tempo' => is_string($_POST['tanggal_jatuh_tempo'] ?? null) ? trim($_POST['tanggal_jatuh_tempo']) : '',
                'catatan' => is_string($_POST['catatan'] ?? null) ? trim($_POST['catatan']) : '',
            ], ['jumlah' => 'Jumlah melebihi stok tersedia saat ini (' . (int) $loan['stok_tersedia'] . ' unit).'], 422);
            return;
        }

        $this->session->set('flash_success', 'Pengajuan peminjaman berhasil dikoreksi.');
        redirect('/admin/peminjaman');
    }

    public function destroy(string $id): void
    {
        $loan = $this->findOr404($id);
        if ($loan === null) {
            return;
        }
        $outcome = $this->loans->adminDelete((int) $loan['id'], $this->actorId());
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome === 'not_deletable') {
            $this->conflict('Peminjaman yang sudah disetujui tidak dapat dihapus karena menyimpan riwayat dan stok.');
            return;
        }
        $this->session->set('flash_success', 'Pengajuan peminjaman berhasil dihapus.');
        redirect('/admin/peminjaman');
    }

    /** @return array<string, mixed>|null */
    private function findOr404(string $id): ?array
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();
            return null;
        }
        $loan = $this->loans->adminFind((int) $id);
        if ($loan === null) {
            $this->notFound();
            return null;
        }

        return $loan;
    }

    /**
     * @param array<string, mixed> $loan
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function renderForm(array $loan, array $values, array $errors = [], int $status = 200): void
    {
        $this->render('admin/peminjaman/form', [
            'loan' => $loan,
            'values' => $values,
            'errors' => $errors,
            'today' => date('Y-m-d'),
            'pageTitle' => 'Koreksi Peminjaman',
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
        $this->abort(404, 'Peminjaman tidak ditemukan', 'Data peminjaman tidak ditemukan.');
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
            . '<p><a href="/admin/peminjaman">Kembali ke data peminjaman</a></p></body></html>';
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
