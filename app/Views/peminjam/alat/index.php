<?php
/** @var array $items */
/** @var array $categories */
/** @var string $q */
/** @var string $kategoriId */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string|null $success */
/** @var string|null $error */

$items = is_array($items ?? null) ? $items : [];
$categories = is_array($categories ?? null) ? $categories : [];
$q = (string) ($q ?? '');
$kategoriId = (string) ($kategoriId ?? '');
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$pageTitle = 'Katalog Alat';
$activeNav = 'katalog';

$pageUrl = static function (int $target) use ($q, $kategoriId): string {
    $params = array_filter([
        'q' => $q,
        'kategori_id' => $kategoriId,
        'page' => $target > 1 ? (string) $target : null,
    ], static fn ($val) => $val !== null && $val !== '');
    return '/peminjam/alat' . ($params !== [] ? '?' . http_build_query($params) : '');
};

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <span class="badge border-indigo-200 bg-indigo-50 font-semibold text-indigo-800">Peminjaman Sekolah</span>
            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Katalog Alat</h2>
            <p class="mt-2 text-slate-600">Cari dan ajukan peminjaman alat praktik dan operasional sekolah.</p>
        </div>
        <a href="/peminjam/peminjaman" class="btn btn-outline">
            <span class="app-icon" aria-hidden="true"><?= icon('clipboard-list') ?></span>
            Peminjaman Saya
        </a>
    </section>

    <?php if (!empty($success)): ?>
        <div role="status" class="alert alert-success alert-soft">
            <span class="app-icon" aria-hidden="true"><?= icon('check') ?></span>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div role="alert" class="alert alert-error alert-soft">
            <span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Card -->
    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-4 p-4 sm:p-6">
            <form method="get" action="/peminjam/alat" class="grid gap-3 sm:grid-cols-12" role="search">
                <div class="sm:col-span-7 lg:col-span-8">
                    <label class="input w-full">
                        <span class="app-icon text-slate-400" aria-hidden="true"><?= icon('search') ?></span>
                        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama alat..." aria-label="Cari alat">
                    </label>
                </div>
                <div class="sm:col-span-3 lg:col-span-3">
                    <label for="kategori_id" class="sr-only">Filter Kategori</label>
                    <select id="kategori_id" name="kategori_id" class="select w-full" aria-label="Filter berdasarkan kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php $catId = (string) ($cat['id'] ?? ''); ?>
                            <option value="<?= e($catId) ?>" <?= $kategoriId === $catId ? 'selected' : '' ?>>
                                <?= e((string) ($cat['nama'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-neutral w-full">Filter</button>
                    <?php if ($q !== '' || $kategoriId !== ''): ?>
                        <a href="/peminjam/alat" class="btn btn-ghost btn-square" title="Reset filter" aria-label="Reset filter">
                            <span class="app-icon" aria-hidden="true"><?= icon('x') ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-14 text-center">
                    <span class="app-icon mx-auto grid size-14 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true">
                        <?= icon($q !== '' || $kategoriId !== '' ? 'search' : 'wrench') ?>
                    </span>
                    <h3 class="mt-4 text-xl font-bold">
                        <?= $q !== '' || $kategoriId !== '' ? 'Alat tidak ditemukan' : 'Katalog alat belum tersedia' ?>
                    </h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">
                        <?= $q !== '' || $kategoriId !== ''
                            ? 'Tidak ada alat yang sesuai kriteria pencarian. Coba ubah kata kunci atau bersihkan filter.'
                            : 'Belum ada alat aktif yang dapat dipinjam saat ini. Silakan hubungi petugas laboratorium.' ?>
                    </p>
                    <div class="mt-6">
                        <?php if ($q !== '' || $kategoriId !== ''): ?>
                            <a href="/peminjam/alat" class="btn btn-outline">Lihat Semua Alat</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Grid of Catalog Items -->
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $stokTersedia = (int) ($item['stok_tersedia'] ?? 0);
                        $isAvailable = $stokTersedia > 0;
                        $itemId = (string) ($item['id'] ?? '');
                        $deskripsi = trim((string) ($item['deskripsi'] ?? ''));
                        ?>
                        <article class="card flex flex-col justify-between border border-base-300 bg-base-100 shadow-xs transition hover:border-indigo-300 hover:shadow-md">
                            <div class="card-body p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="badge badge-sm border-indigo-200 bg-indigo-50 font-medium text-indigo-800">
                                        <?= e((string) ($item['kategori_nama'] ?? 'Umum')) ?>
                                    </span>
                                    <span class="font-mono text-xs font-semibold text-slate-500">
                                        <?= e((string) ($item['kode'] ?? '')) ?>
                                    </span>
                                </div>
                                <h3 class="card-title mt-3 text-lg font-bold leading-snug">
                                    <?= e((string) ($item['nama'] ?? '')) ?>
                                </h3>
                                <p class="line-clamp-2 text-sm text-slate-600">
                                    <?= $deskripsi !== '' ? e($deskripsi) : '<span class="italic text-slate-400">Tidak ada deskripsi tambahan.</span>' ?>
                                </p>
                            </div>
                            <div class="border-t border-base-200 bg-base-200/40 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-xs text-slate-500">Stok Tersedia</p>
                                        <p class="text-base font-bold <?= $isAvailable ? 'text-emerald-700' : 'text-slate-500' ?>">
                                            <?= e((string) $stokTersedia) ?> <span class="text-xs font-normal">unit</span>
                                        </p>
                                    </div>
                                    <?php if ($isAvailable): ?>
                                        <a href="/peminjam/peminjaman/create?alat_id=<?= rawurlencode($itemId) ?>" class="btn btn-primary btn-sm">
                                            <span class="app-icon" aria-hidden="true"><?= icon('plus') ?></span>
                                            Pinjam
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-disabled" disabled aria-disabled="true" title="Stok habis">
                                            Habis
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center">
                    <p class="text-sm text-slate-600">
                        <strong><?= e((string) $total) ?></strong> alat · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?>
                    </p>
                    <?php if ($totalPages > 1): ?>
                        <nav class="join" aria-label="Paginasi katalog">
                            <?php if ($page > 1): ?>
                                <a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page - 1)) ?>">Sebelumnya</a>
                            <?php else: ?>
                                <span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Sebelumnya</span>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page + 1)) ?>">Berikutnya</a>
                            <?php else: ?>
                                <span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Berikutnya</span>
                            <?php endif; ?>
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
