<?php

declare(strict_types=1);

/**
 * Route table. Only phase 1 (auth + role dashboards) is wired here; module
 * routes append below when their controllers exist.
 *
 * @var \App\Core\Router $router
 * @var array{db: \PDO, auth: \App\Services\Auth, session: \App\Core\Session, view: \App\Core\View, errors: \App\Controllers\ErrorController, authController: \App\Controllers\AuthController, dashboardController: \App\Controllers\DashboardController} $app
 */

use App\Core\Router;

$router->get('/login', [$app['authController'], 'showLogin'], public: true);
$router->post('/login', [$app['authController'], 'login'], public: true);
$router->post('/logout', [$app['authController'], 'logout']);

$router->get('/', [$app['dashboardController'], 'home']);

$roles = ['admin', 'petugas', 'peminjam'];
foreach ($roles as $role) {
    $router->get(
        '/' . $role . '/dashboard',
        [$app['dashboardController'], 'dashboard'],
        [$role]
    );
}
