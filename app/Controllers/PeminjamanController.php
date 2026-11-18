<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Alat;
use App\Models\Peminjaman;
use App\Services\Auth;
use App\Services\PeminjamanService;

/** Borrower loan requests and own history (FR-13, BR-08). */
final class PeminjamanController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private Alat $alat,
        private Peminjaman $loans,
        private PeminjamanService $service
    ) {
    }

    public function index(): void
    {
        $page = $this->pageParam();
        $result = $this->loans->history($this->actorId(), $page);
        $this->render('peminjam/peminjaman/index', [
            'items' => $result['items'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'pageTitle' => 'Peminjaman Saya',
        ]);
    }

    public function create(): void
    {
        $id = $this->alat->positiveId($_GET['alat_id'] ?? null);
        $tool = $id === null ? null : $this->alat->find($id);
        if (!is_array($tool) || (int) $tool['is_active'] !== 1) {
            $this->notFound();
            return;
        }
        $today = date('Y-m-d');
        $this->renderForm($tool, [
            'alat_id' => (string) $tool['id'],
            'jumlah' => '1',
            'tanggal_pinjam' => $today,
            'tanggal_jatuh_tempo' => date('Y-m-d', strtotime($today . ' +7 days')),
            'tujuan' => '',
        ]);
    }

    public function store(): void
    {
        $parsed = $this->service->validate($_POST);
        if (!is_array($parsed['tool'])) {
            $this->notFound();
            return;
        }
        if ($parsed['errors'] !== []) {
            $this->renderForm($parsed['tool'], $parsed['values'], $parsed['errors'], 422);
            return;
        }
        $this->service->create($this->actorId(), $parsed['values'], $this->actorId());
        $this->session->set('flash_success', 'Pengajuan peminjaman berhasil dikirim dan menunggu persetujuan Petugas.');
        redirect('/peminjam/peminjaman');
    }

    /**
     * @param array<string, mixed> $tool
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function renderForm(array $tool, array $values, array $errors = [], int $status = 200): void
    {
        $this->render('peminjam/peminjaman/form', [
            'alat' => $tool,
            'values' => $values,
            'errors' => $errors,
            'pageTitle' => 'Ajukan Peminjaman',
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
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><meta charset="utf-8">'
            . '<title>Alat tidak ditemukan · SPARTA</title>'
            . '<body><h1>404</h1><p>Alat tidak ditemukan atau sudah nonaktif.</p>'
            . '<p><a href="/peminjam/alat">Kembali ke katalog</a></p></body></html>';
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
            'pageTitle' => 'Peminjaman',
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
