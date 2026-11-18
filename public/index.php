<?php

declare(strict_types=1);

// Single web entrypoint. Document root is public/; app code stays outside it.
$root = dirname(__DIR__);

try {
    $app = require $root . '/app/bootstrap.php';
    (new App\Core\App($app['router'], $app['session'], $app['auth'], $app['errors']))->run();
} catch (Throwable $exception) {
    error_log('SPARTA boot failure: ' . $exception->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $app = $app ?? [];
    $view = $app['view'] ?? null;
    if ($view instanceof App\Core\View) {
        $user = isset($app['auth']) && $app['auth'] instanceof App\Services\Auth ? $app['auth']->user() : null;
        $view->render('errors/500', [
            'user' => $user,
            'csrfToken' => '',
            'error' => null,
            'oldUsername' => '',
            'role' => is_array($user) ? (string) $user['role'] : '',
        ], 500);
        exit;
    }

    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Gangguan sistem · SPARTA</title>'
        . '<body><h1>500</h1><p>Terjadi gangguan pada sistem.</p></body></html>';
}
