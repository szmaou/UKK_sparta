<?php
/** @var array $items */
/** @var string $q */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string|null $success */
/** @var string|null $error */

$items = is_array($items ?? null) ? $items : [];
$q = (string) ($q ?? '');
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$pageTitle = 'Kelola Pengguna';
$activeNav = 'pengguna';
$pageUrl = static fn (int $target): string => '/admin/users?' . http_build_query(array_filter(['q' => $q, 'page' => $target], static fn ($value) => $value !== ''));

$roleLabels = [
    'admin' => 'Admin',
    'petugas' => 'Petugas',
    'peminjam' => 'Peminjam',
];

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-indigo-700">Master pengguna</p>
            <h2 class="mt-1 text-3xl font-black tracking-tight">Kelola pengguna</h2>
            <p class="mt-2 text-slate-600">Kelola akun, peran akses, dan status aktif pengguna sistem.</p>
        </div>
        <a href="/admin/users/create" class="btn btn-primary">
            <span class="app-icon" aria-hidden="true"><?= icon('plus') ?></span>
            Tambah Pengguna
        </a>
    </section>

    <?php if (!empty($success)): ?>
        <div role="status" class="alert alert-success alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('check') ?></span><span><?= e($success) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div role="alert" class="alert alert-error alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-5 p-4 sm:p-6">
            <form method="get" action="/admin/users" class="flex flex-col gap-3 sm:flex-row" role="search">
                <label class="input w-full sm:max-w-md">
                    <span class="app-icon text-slate-400" aria-hidden="true"><?= icon('search') ?></span>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau username pengguna" aria-label="Cari pengguna">
                </label>
                <button type="submit" class="btn btn-neutral">Cari</button>
                <?php if ($q !== ''): ?><a href="/admin/users" class="btn btn-ghost">Hapus pencarian</a><?php endif; ?>
            </form>

            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-12 text-center">
                    <span class="app-icon mx-auto grid size-12 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true"><?= icon($q === '' ? 'users' : 'search') ?></span>
                    <h3 class="mt-4 text-lg font-bold"><?= $q === '' ? 'Belum ada pengguna' : 'Pengguna tidak ditemukan' ?></h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600"><?= $q === '' ? 'Tambahkan akun pengguna pertama untuk memberikan hak akses ke aplikasi.' : 'Periksa ejaan atau hapus pencarian untuk melihat semua akun pengguna.' ?></p>
                    <div class="mt-5"><?php if ($q === ''): ?><a href="/admin/users/create" class="btn btn-primary">Tambah Pengguna</a><?php else: ?><a href="/admin/users" class="btn btn-outline">Lihat semua pengguna</a><?php endif; ?></div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar pengguna</caption>
                        <thead>
                            <tr>
                                <th>Nama dan username</th>
                                <th>Peran</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $active = (string) ($item['is_active'] ?? '0') === '1';
                            $itemRole = strtolower((string) ($item['role'] ?? 'peminjam'));
                            $displayRole = $roleLabels[$itemRole] ?? ucfirst($itemRole);
                            $roleBadgeClass = match ($itemRole) {
                                'admin' => 'badge-primary',
                                'petugas' => 'badge-info',
                                default => 'badge-ghost',
                            };
                            $createdAt = (string) ($item['created_at'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <p class="font-semibold"><?= e((string) ($item['name'] ?? '')) ?></p>
                                    <p class="mt-1 font-mono text-xs text-slate-500">@<?= e((string) ($item['username'] ?? '')) ?></p>
                                </td>
                                <td>
                                    <span class="badge badge-sm <?= $roleBadgeClass ?>"><?= e($displayRole) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $active ? 'border-green-200 bg-green-50 text-green-800' : 'badge-ghost' ?>"><?= $active ? 'Aktif' : 'Nonaktif' ?></span>
                                </td>
                                <td class="text-xs text-slate-500 tabular-nums">
                                    <?= e($createdAt !== '' ? $createdAt : '—') ?>
                                </td>
                                <td class="text-right">
                                    <a href="/admin/users/<?= e((string) ($item['id'] ?? '')) ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center">
                    <p class="text-sm text-slate-600"><strong><?= e((string) $total) ?></strong> pengguna · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?></p>
                    <?php if ($totalPages > 1): ?>
                        <nav class="join" aria-label="Paginasi pengguna">
                            <?php if ($page > 1): ?><a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page - 1)) ?>">Sebelumnya</a><?php else: ?><span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Sebelumnya</span><?php endif; ?>
                            <?php if ($page < $totalPages): ?><a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page + 1)) ?>">Berikutnya</a><?php else: ?><span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Berikutnya</span><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
