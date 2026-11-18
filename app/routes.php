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

// Borrower catalog, loan requests and own history (FR-12, FR-13).
$router->get('/peminjam/alat', [$app['katalogController'], 'index'], ['peminjam']);
$router->get('/peminjam/peminjaman', [$app['peminjamanController'], 'index'], ['peminjam']);
$router->get('/peminjam/peminjaman/create', [$app['peminjamanController'], 'create'], ['peminjam']);
$router->post('/peminjam/peminjaman/create', [$app['peminjamanController'], 'store'], ['peminjam']);
// Petugas approval queue (FR-09).
$router->get('/petugas/persetujuan', [$app['approvalController'], 'index'], ['petugas']);
$router->post('/petugas/peminjaman/{id}/approve', [$app['approvalController'], 'approve'], ['petugas']);
$router->post('/petugas/peminjaman/{id}/reject', [$app['approvalController'], 'reject'], ['petugas']);

// Returns: borrower request (FR-14) and Petugas reception monitoring (FR-10).
$router->get('/peminjam/pengembalian', [$app['pengembalianController'], 'index'], ['peminjam']);
$router->post('/peminjam/peminjaman/{id}/return', [$app['pengembalianController'], 'requestReturn'], ['peminjam']);
$router->get('/petugas/pengembalian', [$app['pengembalianController'], 'monitoring'], ['petugas']);
$router->get('/petugas/pengembalian/{id}/verify', [$app['pengembalianController'], 'verifyForm'], ['petugas']);
$router->post('/petugas/pengembalian/{id}/verify', [$app['pengembalianController'], 'verifyStore'], ['petugas']);

// Admin master data: kategori, alat and users (FR-03, FR-04, FR-05). POST
// routes carry CSRF through the front controller.
foreach (['kategori', 'alat', 'users'] as $module) {
    $controller = $app[$module . 'Controller'];
    $router->get('/admin/' . $module, [$controller, 'index'], ['admin']);
    $router->get('/admin/' . $module . '/create', [$controller, 'create'], ['admin']);
    $router->post('/admin/' . $module . '/create', [$controller, 'store'], ['admin']);
    $router->get('/admin/' . $module . '/{id}/edit', [$controller, 'edit'], ['admin']);
    $router->post('/admin/' . $module . '/{id}/edit', [$controller, 'update'], ['admin']);
    $router->post('/admin/' . $module . '/{id}/delete', [$controller, 'destroy'], ['admin']);
}
