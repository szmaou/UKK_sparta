<?php
/** @var array|null $user */
/** @var string $csrfToken */
/** @var string $role */

$user = is_array($user ?? null) ? $user : [];
$currentRole = strtolower((string) ($role ?? $user['role'] ?? 'peminjam'));
$name = (string) ($user['name'] ?? $user['username'] ?? 'Pengguna');
$roleCopy = [
    'admin' => [
        'label' => 'Admin',
        'intro' => 'Kelola data sekolah dari satu ruang kerja yang rapi.',
        'icon' => 'users',
        'title' => 'Ruang kelola Admin',
        'body' => 'Modul pengguna, kategori, alat, transaksi, dan log aktivitas sedang disiapkan.',
        'modules' => ['Pengguna', 'Kategori & alat', 'Transaksi', 'Log aktivitas'],
    ],
    'petugas' => [
        'label' => 'Petugas',
        'intro' => 'Tinjau alur peminjaman dan pengembalian dengan jelas.',
        'icon' => 'square-check',
        'title' => 'Ruang kerja Petugas',
        'body' => 'Modul persetujuan, pemantauan pengembalian, dan laporan sedang disiapkan.',
        'modules' => ['Persetujuan', 'Pengembalian', 'Laporan'],
    ],
    'peminjam' => [
        'label' => 'Peminjam',
        'intro' => 'Temukan alat sekolah dan pantau pengajuan Anda.',
        'icon' => 'search',
        'title' => 'Ruang pinjam Anda',
        'body' => 'Katalog, pengajuan, dan pengembalian alat sedang disiapkan.',
        'modules' => ['Katalog alat', 'Peminjaman saya', 'Pengembalian'],
    ],
];
$copy = $roleCopy[$currentRole] ?? $roleCopy['peminjam'];
$pageTitle = 'Dashboard';

ob_start();
?>
<div class="mx-auto max-w-7xl">
    <section class="mb-8 flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <span class="badge border-indigo-200 bg-indigo-50 font-semibold text-indigo-800"><?= e($copy['label']) ?></span>
            <h2 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Selamat datang, <?= e($name) ?></h2>
            <p class="mt-2 max-w-2xl text-slate-600"><?= e($copy['intro']) ?></p>
        </div>
        <p class="text-sm text-slate-500">Fase 1 · Dasbor dasar</p>
    </section>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.75fr)]">
        <article class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body p-6 sm:p-8">
                <div class="mb-6 grid size-12 place-items-center rounded-xl bg-indigo-50 text-indigo-700 app-icon" aria-hidden="true"><?= icon($copy['icon']) ?></div>
                <h3 class="card-title text-2xl font-bold"><?= e($copy['title']) ?></h3>
                <p class="mt-2 max-w-2xl text-slate-600"><?= e($copy['body']) ?></p>

                <div class="mt-8 grid gap-3 sm:grid-cols-2">
                    <?php foreach ($copy['modules'] as $module): ?>
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-base-300 bg-base-200/60 p-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="app-icon text-slate-500" aria-hidden="true"><?= icon('wrench') ?></span>
                                <span class="truncate font-semibold"><?= e($module) ?></span>
                            </div>
                            <span class="badge badge-ghost badge-sm whitespace-nowrap">Belum tersedia</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>

        <aside class="card border border-base-300 bg-indigo-950 text-white shadow-sm">
            <div class="card-body p-6 sm:p-7">
                <span class="app-icon text-indigo-200" aria-hidden="true"><?= icon('circle-exclamation-point') ?></span>
                <h3 class="card-title mt-2 text-xl">Tentang tahap ini</h3>
                <p class="leading-7 text-indigo-100">Login, logout, dan tampilan dashboard sudah tersedia. Data operasional belum ditampilkan agar tidak menyerupai data nyata.</p>
                <div class="mt-4 rounded-xl border border-white/10 bg-white/5 p-4">
                    <p class="text-sm font-semibold">Akun aktif</p>
                    <p class="mt-1 truncate text-sm text-indigo-200">@<?= e((string) ($user['username'] ?? '')) ?></p>
                </div>
            </div>
        </aside>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/app.php';
