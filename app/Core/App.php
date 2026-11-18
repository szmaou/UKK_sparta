<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;
use App\Services\Auth;

/**
 * Front-controller kernel: authentication, role authorisation and CSRF run here
 * so every route is protected the same way and controllers stay small.
 */
final class App
{
    public function __construct(
        private Router $router,
        private Session $session,
        private Auth $auth,
        private ErrorController $errors
    ) {
    }

    public function run(): void
    {
        $this->session->start();
        $this->sendSecurityHeaders();

        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        $route = $this->router->match($method, $path);
        if ($route === null) {
            $allowed = $this->router->allowedMethods($path);
            if ($allowed === []) {
                $this->errors->notFound();

                return;
            }

            http_response_code(405);
            header('Allow: ' . implode(', ', $allowed));
            header('Content-Type: text/html; charset=utf-8');
            echo 'Method not allowed.';

            return;
        }

        $user = $this->auth->user();

        if ($route['auth'] && $user === null) {
            redirect('/login');
        }

        if ($route['roles'] !== null && ($user === null || !in_array((string) $user['role'], $route['roles'], true))) {
            http_response_code(403);
            $this->errors->forbidden();

            return;
        }

        if ($method === 'POST' && !$this->session->verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(403);
            $this->errors->forbidden();

            return;
        }

        try {
            call_user_func_array($route['handler'], array_values($route['params']));
        } catch (\Throwable $exception) {
            error_log('SPARTA: ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());
            if (!headers_sent()) {
                http_response_code(500);
            }
            $this->errors->serverError();
        }
    }

    private function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
    }
}
