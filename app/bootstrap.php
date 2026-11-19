<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

// Return dates and deadlines are compared in the school's local timezone.
date_default_timezone_set('Asia/Jakarta');

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
$logs = new App\Models\ActivityLog($container['db']);
$alatModel = new App\Models\Alat($container['db'], $logs, new App\Models\Kategori($container['db'], $logs));
$loans = new App\Models\Peminjaman($container['db'], $logs);
$peminjamanService = new App\Services\PeminjamanService($alatModel, $loans);
$pengaturan = new App\Models\Pengaturan($container['db'], $logs);
$returns = new App\Models\Pengembalian($container['db'], $pengaturan);

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
        $view,
        new App\Models\LoginThrottle($container['db'])
    ),
    'dashboardController' => new App\Controllers\DashboardController(
        $auth,
        $session,
        $view,
        new App\Models\Dashboard($container['db'])
    ),
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
    'katalogController' => new App\Controllers\KatalogController(
        $auth,
        $session,
        $view,
        $alatModel
    ),
    'peminjamanController' => new App\Controllers\PeminjamanController(
        $auth,
        $session,
        $view,
        $alatModel,
        $loans,
        $peminjamanService
    ),
    'approvalController' => new App\Controllers\ApprovalController(
        $auth,
        $session,
        $view,
        $loans,
        $peminjamanService
    ),
    'pengembalianController' => new App\Controllers\PengembalianController(
        $auth,
        $session,
        $view,
        $returns,
        new App\Services\PengembalianService(),
        $pengaturan
    ),
    'pengaturanController' => new App\Controllers\PengaturanController(
        $auth,
        $session,
        $view,
        $pengaturan
    ),
    'adminPeminjamanController' => new App\Controllers\AdminPeminjamanController(
        $auth,
        $session,
        $view,
        $loans,
        $peminjamanService
    ),
    'adminPengembalianController' => new App\Controllers\AdminPengembalianController(
        $auth,
        $session,
        $view,
        $returns,
        new App\Services\PengembalianService()
    ),
    'activityLogController' => new App\Controllers\ActivityLogController(
        $auth,
        $session,
        $view,
        $logs
    ),
    'laporanController' => new App\Controllers\LaporanController(
        $auth,
        $session,
        $view,
        new App\Models\Laporan($container['db'])
    ),
];

$router = new App\Core\Router();
require __DIR__ . '/routes.php';

$app['router'] = $router;

return $app;
