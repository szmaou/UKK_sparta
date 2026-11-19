<?php

declare(strict_types=1);

/**
 * Route table for all shipped modules: authentication, role dashboards,
 * catalog and loans, Petugas approvals/returns/reports, Admin master data,
 * transactions, audit log, and settings.
 *
 * @var \App\Core\Router $router
 * @var array{db: \PDO, auth: \App\Services\Auth, session: \App\Core\Session, view: \App\Core\View, errors: \App\Controllers\ErrorController, authController: \App\Controllers\AuthController, dashboardController: \App\Controllers\DashboardController} $app
 */

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

// Admin transaction maintenance (FR-06, FR-07) and audit trail (FR-08).
foreach (['peminjaman' => 'adminPeminjamanController', 'pengembalian' => 'adminPengembalianController'] as $module => $key) {
    $router->get('/admin/' . $module, [$app[$key], 'index'], ['admin']);
    $router->get('/admin/' . $module . '/{id}/edit', [$app[$key], 'edit'], ['admin']);
    $router->post('/admin/' . $module . '/{id}/edit', [$app[$key], 'update'], ['admin']);
    $router->post('/admin/' . $module . '/{id}/delete', [$app[$key], 'destroy'], ['admin']);
}
$router->get('/admin/activity-logs', [$app['activityLogController'], 'index'], ['admin']);
// Admin settings: configurable late-return fine tariff.
$router->get('/admin/pengaturan', [$app['pengaturanController'], 'index'], ['admin']);
$router->post('/admin/pengaturan', [$app['pengaturanController'], 'update'], ['admin']);

// Petugas period report and print view (FR-11).
$router->get('/petugas/laporan', [$app['laporanController'], 'index'], ['petugas']);
$router->get('/petugas/laporan/cetak', [$app['laporanController'], 'cetak'], ['petugas']);
