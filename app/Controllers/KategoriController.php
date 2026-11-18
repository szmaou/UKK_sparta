<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Session;
use App\Core\View;
use App\Models\Kategori;
use App\Services\Auth;
use DomainException;
final class KategoriController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private Kategori $kategori
    ) {
    }
    public function index(): void
    {
        $q = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
        $page = $this->pageParam();
        $result = $this->kategori->paginate($q, $page);
        $this->render('admin/kategori/index', [
            'items' => $result['items'],
            'q' => $q,
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
        ]);
    }
    public function create(): void
    {
        $this->renderForm(null, ['nama' => '', 'deskripsi' => '']);
    }
    public function store(): void
    {
        $parsed = $this->kategori->parse($_POST);
        if ($parsed['errors'] !== []) {
            $this->renderForm(null, $parsed['values'], $parsed['errors'], 422);
            return;
        }
        try {
            $this->kategori->create($parsed['values'], $this->actorId());
        } catch (DomainException $exception) {
            $this->renderForm(null, $parsed['values'], ['nama' => $exception->getMessage()], 422);
            return;
        }
        $this->flash('flash_success', 'Kategori berhasil ditambahkan.');
        redirect('/admin/kategori');
    }
    public function edit(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $this->renderForm($row, [
            'id' => (string) $row['id'],
            'nama' => (string) $row['nama'],
            'deskripsi' => (string) ($row['deskripsi'] ?? ''),
        ]);
    }
    public function update(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $parsed = $this->kategori->parse($_POST, (int) $row['id']);
        if ($parsed['errors'] !== []) {
            $this->renderForm($row, $parsed['values'], $parsed['errors'], 422);
            return;
        }
        try {
            $this->kategori->update((int) $row['id'], $parsed['values'], $this->actorId());
        } catch (DomainException $exception) {
            $this->renderForm($row, $parsed['values'], ['nama' => $exception->getMessage()], 422);
            return;
        }
        $this->flash('flash_success', 'Kategori berhasil diperbarui.');
        redirect('/admin/kategori');
    }
    public function destroy(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $outcome = $this->kategori->delete((int) $row['id'], $this->actorId());
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome === 'referenced') {
            $this->flash(
                'flash_error',
                'Kategori masih digunakan oleh alat. Pindahkan atau hapus alat terkait sebelum menghapus kategori.'
            );
            redirect('/admin/kategori/' . (int) $row['id'] . '/edit');
        }
        $this->flash('flash_success', 'Kategori berhasil dihapus.');
        redirect('/admin/kategori');
    }
    /** @param array<string, mixed>|null $row */
    private function renderForm(?array $row, array $values, array $errors = [], int $status = 200): void
    {
        $this->render('admin/kategori/form', [
            'isEdit' => $row !== null,
            'values' => $values,
            'errors' => $errors,
        ], $status);
    }
    /** Resolve a positive route ID or emit a 404. @return array<string, mixed>|null */
    private function findOr404(string $id): ?array
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();
            return null;
        }
        $row = $this->kategori->find((int) $id);
        if ($row === null) {
            $this->notFound();
            return null;
        }
        return $row;
    }
    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><meta charset="utf-8">'
            . '<title>Kategori tidak ditemukan · SPARTA</title>'
            . '<body><h1>404</h1><p>Kategori tidak ditemukan.</p>'
            . '<p><a href="/admin/kategori">Kembali ke daftar kategori</a></p></body></html>';
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
    private function flash(string $key, string $message): void
    {
        $this->session->set($key, $message);
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
            'pageTitle' => 'Kategori',
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
