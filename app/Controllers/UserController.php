<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Services\Auth;
use DomainException;

final class UserController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private User $user
    ) {
    }

    public function index(): void
    {
        $q = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
        $page = $this->pageParam();
        $result = $this->user->paginate($q, $page);
        $this->render('admin/users/index', [
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
        $parsed = $this->user->parse($_POST);
        if ($parsed['errors'] !== []) {
            $this->renderForm(null, $parsed['values'], $parsed['errors'], 422);

            return;
        }
        try {
            $this->user->create($parsed['values'], $parsed['password'], $this->actorId());
        } catch (DomainException $exception) {
            $this->renderForm(null, $parsed['values'], ['username' => $exception->getMessage()], 422);

            return;
        }
        $this->session->set('flash_success', 'Pengguna berhasil ditambahkan.');
        redirect('/admin/users');
    }

    public function edit(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $this->renderForm($row, $this->rowValues($row));
    }

    public function update(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        $parsed = $this->user->parse($_POST, (int) $row['id']);
        if ($parsed['errors'] !== []) {
            $this->renderForm($row, $parsed['values'], $parsed['errors'], 422);

            return;
        }
        try {
            $updated = $this->user->update(
                (int) $row['id'],
                $parsed['values'],
                $parsed['password'],
                $this->actorId()
            );
        } catch (DomainException $exception) {
            $field = str_contains($exception->getMessage(), 'Username')
                ? 'username'
                : ((int) $parsed['values']['is_active'] === 0 ? 'is_active' : 'role');
            $this->renderForm($row, $parsed['values'], [$field => $exception->getMessage()], 422);

            return;
        }
        if (!$updated) {
            $this->notFound();

            return;
        }
        $this->session->set('flash_success', 'Pengguna berhasil diperbarui.');
        redirect('/admin/users');
    }

    public function destroy(string $id): void
    {
        $row = $this->findOr404($id);
        if ($row === null) {
            return;
        }
        try {
            $outcome = $this->user->delete((int) $row['id'], $this->actorId());
        } catch (DomainException $exception) {
            $this->session->set('flash_error', $exception->getMessage());
            redirect('/admin/users/' . (int) $row['id'] . '/edit');

            return;
        }
        if ($outcome === 'missing') {
            $this->notFound();

            return;
        }
        $this->session->set(
            'flash_success',
            'Pengguna ' . $row['username'] . ' dinonaktifkan. Histori transaksi dan audit tetap utuh.'
        );
        redirect('/admin/users');
    }

    /**
     * @param array<string, mixed>|null $row
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function renderForm(?array $row, array $values, array $errors = [], int $status = 200): void
    {
        $this->render('admin/users/form', [
            'isEdit' => $row !== null,
            'values' => $values + $this->emptyValues(),
            'errors' => $errors,
        ], $status);
    }

    /** @return array<string, string> */
    private function emptyValues(): array
    {
        return [
            'id' => '',
            'name' => '',
            'username' => '',
            'role' => 'peminjam',
            'is_active' => '1',
        ];
    }

    /** @param array<string, mixed> $row @return array<string, string> */
    private function rowValues(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'username' => (string) $row['username'],
            'role' => (string) $row['role'],
            'is_active' => (string) $row['is_active'],
        ];
    }

    /** @return array<string, mixed>|null */
    private function findOr404(string $id): ?array
    {
        if (preg_match('/^[1-9]\d{0,18}$/', $id) !== 1) {
            $this->notFound();

            return null;
        }
        $row = $this->user->find((int) $id);
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
            . '<title>Pengguna tidak ditemukan · SPARTA</title>'
            . '<body><h1>404</h1><p>Pengguna tidak ditemukan.</p>'
            . '<p><a href="/admin/users">Kembali ke daftar pengguna</a></p></body></html>';
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
            'pageTitle' => 'Pengguna',
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
