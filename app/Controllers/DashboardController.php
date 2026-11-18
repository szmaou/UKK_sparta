<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Services\Auth;

final class DashboardController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view
    ) {
    }

    /** GET /: send signed-in users to their own dashboard, everyone else to login. */
    public function home(): void
    {
        $user = $this->auth->user();

        redirect($user === null ? '/login' : dashboard_path((string) $user['role']));
    }

    public function dashboard(): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            redirect('/login');
        }

        $role = (string) $user['role'];

        $this->view->render('dashboard', [
            'user' => $user,
            'csrfToken' => $this->session->csrfToken(),
            'error' => null,
            'oldUsername' => '',
            'role' => $role,
        ]);
    }
}
