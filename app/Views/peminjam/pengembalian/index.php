<?php
/** @var array $items */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $csrfToken */
/** @var string|null $success */
/** @var string|null $error */

$items = is_array($items ?? null) ? $items : [];
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$pageTitle = 'Pengembalian Saya';
$activeNav = 'pengembalian';

$pageUrl = static function (int $target): string {
    return '/peminjam/pengembalian' . ($target > 1 ? '?page=' . $target : '');
};

$statusBadge = static function (string $status): array {
    return match (strtolower(trim($status))) {
        'approved' => ['Siap Dikembalikan', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'return_requested' => ['Menunggu Verifikasi', 'border-amber-200 bg-amber-50 text-amber-800'],
        'returned' => ['Sudah Dikembalikan', 'border-slate-200 bg-slate-100 text-slate-700'],
        default => [ucfirst($status), 'badge-ghost'],
    };
};

$formatDate = static function (?string $date): string {
    if (!$date) return '—';
    $time = strtotime($date);
    return $time ? date('d M Y', $time) : $date;
};

$formatRupiah = static function (int|float|string $val): string {
    $num = (float) $val;
    return 'Rp ' . number_format($num, 0, ',', '.');
};

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <span class="badge border-indigo-200 bg-indigo-50 font-semibold text-indigo-800">Alur Pengembalian</span>
            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Pengembalian Saya</h2>
            <p class="mt-2 text-slate-600">Ajukan pengembalian alat yang sudah selesai dipinjam dan pantau verifikasi fisik oleh Petugas.</p>
        </div>
        <a href="/peminjam/peminjaman" class="btn btn-outline">
            <span class="app-icon" aria-hidden="true"><?= icon('clipboard-list') ?></span>
            Lihat Semua Peminjaman
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
                        <?= icon('refresh-cw') ?>
                    </span>
                    <h3 class="mt-4 text-xl font-bold">Tidak ada alat yang perlu dikembalikan</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">
                        Anda belum memiliki peminjaman yang disetujui atau menunggu pengembalian fisik.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <a href="/peminjam/alat" class="btn btn-primary">
                            <span class="app-icon" aria-hidden="true"><?= icon('search') ?></span>
                            Katalog Alat
                        </a>
                        <a href="/peminjam/peminjaman" class="btn btn-outline">
                            <span class="app-icon" aria-hidden="true"><?= icon('clipboard-list') ?></span>
                            Riwayat Pinjam
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar peminjaman yang dapat atau telah dikembalikan</caption>
                        <thead>
                            <tr>
                                <th>Kode & Alat</th>
                                <th class="text-right">Jumlah</th>
                                <th>Jadwal Pinjam</th>
                                <th>Status & Riwayat</th>
                                <th>Denda</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $loanId = (string) ($item['id'] ?? '');
                            $kode = (string) ($item['kode'] ?? '—');
                            $alatNama = (string) ($item['alat_nama'] ?? '—');
                            $jumlah = (int) ($item['jumlah'] ?? 1);
                            $status = (string) ($item['status'] ?? '');
                            [$badgeLabel, $badgeClass] = $statusBadge($status);
                            $tglPinjam = (string) ($item['tanggal_pinjam'] ?? '');
                            $tglTempo = (string) ($item['tanggal_jatuh_tempo'] ?? '');
                            $tglPengajuan = (string) ($item['tanggal_pengajuan'] ?? '');
                            $tglDiterima = (string) ($item['tanggal_diterima'] ?? '');
                            $denda = (float) ($item['denda'] ?? 0);
                            $canReturn = ($status === 'approved');
                            ?>
                            <tr>
                                <td>
                                    <p class="font-mono text-xs font-bold text-indigo-700"><?= e($kode) ?></p>
                                    <p class="font-semibold text-slate-900"><?= e($alatNama) ?></p>
                                </td>
                                <td class="text-right tabular-nums font-semibold">
                                    <?= e((string) $jumlah) ?> <span class="text-xs font-normal text-slate-500">unit</span>
                                </td>
                                <td>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Pinjam:</span> <?= e($formatDate($tglPinjam)) ?>
                                    </p>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Tempo:</span> <?= e($formatDate($tglTempo)) ?>
                                    </p>
                                </td>
                                <td>
                                    <span class="badge badge-sm <?= $badgeClass ?> font-medium">
                                        <?= e($badgeLabel) ?>
                                    </span>
                                    <?php if ($tglPengajuan !== ''): ?>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Pengajuan: <?= e($formatDate($tglPengajuan)) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($tglDiterima !== ''): ?>
                                        <p class="text-xs text-emerald-700">
                                            Diterima: <?= e($formatDate($tglDiterima)) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tabular-nums font-semibold <?= $denda > 0 ? 'text-rose-700' : 'text-slate-600' ?>">
                                        <?= e($formatRupiah($denda)) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php if ($canReturn): ?>
                                        <form method="post" action="/peminjam/peminjaman/<?= e($loanId) ?>/return" class="inline"
                                              onsubmit="return confirm('Kembalikan alat <?= e(addslashes($alatNama)) ?> (<?= e($jumlah) ?> unit)? Permintaan pengembalian akan dikirim ke Petugas.');">
                                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <span class="app-icon" aria-hidden="true"><?= icon('refresh-cw') ?></span>
                                                Ajukan Kembali
                                            </button>
                                        </form>
                                    <?php elseif ($status === 'return_requested'): ?>
                                        <span class="badge border-amber-200 bg-amber-50 text-xs font-medium text-amber-800">
                                            Menunggu Petugas
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                            <span class="app-icon size-4 text-emerald-600" aria-hidden="true"><?= icon('check') ?></span>
                                            Selesai
                                        </span>
                                    <?php endif; ?>
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
                        <nav class="join" aria-label="Paginasi pengembalian saya">
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
