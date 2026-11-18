<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

/**
 * Full application container: PDO (from bootstrap/app.php), services, controllers
 * and the route table. Used by public/index.php and the HTTP integration test.
 *
 * @return array<string, mixed>
 */
$container = require dirname(__DIR__) . '/bootstrap/app.php';

$session = new App\Core\Session();
$users = new App\Models\User($container['db'], new App\Models\ActivityLog($container['db']));
$auth = new App\Services\Auth($session, $users);
$view = new App\Core\View(__DIR__ . '/Views');

$app = [
    'db' => $container['db'],
    'session' => $session,
    'auth' => $auth,
    'view' => $view,
    'errors' => new App\Controllers\ErrorController($auth, $session, $view),
    'authController' => new App\Controllers\AuthController(
        $auth,
        $session,
        new App\Models\ActivityLog($container['db']),
        $view
    ),
    'dashboardController' => new App\Controllers\DashboardController($auth, $session, $view),
    'kategoriController' => new App\Controllers\KategoriController(
        $auth,
        $session,
        $view,
        new App\Models\Kategori($container['db'], new App\Models\ActivityLog($container['db']))
    ),
    'usersController' => new App\Controllers\UserController($auth, $session, $view, $users),
    'alatController' => new App\Controllers\AlatController(
        $auth,
        $session,
        $view,
        new App\Models\Alat(
            $container['db'],
            new App\Models\ActivityLog($container['db']),
            new App\Models\Kategori($container['db'], new App\Models\ActivityLog($container['db']))
        )
    ),
];

$router = new App\Core\Router();
require __DIR__ . '/routes.php';

$app['router'] = $router;

return $app;
