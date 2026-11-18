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
$pageTitle = 'Kategori';
$activeNav = 'kategori';
$pageUrl = static fn (int $target): string => '/admin/kategori?' . http_build_query(array_filter(['q' => $q, 'page' => $target], static fn ($value) => $value !== ''));

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-indigo-700">Master inventaris</p>
            <h2 class="mt-1 text-3xl font-black tracking-tight">Kategori alat</h2>
            <p class="mt-2 text-slate-600">Kelompokkan alat agar daftar inventaris mudah dicari dan dikelola.</p>
        </div>
        <a href="/admin/kategori/create" class="btn btn-primary">
            <span class="app-icon" aria-hidden="true"><?= icon('plus') ?></span>
            Tambah Kategori
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
            <form method="get" action="/admin/kategori" class="flex flex-col gap-3 sm:flex-row" role="search">
                <label class="input w-full sm:max-w-md">
                    <span class="app-icon text-slate-400" aria-hidden="true"><?= icon('search') ?></span>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau deskripsi kategori" aria-label="Cari kategori">
                </label>
                <button type="submit" class="btn btn-neutral">Cari</button>
                <?php if ($q !== ''): ?><a href="/admin/kategori" class="btn btn-ghost">Hapus pencarian</a><?php endif; ?>
            </form>

            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-12 text-center">
                    <span class="app-icon mx-auto grid size-12 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true"><?= icon($q === '' ? 'tags' : 'search') ?></span>
                    <h3 class="mt-4 text-lg font-bold"><?= $q === '' ? 'Belum ada kategori' : 'Kategori tidak ditemukan' ?></h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600"><?= $q === '' ? 'Tambahkan kategori pertama untuk mulai mengelompokkan inventaris.' : 'Coba kata kunci lain atau hapus pencarian untuk melihat semua kategori.' ?></p>
                    <div class="mt-5"><?php if ($q === ''): ?><a href="/admin/kategori/create" class="btn btn-primary">Tambah Kategori</a><?php else: ?><a href="/admin/kategori" class="btn btn-outline">Lihat semua kategori</a><?php endif; ?></div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar kategori alat</caption>
                        <thead><tr><th>Nama</th><th>Deskripsi</th><th class="text-right">Jumlah alat</th><th class="text-right">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="font-semibold"><?= e((string) ($item['nama'] ?? '')) ?></td>
                                <td class="max-w-md whitespace-normal text-slate-600"><?= e((string) (($item['deskripsi'] ?? '') !== '' ? $item['deskripsi'] : 'Tidak ada deskripsi')) ?></td>
                                <td class="text-right tabular-nums"><?= e((string) ($item['jumlah_alat'] ?? 0)) ?></td>
                                <td class="text-right"><a href="/admin/kategori/<?= e((string) ($item['id'] ?? '')) ?>/edit" class="btn btn-ghost btn-sm">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center">
                    <p class="text-sm text-slate-600"><strong><?= e((string) $total) ?></strong> kategori · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?></p>
                    <?php if ($totalPages > 1): ?>
                        <nav class="join" aria-label="Paginasi kategori">
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
