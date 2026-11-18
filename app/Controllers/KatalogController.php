<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Alat;
use App\Services\Auth;

/** Borrower-facing active-tool catalog (FR-12). */
final class KatalogController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private Alat $alat
    ) {
    }

    public function index(): void
    {
        $q = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
        $rawKategori = is_string($_GET['kategori_id'] ?? null) ? trim($_GET['kategori_id']) : '';
        $kategoriId = $this->alat->positiveId($rawKategori);
        $page = $this->pageParam();
        $result = $this->alat->catalog($q, $kategoriId, $page);
        $this->render('peminjam/alat/index', [
            'items' => $result['items'],
            'categories' => $this->alat->categories(),
            'q' => $q,
            'kategoriId' => $kategoriId === null ? '' : (string) $kategoriId,
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'pageTitle' => 'Katalog Alat',
        ]);
    }

    private function pageParam(): int
    {
        $page = $_GET['page'] ?? null;
        return is_string($page) && preg_match('/^[1-9]\d{0,6}$/', $page) === 1 ? (int) $page : 1;
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
            'pageTitle' => 'Katalog Alat',
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
