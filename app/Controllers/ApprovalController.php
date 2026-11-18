<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Peminjaman;
use App\Services\Auth;
use App\Services\PeminjamanService;
use DomainException;

/** Petugas decision queue (FR-09). */
final class ApprovalController
{
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
        $page = $this->pageParam();
        $result = $this->loans->pending($page);
        $this->render('petugas/persetujuan/index', [
            'items' => $result['items'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'pageTitle' => 'Persetujuan Peminjaman',
        ]);
    }

    public function approve(string $id): void
    {
        $this->decide($id, 'approved', null);
    }

    public function reject(string $id): void
    {
        try {
            $note = $this->service->note($_POST['catatan'] ?? null);
        } catch (DomainException $exception) {
            $this->session->set('flash_error', $exception->getMessage());
            redirect('/petugas/persetujuan');
        }
        $this->decide($id, 'rejected', $note);
    }

    private function decide(string $id, string $decision, ?string $note): void
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();
            return;
        }
        $outcome = $this->loans->decide((int) $id, $decision, $this->actorId(), $note);
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        $messages = [
            'approved' => ['flash_success', 'Pengajuan peminjaman disetujui dan stok telah dikurangi.'],
            'rejected' => ['flash_success', 'Pengajuan peminjaman ditolak tanpa mengubah stok.'],
            'not_pending' => ['flash_error', 'Pengajuan sudah diproses. Muat ulang antrean untuk melihat status terbaru.'],
            'inactive' => ['flash_error', 'Alat sudah nonaktif sehingga pengajuan tidak dapat disetujui.'],
            'insufficient' => ['flash_error', 'Stok alat tidak mencukupi lagi. Pengajuan tidak diubah.'],
        ];
        [$key, $message] = $messages[$outcome] ?? ['flash_error', 'Keputusan tidak dapat diproses.'];
        $this->session->set($key, $message);
        redirect('/petugas/persetujuan');
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
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><meta charset="utf-8">'
            . '<title>Pengajuan tidak ditemukan · SPARTA</title>'
            . '<body><h1>404</h1><p>Pengajuan peminjaman tidak ditemukan.</p>'
            . '<p><a href="/petugas/persetujuan">Kembali ke antrean persetujuan</a></p></body></html>';
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
            'pageTitle' => 'Persetujuan Peminjaman',
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
