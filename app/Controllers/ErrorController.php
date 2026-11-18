<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Services\Auth;

final class ErrorController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view
    ) {
    }

    public function forbidden(): void
    {
        $this->view->render('errors/403', $this->contract(), 403);
    }

    public function serverError(): void
    {
        $this->view->render('errors/500', $this->contract(), 500);
    }

    /** No 404 template is part of the UI set yet; keep the response tiny and escaped. */
    public function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><meta charset="utf-8">'
            . '<title>Halaman tidak ditemukan · SPARTA</title>'
            . '<body><h1>404</h1><p>Halaman tidak ditemukan.</p>'
            . '<p><a href="/">Kembali</a></p></body></html>';
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        $user = $this->auth->user();

        return [
            'user' => $user,
            'csrfToken' => $this->session->csrfToken(),
            'error' => null,
            'oldUsername' => '',
            'role' => $user === null ? '' : (string) $user['role'],
        ];
    }
}
