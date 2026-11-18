<?php
/** @var array $items */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string|null $success */
/** @var string|null $error */

$items = is_array($items ?? null) ? $items : [];
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$pageTitle = 'Peminjaman Saya';
$activeNav = 'peminjaman';

$pageUrl = static function (int $target): string {
    return '/peminjam/peminjaman' . ($target > 1 ? '?page=' . $target : '');
};

$statusBadge = static function (string $status): array {
    return match (strtolower(trim($status))) {
        'pending' => ['Menunggu Persetujuan', 'border-amber-200 bg-amber-50 text-amber-800'],
        'approved' => ['Disetujui', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'rejected' => ['Ditolak', 'border-rose-200 bg-rose-50 text-rose-800'],
        'borrowed' => ['Sedang Dipinjam', 'border-blue-200 bg-blue-50 text-blue-800'],
        'return_requested' => ['Menunggu Verifikasi Kembali', 'border-sky-200 bg-sky-50 text-sky-800'],
        'returned' => ['Dikembalikan', 'border-slate-200 bg-slate-100 text-slate-700'],
        default => [ucfirst($status), 'badge-ghost'],
    };
};

$formatDate = static function (?string $date): string {
    if (!$date) return '—';
    $time = strtotime($date);
    return $time ? date('d M Y', $time) : $date;
};

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <span class="badge border-indigo-200 bg-indigo-50 font-semibold text-indigo-800">Riwayat & Status</span>
            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Peminjaman Saya</h2>
            <p class="mt-2 text-slate-600">Pantau status pengajuan peminjaman alat praktik Anda.</p>
        </div>
        <a href="/peminjam/alat" class="btn btn-primary">
            <span class="app-icon" aria-hidden="true"><?= icon('plus') ?></span>
            Ajukan Pinjaman Baru
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

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-5 p-4 sm:p-6">
            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-14 text-center">
                    <span class="app-icon mx-auto grid size-14 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true">
                        <?= icon('clipboard-list') ?>
                    </span>
                    <h3 class="mt-4 text-xl font-bold">Belum ada pengajuan peminjaman</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">
                        Anda belum pernah mengajukan peminjaman alat. Pilih alat yang dibutuhkan pada katalog untuk mulai meminjam.
                    </p>
                    <div class="mt-6">
                        <a href="/peminjam/alat" class="btn btn-primary">
                            <span class="app-icon" aria-hidden="true"><?= icon('search') ?></span>
                            Buka Katalog Alat
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar peminjaman saya</caption>
                        <thead>
                            <tr>
                                <th>Kode & Tanggal</th>
                                <th>Nama Alat</th>
                                <th class="text-right">Jumlah</th>
                                <th>Jadwal Pinjam</th>
                                <th>Status</th>
                                <th>Catatan Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $status = (string) ($item['status'] ?? 'pending');
                            [$label, $badgeClass] = $statusBadge($status);
                            $catatan = trim((string) ($item['catatan'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <p class="font-mono text-xs font-bold text-indigo-700">
                                        <?= e((string) ($item['kode'] ?? '—')) ?>
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        Diajukan <?= e($formatDate((string) ($item['created_at'] ?? ''))) ?>
                                    </p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900"><?= e((string) ($item['alat_nama'] ?? '—')) ?></p>
                                </td>
                                <td class="text-right tabular-nums font-semibold">
                                    <?= e((string) ($item['jumlah'] ?? 1)) ?> <span class="text-xs font-normal text-slate-500">unit</span>
                                </td>
                                <td>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Pinjam:</span> <?= e($formatDate((string) ($item['tanggal_pinjam'] ?? ''))) ?>
                                    </p>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Tempo:</span> <?= e($formatDate((string) ($item['tanggal_jatuh_tempo'] ?? ''))) ?>
                                    </p>
                                </td>
                                <td>
                                    <span class="badge badge-sm <?= $badgeClass ?> font-medium">
                                        <?= e($label) ?>
                                    </span>
                                </td>
                                <td class="max-w-xs text-xs text-slate-600">
                                    <?= $catatan !== '' ? e($catatan) : '<span class="italic text-slate-400">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center">
                    <p class="text-sm text-slate-600">
                        <strong><?= e((string) $total) ?></strong> transaksi · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?>
                    </p>
                    <?php if ($totalPages > 1): ?>
                        <nav class="join" aria-label="Paginasi riwayat pinjaman">
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
