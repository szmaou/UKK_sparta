<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Session;
use App\Core\View;
use App\Models\Alat;
use App\Services\Auth;
use DomainException;
final class AlatController
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
        $page = $this->pageParam();
        $result = $this->alat->paginate($q, $page);
        $this->render('admin/alat/index', [
            'items' => $result['items'],
            'q' => $q,
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
        ]);
    }
    public function create(): void
    {
        $this->renderForm(null, $this->emptyValues());
    }
    public function store(): void
    {
        $parsed = $this->alat->parse($_POST);
        if ($parsed['errors'] !== []) {
            $this->renderForm(null, $parsed['values'], $parsed['errors'], 422);
            return;
        }
        try {
            $this->alat->create($parsed['values'], $this->actorId());
        } catch (DomainException $exception) {
            $this->renderForm(null, $parsed['values'], ['kode' => $exception->getMessage()], 422);
            return;
        }
        $this->session->set('flash_success', 'Alat berhasil ditambahkan.');
        redirect('/admin/alat');
    }
    public function edit(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $this->renderForm($row, [
            'id' => (string) $row['id'],
            'kode' => (string) $row['kode'],
            'nama' => (string) $row['nama'],
            'kategori_id' => (string) $row['kategori_id'],
            'deskripsi' => (string) ($row['deskripsi'] ?? ''),
            'stok_total' => (string) $row['stok_total'],
            'is_active' => (string) $row['is_active'],
        ]);
    }
    public function update(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $parsed = $this->alat->parse($_POST, (int) $row['id']);
        if ($parsed['errors'] !== []) {
            $this->renderForm($row, $parsed['values'], $parsed['errors'], 422);
            return;
        }
        try {
            $result = $this->alat->update((int) $row['id'], $parsed['values'], $this->actorId());
        } catch (DomainException $exception) {
            $this->renderForm($row, $parsed['values'], ['stok_total' => $exception->getMessage()], 422);
            return;
        }
        if ($result === null) {
            $this->notFound();
            return;
        }
        $this->session->set('flash_success', 'Alat berhasil diperbarui.');
        redirect('/admin/alat');
    }
    public function destroy(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $outcome = $this->alat->delete((int) $row['id'], $this->actorId());
        if ($outcome === 'missing') {
            $this->notFound();
            return;
        }
        if ($outcome === 'deactivated') {
            $this->session->set(
                'flash_error',
                'Alat ' . $row['kode'] . ' sudah dipakai transaksi peminjaman, jadi dinonaktifkan agar histori tetap utuh.'
            );
            redirect('/admin/alat/' . (int) $row['id'] . '/edit');
        }
        $this->session->set('flash_success', 'Alat ' . $row['kode'] . ' berhasil dihapus.');
        redirect('/admin/alat');
    }
    /**
     * @param array<string, mixed>|null $row
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function renderForm(?array $row, array $values, array $errors = [], int $status = 200): void
    {
        $this->render('admin/alat/form', [
            'isEdit' => $row !== null,
            'values' => $values + $this->emptyValues(),
            'errors' => $errors,
            'categories' => $this->alat->categories(),
            'pageTitle' => 'Alat',
        ], $status);
    }
    /** @return array<string, string> */
    private function emptyValues(): array
    {
        return [
            'kode' => '',
            'nama' => '',
            'kategori_id' => '',
            'deskripsi' => '',
            'stok_total' => '0',
            'is_active' => '1',
        ];
    }
    /** @return array<string, mixed>|null */
    private function findOr404(string $id): ?array
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();
            return null;
        }
        $row = $this->alat->find((int) $id);
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
            . '<title>Alat tidak ditemukan · SPARTA</title>'
            . '<body><h1>404</h1><p>Alat tidak ditemukan.</p>'
            . '<p><a href="/admin/alat">Kembali ke daftar alat</a></p></body></html>';
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
            'pageTitle' => 'Alat',
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
