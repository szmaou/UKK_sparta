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

    public function notFound(): void
    {
        $this->view->render('errors/404', $this->contract(), 404);
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
