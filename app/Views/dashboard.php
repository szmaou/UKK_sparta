<?php
/** @var array|null $user */
/** @var string $csrfToken */
/** @var string $role */
/** @var list<array{label: string, value: int, href: string, desc: string}> $stats */

$user = is_array($user ?? null) ? $user : [];
$stats = is_array($stats ?? null) ? $stats : [];
$currentRole = strtolower((string) ($role ?? $user['role'] ?? 'peminjam'));
$name = (string) ($user['name'] ?? $user['username'] ?? 'Pengguna');
$roleCopy = [
    'admin' => [
        'label' => 'Admin',
        'intro' => 'Kelola data sekolah dari satu ruang kerja yang rapi.',
        'icon' => 'users',
        'title' => 'Ruang kelola Admin',
        'body' => 'Pantau ringkasan sistem dan kelola data sekolah dari menu inventaris.',
    ],
    'petugas' => [
        'label' => 'Petugas',
        'intro' => 'Tinjau alur peminjaman dan pengembalian dengan jelas.',
        'icon' => 'square-check',
        'title' => 'Ruang kerja Petugas',
        'body' => 'Pantau antrean persetujuan, verifikasi pengembalian, dan keterlambatan.',
    ],
    'peminjam' => [
        'label' => 'Peminjam',
        'intro' => 'Temukan alat sekolah dan pantau pengajuan Anda.',
        'icon' => 'search',
        'title' => 'Ruang pinjam Anda',
        'body' => 'Pantau status pengajuan dan kelola peminjaman Anda dari satu tempat.',
    ],
];
$copy = $roleCopy[$currentRole] ?? $roleCopy['peminjam'];
$pageTitle = 'Dashboard';

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <span class="badge border-indigo-200 bg-indigo-50 font-semibold text-indigo-800"><?= e($copy['label']) ?></span>
            <h2 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Selamat datang, <?= e($name) ?></h2>
            <p class="mt-2 max-w-2xl text-slate-600"><?= e($copy['intro']) ?></p>
        </div>
        <p class="text-sm text-slate-500">Ruang kerja <?= e($copy['label']) ?></p>
    </section>

    <?php if ($stats !== []): ?>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan">
            <?php foreach ($stats as $stat): ?>
                <a href="<?= e((string) ($stat['href'] ?? '#')) ?>" class="stat rounded-xl border border-base-300 bg-base-100 shadow-sm transition hover:border-indigo-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600">
                    <div class="stat-title"><?= e((string) ($stat['label'] ?? '')) ?></div>
                    <div class="stat-value text-2xl tabular-nums"><?= e((string) ($stat['value'] ?? 0)) ?></div>
                    <div class="stat-desc whitespace-normal text-slate-500"><?= e((string) ($stat['desc'] ?? '')) ?></div>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.75fr)]">
        <article class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body p-6 sm:p-8">
                <div class="mb-6 grid size-12 place-items-center rounded-xl bg-indigo-50 text-indigo-700 app-icon" aria-hidden="true"><?= icon($copy['icon']) ?></div>
                <h3 class="card-title text-2xl font-bold"><?= e($copy['title']) ?></h3>
                <p class="mt-2 max-w-2xl text-slate-600"><?= e($copy['body']) ?></p>
            </div>
        </article>

        <aside class="card border border-base-300 bg-indigo-950 text-white shadow-sm">
            <div class="card-body p-6 sm:p-7">
                <span class="app-icon text-indigo-200" aria-hidden="true"><?= icon('circle-exclamation-point') ?></span>
                <h3 class="card-title mt-2 text-xl">Akses sesuai peran</h3>
                <p class="leading-7 text-indigo-100">Gunakan menu yang tersedia untuk membuka fitur sesuai hak akses akun Anda.</p>
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
