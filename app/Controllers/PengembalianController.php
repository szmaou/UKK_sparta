<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Pengembalian;
use App\Services\Auth;
use App\Services\PengembalianService;

/** Borrower return requests (FR-14) and Petugas reception monitoring (FR-10). */
final class PengembalianController
{
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
        $page = $this->pageParam();
        $result = $this->returns->own($this->actorId(), $page);
        $this->render('peminjam/pengembalian/index', [
            'items' => $result['items'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'pageTitle' => 'Pengembalian Saya',
        ]);
    }

    public function requestReturn(string $id): void
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();
            return;
        }
        $outcome = $this->returns->requestReturn((int) $id, $this->actorId(), $this->actorId());
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome === 'forbidden') {
            $this->forbidden();
            return;
        }
        if ($outcome === 'not_approved' || $outcome === 'duplicate') {
            $this->conflict('Pengembalian tidak dapat diajukan pada status peminjaman ini.');
            return;
        }
        $this->session->set(
            'flash_success',
            'Permintaan pengembalian berhasil dikirim. Menunggu verifikasi Petugas.'
        );
        redirect('/peminjam/pengembalian');
    }

    public function monitoring(): void
    {
        $status = $this->service->statusFilter($_GET['status'] ?? null);
        $q = $this->service->search($_GET['q'] ?? null);
        $page = $this->pageParam();
        $result = $this->returns->monitoring($status, $page, $q);
        $this->render('petugas/pengembalian/index', [
            'items' => $result['items'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'status' => $status,
            'q' => $q,
            'pageTitle' => 'Monitoring Pengembalian',
        ]);
    }

    public function verifyForm(string $id): void
    {
        $loan = $this->findVerifyLoan($id);
        if ($loan === null) {
            return;
        }
        if ((string) $loan['status'] !== 'return_requested'
            || $loan['tanggal_pengajuan'] === null
            || $loan['tanggal_diterima'] !== null) {
            $this->conflict('Peminjaman ini tidak sedang menunggu verifikasi pengembalian.');
            return;
        }
        $this->renderForm($loan, [
            'tanggal_diterima' => date('Y-m-d'),
            'kondisi' => 'baik',
        ]);
    }

    public function verifyStore(string $id): void
    {
        $loan = $this->findVerifyLoan($id);
        if ($loan === null) {
            return;
        }
        if ((string) $loan['status'] !== 'return_requested'
            || $loan['tanggal_pengajuan'] === null
            || $loan['tanggal_diterima'] !== null) {
            $this->conflict('Peminjaman ini tidak sedang menunggu verifikasi pengembalian.');
            return;
        }

        $parsed = $this->service->validateVerify($_POST);
        if ($parsed['errors'] !== []) {
            $this->renderForm($loan, $parsed['values'], $parsed['errors'], 422);
            return;
        }

        $outcome = $this->returns->verify(
            (int) $loan['id'],
            $this->actorId(),
            $parsed['values']['tanggal_diterima'],
            $parsed['values']['kondisi']
        );
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome === 'invalid_date') {
            $this->renderForm($loan, $parsed['values'], [
                'tanggal_diterima' => 'Tanggal diterima tidak boleh sebelum tanggal pinjam atau tanggal pengajuan, dan tidak boleh melebihi hari ini.',
            ], 422);
            return;
        }
        if ($outcome !== 'verified') {
            $this->conflict('Pengembalian sudah diproses atau stok tidak konsisten. Muat ulang data.');
            return;
        }
        $this->session->set(
            'flash_success',
            'Pengembalian berhasil diverifikasi dan stok tersedia telah diperbarui.'
        );
        redirect('/petugas/pengembalian');
    }

    /** @return array<string, mixed>|null */
    private function findVerifyLoan(string $id): ?array
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();
            return null;
        }
        $loan = $this->returns->findForVerify((int) $id);
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
        $this->render('petugas/pengembalian/form', [
            'loan' => $loan,
            'values' => $values,
            'errors' => $errors,
            'tarifDenda' => 0,
            'today' => date('Y-m-d'),
            'pageTitle' => 'Verifikasi Pengembalian',
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
        $this->abort(404, 'Pengembalian tidak ditemukan', 'Peminjaman atau data pengembalian tidak ditemukan.');
    }

    private function forbidden(): void
    {
        $this->abort(403, 'Akses ditolak', 'Anda hanya dapat mengajukan pengembalian untuk peminjaman milik sendiri.');
    }

    private function conflict(string $message): void
    {
        $this->abort(409, 'Status tidak valid', $message);
    }

    private function abort(int $status, string $title, string $message): void
    {
        $user = $this->auth->user();
        $back = $user !== null && (string) $user['role'] === 'petugas'
            ? '/petugas/pengembalian' : '/peminjam/peminjaman';
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><meta charset="utf-8">'
            . '<title>' . e($title) . ' · SPARTA</title>'
            . '<body><h1>' . e((string) $status) . '</h1><p>' . e($message) . '</p>'
            . '<p><a href="' . $back . '">Kembali</a></p></body></html>';
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
            'pageTitle' => 'Pengembalian',
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
