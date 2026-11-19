<?php
/** @var array $items */
/** @var string $q */
/** @var string $status */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $csrfToken */
/** @var string|null $success */
/** @var string|null $error */

$items = is_array($items ?? null) ? $items : [];
$q = (string) ($q ?? '');
$status = (string) ($status ?? '');
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$pageTitle = 'Data Pengembalian';
$activeNav = 'pengembalian';

$tabs = ['' => 'Semua', 'pending' => 'Belum Diverifikasi', 'verified' => 'Sudah Diverifikasi'];
$filterUrl = static function (string $target) use ($q): string {
    $params = array_filter(['status' => $target, 'q' => $q], static fn ($v) => $v !== '');

    return '/admin/pengembalian' . ($params === [] ? '' : '?' . http_build_query($params));
};
$pageUrl = static function (int $target) use ($q, $status): string {
    $params = array_filter(['status' => $status, 'q' => $q, 'page' => $target > 1 ? (string) $target : ''], static fn ($v) => $v !== '');

    return '/admin/pengembalian' . ($params === [] ? '' : '?' . http_build_query($params));
};
$kondisiLabels = ['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat', 'hilang' => 'Hilang'];
$formatRupiah = static fn (float|int|string $value): string => 'Rp ' . number_format((float) $value, 0, ',', '.');
$formatDate = static function (?string $value): string {
    if (!$value) return '—';
    $ts = strtotime($value);

    return $ts ? date('d M Y', $ts) : $value;
};

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section>
        <p class="text-sm font-semibold text-indigo-700">Transaksi</p>
        <h2 class="mt-1 text-3xl font-black tracking-tight">Data pengembalian</h2>
        <p class="mt-2 text-slate-600">Koreksi kondisi dan tanggal penerimaan. Perubahan kondisi menyesuaikan stok tersedia sebesar selisihnya dan tercatat di log aktivitas.</p>
    </section>

    <?php if (!empty($success)): ?>
        <div role="status" class="alert alert-success alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('check') ?></span><span><?= e($success) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div role="alert" class="alert alert-error alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="join overflow-x-auto" role="tablist" aria-label="Filter status pengembalian">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="<?= e($filterUrl($key)) ?>" class="btn join-item btn-sm <?= $status === $key ? 'btn-active btn-neutral' : 'btn-ghost' ?>" role="tab" aria-selected="<?= $status === $key ? 'true' : 'false' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <form method="get" action="/admin/pengembalian" class="flex items-center gap-2">
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <label class="input input-sm input-bordered flex items-center gap-2">
                <span class="app-icon text-slate-400" aria-hidden="true"><?= icon('search') ?></span>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, peminjam, alat" class="grow" aria-label="Cari pengembalian">
            </label>
            <button type="submit" class="btn btn-neutral btn-sm">Cari</button>
            <?php if ($q !== ''): ?><a href="<?= e($filterUrl($status)) ?>" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
        </form>
    </div>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-5 p-4 sm:p-6">
            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-12 text-center">
                    <span class="app-icon mx-auto grid size-12 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true"><?= icon('refresh-cw') ?></span>
                    <h3 class="mt-4 text-lg font-bold">Belum ada data pengembalian</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600"><?= $q !== '' || $status !== '' ? 'Tidak ada data yang cocok dengan filter tersebut.' : 'Pengembalian muncul setelah peminjam mengajukan pengembalian.' ?></p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar pengembalian</caption>
                        <thead>
                            <tr>
                                <th>Kode &amp; peminjam</th>
                                <th>Alat &amp; jumlah</th>
                                <th>Pengajuan</th>
                                <th>Penerimaan</th>
                                <th class="text-right">Denda</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $verified = ($item['tanggal_diterima'] ?? null) !== null;
                            $loanId = (string) ($item['loan_id'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <p class="font-mono text-xs font-bold text-indigo-700"><?= e((string) ($item['kode'] ?? '')) ?></p>
                                    <p class="font-semibold"><?= e((string) ($item['peminjam_nama'] ?? '')) ?></p>
                                </td>
                                <td>
                                    <p><?= e((string) ($item['alat_nama'] ?? '')) ?></p>
                                    <p class="text-xs text-slate-500 tabular-nums"><?= e((string) ($item['jumlah'] ?? '')) ?> unit</p>
                                </td>
                                <td class="whitespace-nowrap text-xs text-slate-600"><?= e($formatDate((string) ($item['tanggal_pengajuan'] ?? ''))) ?></td>
                                <td>
                                    <?php if ($verified): ?>
                                        <p class="text-xs text-slate-600"><?= e($formatDate((string) ($item['tanggal_diterima'] ?? ''))) ?></p>
                                        <span class="badge badge-sm badge-ghost"><?= e($kondisiLabels[(string) ($item['kondisi'] ?? '')] ?? (string) ($item['kondisi'] ?? '')) ?></span>
                                        <?php if ((int) ($item['hari_terlambat'] ?? 0) > 0): ?>
                                            <p class="mt-1 text-[11px] font-semibold text-rose-700">Terlambat <?= e((string) $item['hari_terlambat']) ?> hari</p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-sm border-amber-200 bg-amber-50 text-amber-800">Menunggu verifikasi</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right tabular-nums"><?= e($formatRupiah((float) ($item['denda'] ?? 0))) ?></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <?php if ($verified): ?>
                                            <a href="/admin/pengembalian/<?= e($loanId) ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
                                        <?php else: ?>
                                            <form method="post" action="/admin/pengembalian/<?= e($loanId) ?>/delete" onsubmit="return confirm('Hapus pengajuan pengembalian ini? Peminjaman akan kembali berstatus disetujui.')">
                                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm text-red-700">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center">
                    <p class="text-sm text-slate-600"><strong><?= e((string) $total) ?></strong> data · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?></p>
                    <?php if ($totalPages > 1): ?>
                        <nav class="join" aria-label="Paginasi pengembalian">
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
