<?php
/** @var array|null $user */
/** @var string $csrfToken */
/** @var string $role */

$user = is_array($user ?? null) ? $user : [];
$currentRole = strtolower((string) ($role ?? $user['role'] ?? 'peminjam'));
$roleLabel = ['admin' => 'Admin', 'petugas' => 'Petugas', 'peminjam' => 'Peminjam'][$currentRole] ?? 'Pengguna';
$name = (string) ($user['name'] ?? $user['username'] ?? 'Pengguna');
$username = (string) ($user['username'] ?? '');
$pageTitle = (string) ($pageTitle ?? 'Dashboard');
$activeNav = (string) ($activeNav ?? match (true) {
    str_contains(strtolower($pageTitle), 'pengguna') => 'pengguna',
    str_contains(strtolower($pageTitle), 'kategori') => 'kategori',
    str_contains(strtolower($pageTitle), 'alat') => 'alat',
    str_contains(strtolower($pageTitle), 'persetujuan') => 'persetujuan',
    str_contains(strtolower($pageTitle), 'katalog') => 'katalog',
    str_contains(strtolower($pageTitle), 'peminjaman') => 'peminjaman',
    default => 'dashboard',
});

$menus = [
    'admin' => [
        ['layout-dashboard', 'Dashboard', '/admin/dashboard', 'dashboard'],
        ['users', 'Pengguna', '/admin/users', 'pengguna'],
        ['tags', 'Kategori', '/admin/kategori', 'kategori'],
        ['wrench', 'Alat', '/admin/alat', 'alat'],
        ['clipboard-list', 'Peminjaman', null, 'peminjaman'],
        ['refresh-cw', 'Pengembalian', null, 'pengembalian'],
        ['logs', 'Log Aktivitas', null, 'log'],
    ],
    'petugas' => [
        ['layout-dashboard', 'Dashboard', '/petugas/dashboard', 'dashboard'],
        ['square-check', 'Persetujuan', '/petugas/persetujuan', 'persetujuan'],
        ['refresh-cw', 'Pengembalian', null, 'pengembalian'],
        ['file-text', 'Laporan', null, 'laporan'],
    ],
    'peminjam' => [
        ['layout-dashboard', 'Dashboard', '/peminjam/dashboard', 'dashboard'],
        ['search', 'Katalog Alat', '/peminjam/alat', 'katalog'],
        ['clipboard-list', 'Peminjaman Saya', '/peminjam/peminjaman', 'peminjaman'],
        ['refresh-cw', 'Pengembalian', null, 'pengembalian'],
    ],
];
if (!isset($menus[$currentRole])) {
    $currentRole = 'peminjam';
    $roleLabel = 'Peminjam';
}
$navigation = $menus[$currentRole];
?>
<!doctype html>
<html lang="id" data-theme="sparta">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SPARTA — Sistem Peminjaman Barang dan Alat">
    <title><?= e($pageTitle) ?> · SPARTA</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-base-200 text-base-content antialiased">
<div class="drawer lg:drawer-open">
    <input id="app-drawer" type="checkbox" class="drawer-toggle">
    <div class="drawer-content flex min-h-screen min-w-0 flex-col">
        <header class="navbar sticky top-0 z-20 min-h-16 border-b border-base-300 bg-base-100 px-4 lg:px-8">
            <div class="navbar-start gap-2">
                <button type="button" class="btn btn-square btn-ghost lg:hidden" aria-label="Buka navigasi" aria-controls="app-drawer" aria-expanded="false" data-drawer-open>
                    <span class="app-icon" aria-hidden="true"><?= icon('menu') ?></span>
                </button>
                <div>
                    <p class="hidden text-xs font-medium text-slate-500 sm:block">SPARTA / <?= e($roleLabel) ?></p>
                    <h1 class="text-lg font-bold tracking-tight"><?= e($pageTitle) ?></h1>
                </div>
            </div>
            <div class="navbar-end">
                <div class="flex items-center gap-3 rounded-lg px-2 py-1">
                    <div class="hidden text-right sm:block">
                        <p class="max-w-44 truncate text-sm font-semibold"><?= e($name) ?></p>
                        <p class="text-xs text-slate-500"><?= e($roleLabel) ?></p>
                    </div>
                    <span class="grid size-9 place-items-center rounded-full bg-indigo-50 text-indigo-700 app-icon" aria-hidden="true"><?= icon('circle-user') ?></span>
                </div>
            </div>
        </header>

        <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8">
            <?= $content ?? '' ?>
        </main>
        <footer class="px-4 pb-6 text-center text-xs text-slate-500">SPARTA · Sistem Peminjaman Barang &amp; Alat</footer>
    </div>

    <div class="drawer-side z-30">
        <button type="button" aria-label="Tutup navigasi" class="drawer-overlay" data-drawer-close></button>
        <aside class="flex min-h-full w-64 flex-col border-r border-base-300 bg-base-100" aria-label="Panel navigasi">
            <div class="flex h-20 items-center gap-3 border-b border-base-300 px-5">
                <img class="logo-school h-12 w-auto" src="/assets/img/SMK%20Negeri%201%20Leuwimunding.svg" alt="Logo SMK Negeri 1 Leuwimunding">
                <div>
                    <p class="text-xl font-black tracking-tight text-indigo-700">SPARTA</p>
                    <p class="text-[11px] leading-tight text-slate-500">Peminjaman barang &amp; alat</p>
                </div>
            </div>

            <nav class="flex-1 p-3" aria-label="Navigasi utama">
                <p class="px-3 pb-2 pt-3 text-xs font-bold uppercase tracking-widest text-slate-400">Menu <?= e($roleLabel) ?></p>
                <ul class="menu sidebar-menu w-full gap-1 p-0">
                    <?php foreach ($navigation as [$menuIcon, $label, $url, $navKey]): ?>
                        <li>
                            <?php if ($url !== null): ?>
                                <a href="<?= e($url) ?>"<?= $activeNav === $navKey ? ' aria-current="page"' : '' ?>>
                                    <span class="app-icon" aria-hidden="true"><?= icon($menuIcon) ?></span>
                                    <?= e($label) ?>
                                </a>
                            <?php else: ?>
                                <span class="menu-disabled flex min-h-10 items-center gap-3 rounded-lg px-3" aria-disabled="true">
                                    <span class="app-icon" aria-hidden="true"><?= icon($menuIcon) ?></span>
                                    <span class="flex-1"><?= e($label) ?></span>
                                    <span class="badge badge-ghost badge-xs whitespace-nowrap">Belum tersedia</span>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="border-t border-base-300 p-4">
                <div class="mb-3 rounded-lg bg-base-200 px-3 py-2">
                    <p class="truncate text-sm font-semibold"><?= e($name) ?></p>
                    <p class="truncate text-xs text-slate-500">@<?= e($username) ?></p>
                </div>
                <form method="post" action="/logout">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn btn-ghost w-full justify-start text-red-700 hover:bg-red-50">
                        <span class="app-icon" aria-hidden="true"><?= icon('log-out') ?></span>
                        Keluar
                    </button>
                </form>
            </div>
        </aside>
    </div>
</div>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
