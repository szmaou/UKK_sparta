<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\LoginThrottle;
use App\Services\Auth;

final class AuthController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private ActivityLog $logs,
        private View $view,
        private LoginThrottle $throttle
    ) {
    }

    public function showLogin(?string $error = null, int $status = 200, string $oldUsername = ''): void
    {
        $user = $this->auth->user();
        if ($user !== null) {
            redirect(dashboard_path((string) $user['role']));
        }

        $this->view->render('auth/login', [
            'user' => null,
            'csrfToken' => $this->session->csrfToken(),
            'error' => $error,
            'oldUsername' => $oldUsername,
            'role' => '',
        ], $status);
    }

    public function login(): void
    {
        if ($this->auth->user() !== null) {
            redirect(dashboard_path((string) $this->auth->user()['role']));
        }

        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($username === '' || $password === '' || strlen($username) > 50 || strlen($password) > 200) {
            $this->showLogin('Username dan kata sandi wajib diisi.', 422, $username);

            return;
        }

        // Locked accounts skip verification entirely, so a correct password
        // during the lockout reveals nothing and does not shorten the wait.
        $locked = $this->throttle->lockedFor($username);
        if ($locked > 0) {
            header('Retry-After: ' . $locked);
            $this->showLogin(
                'Terlalu banyak percobaan masuk. Coba lagi dalam ' . max(1, (int) ceil($locked / 60)) . ' menit.',
                429,
                $username
            );

            return;
        }

        $user = $this->auth->attempt($username, $password);
        if ($user === null) {
            // Generic message: do not reveal whether the account exists or is inactive.
            $this->throttle->recordFailure($username);
            $this->showLogin('Username atau kata sandi tidak sesuai.', 401, $username);

            return;
        }

        $this->throttle->clear($username);
        $this->auth->login($user);
        $this->logs->record(
            (int) $user['id'],
            'login',
            'user',
            (int) $user['id'],
            'Login berhasil'
        );

        redirect(dashboard_path((string) $user['role']));
    }

    public function logout(): void
    {
        $user = $this->auth->user();
        if ($user !== null) {
            $this->logs->record(
                (int) $user['id'],
                'logout',
                'user',
                (int) $user['id'],
                'Logout'
            );
        }

        $this->auth->logout();
        redirect('/login');
    }
}
