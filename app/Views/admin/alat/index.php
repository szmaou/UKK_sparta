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
$pageTitle = 'Data Alat';
$activeNav = 'alat';
$pageUrl = static fn (int $target): string => '/admin/alat?' . http_build_query(array_filter(['q' => $q, 'page' => $target], static fn ($value) => $value !== ''));

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-indigo-700">Master inventaris</p><h2 class="mt-1 text-3xl font-black tracking-tight">Data alat</h2><p class="mt-2 text-slate-600">Kelola identitas, stok, kategori, dan status alat.</p></div>
        <a href="/admin/alat/create" class="btn btn-primary"><span class="app-icon" aria-hidden="true"><?= icon('plus') ?></span>Tambah Alat</a>
    </section>
    <?php if (!empty($success)): ?><div role="status" class="alert alert-success alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('check') ?></span><span><?= e($success) ?></span></div><?php endif; ?>
    <?php if (!empty($error)): ?><div role="alert" class="alert alert-error alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span><span><?= e($error) ?></span></div><?php endif; ?>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-5 p-4 sm:p-6">
            <form method="get" action="/admin/alat" class="flex flex-col gap-3 sm:flex-row" role="search">
                <label class="input w-full sm:max-w-md"><span class="app-icon text-slate-400" aria-hidden="true"><?= icon('search') ?></span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama alat" aria-label="Cari alat"></label>
                <button type="submit" class="btn btn-neutral">Cari</button>
                <?php if ($q !== ''): ?><a href="/admin/alat" class="btn btn-ghost">Hapus pencarian</a><?php endif; ?>
            </form>

            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-12 text-center">
                    <span class="app-icon mx-auto grid size-12 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true"><?= icon($q === '' ? 'wrench' : 'search') ?></span>
                    <h3 class="mt-4 text-lg font-bold"><?= $q === '' ? 'Belum ada alat' : 'Alat tidak ditemukan' ?></h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600"><?= $q === '' ? 'Tambahkan alat pertama agar inventaris dapat dikelola.' : 'Periksa ejaan atau hapus pencarian untuk melihat semua alat.' ?></p>
                    <div class="mt-5"><?php if ($q === ''): ?><a href="/admin/alat/create" class="btn btn-primary">Tambah Alat</a><?php else: ?><a href="/admin/alat" class="btn btn-outline">Lihat semua alat</a><?php endif; ?></div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar alat</caption>
                        <thead><tr><th>Kode dan alat</th><th>Kategori</th><th class="text-right">Total</th><th class="text-right">Tersedia</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $active = (string) ($item['is_active'] ?? '0') === '1';
                            $foto = trim((string) ($item['foto'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <?php if ($foto !== ''): ?>
                                            <img src="/uploads/alat/<?= rawurlencode($foto) ?>" alt="" class="h-12 w-12 shrink-0 rounded-lg border border-base-300 bg-base-100 object-cover">
                                        <?php else: ?>
                                            <span class="app-icon grid h-12 w-12 shrink-0 place-items-center rounded-lg border border-base-300 bg-base-200/60 text-slate-400" aria-hidden="true"><?= icon('image') ?></span>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <p class="font-semibold"><?= e((string) ($item['nama'] ?? '')) ?></p>
                                            <p class="mt-1 font-mono text-xs text-slate-500"><?= e((string) ($item['kode'] ?? '')) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e((string) ($item['kategori_nama'] ?? '—')) ?></td>
                                <td class="text-right tabular-nums"><?= e((string) ($item['stok_total'] ?? 0)) ?></td>
                                <td class="text-right tabular-nums"><span class="font-semibold"><?= e((string) ($item['stok_tersedia'] ?? 0)) ?></span><span class="block text-xs text-slate-500"><?= e((string) ($item['stok_dipinjam'] ?? 0)) ?> dipinjam</span></td>
                                <td><span class="badge <?= $active ? 'border-green-200 bg-green-50 text-green-800' : 'badge-ghost' ?>"><?= $active ? 'Aktif' : 'Nonaktif' ?></span></td>
                                <td class="text-right"><a href="/admin/alat/<?= e((string) ($item['id'] ?? '')) ?>/edit" class="btn btn-ghost btn-sm">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center"><p class="text-sm text-slate-600"><strong><?= e((string) $total) ?></strong> alat · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?></p>
                    <?php if ($totalPages > 1): ?><nav class="join" aria-label="Paginasi alat">
                        <?php if ($page > 1): ?><a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page - 1)) ?>">Sebelumnya</a><?php else: ?><span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Sebelumnya</span><?php endif; ?>
                        <?php if ($page < $totalPages): ?><a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page + 1)) ?>">Berikutnya</a><?php else: ?><span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Berikutnya</span><?php endif; ?>
                    </nav><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
