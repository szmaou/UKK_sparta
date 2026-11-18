<?php
/** @var array $items */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $status */
/** @var string $q */
/** @var string $csrfToken */
/** @var string|null $success */
/** @var string|null $error */

$items = is_array($items ?? null) ? $items : [];
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$status = is_string($status ?? null) ? $status : 'active';
$q = is_string($q ?? null) ? trim($q) : '';
$pageTitle = 'Monitoring Pengembalian';
$activeNav = 'pengembalian';

$pageUrl = static function (int $target) use ($status, $q): string {
    $params = [];
    if ($status !== 'active') {
        $params['status'] = $status;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($target > 1) {
        $params['page'] = (string) $target;
    }
    return '/petugas/pengembalian' . ($params !== [] ? '?' . http_build_query($params) : '');
};

$filterUrl = static function (string $targetStatus) use ($q): string {
    $params = [];
    if ($targetStatus !== 'active') {
        $params['status'] = $targetStatus;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    return '/petugas/pengembalian' . ($params !== [] ? '?' . http_build_query($params) : '');
};

$statusBadge = static function (string $status): array {
    return match (strtolower(trim($status))) {
        'approved' => ['Belum Dikembalikan', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'return_requested' => ['Menunggu Verifikasi', 'border-amber-200 bg-amber-50 text-amber-800'],
        'returned' => ['Sudah Diterima', 'border-slate-200 bg-slate-100 text-slate-700'],
        default => [ucfirst($status), 'badge-ghost'],
    };
};

$kondisiLabel = static function (?string $k): string {
    return match (strtolower(trim((string) $k))) {
        'baik' => 'Baik',
        'rusak_ringan' => 'Rusak Ringan',
        'rusak_berat' => 'Rusak Berat',
        'hilang' => 'Hilang',
        default => '—',
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

$tabs = [
    'active' => 'Aktif (Perlu Dipantau)',
    'return_requested' => 'Menunggu Verifikasi',
    'returned' => 'Selesai Dikembalikan',
    'all' => 'Semua Status',
];

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <span class="badge border-indigo-200 bg-indigo-50 font-semibold text-indigo-800">Petugas / Sirkulasi</span>
            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Monitoring Pengembalian</h2>
            <p class="mt-2 text-slate-600">Pantau batas jatuh tempo, periksa pengajuan kembali, dan verifikasi fisik alat di gudang.</p>
        </div>
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

    <!-- Filter and Search Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="join overflow-x-auto" role="tablist" aria-label="Filter status pengembalian">
            <?php foreach ($tabs as $key => $tabLabel): ?>
                <a href="<?= e($filterUrl($key)) ?>"
                   class="btn join-item btn-sm <?= $status === $key ? 'btn-active btn-neutral' : 'btn-ghost' ?>"
                   role="tab"
                   aria-selected="<?= $status === $key ? 'true' : 'false' ?>">
                    <?= e($tabLabel) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="/petugas/pengembalian" class="flex items-center gap-2">
            <?php if ($status !== 'active'): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <label class="input input-sm input-bordered flex items-center gap-2">
                <span class="app-icon text-slate-400" aria-hidden="true"><?= icon('search') ?></span>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama, alat..." class="grow" aria-label="Cari pengembalian">
            </label>
            <button type="submit" class="btn btn-neutral btn-sm">Cari</button>
            <?php if ($q !== ''): ?>
                <a href="<?= e($filterUrl($status)) ?>" class="btn btn-ghost btn-sm" aria-label="Reset pencarian">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-5 p-4 sm:p-6">
            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-14 text-center">
                    <span class="app-icon mx-auto grid size-14 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true">
                        <?= icon('refresh-cw') ?>
                    </span>
                    <h3 class="mt-4 text-xl font-bold">Tidak ada data transaksi pengembalian</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">
                        <?= $q !== '' ? 'Tidak ditemukan transaksi yang cocok dengan pencarian kata kunci tersebut.' : 'Belum ada data peminjaman pada filter status yang dipilih.' ?>
                    </p>
                    <?php if ($q !== '' || $status !== 'active'): ?>
                        <div class="mt-6">
                            <a href="/petugas/pengembalian" class="btn btn-outline btn-sm">
                                Reset Filter
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar transaksi pengembalian alat</caption>
                        <thead>
                            <tr>
                                <th>Kode & Peminjam</th>
                                <th>Alat & Jumlah</th>
                                <th>Jadwal & Keterlambatan</th>
                                <th>Status & Catatan Fisik</th>
                                <th>Denda</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $loanId = (string) ($item['id'] ?? '');
                            $kode = (string) ($item['kode'] ?? '—');
                            $peminjamNama = (string) ($item['peminjam_nama'] ?? '—');
                            $alatNama = (string) ($item['alat_nama'] ?? '—');
                            $jumlah = (int) ($item['jumlah'] ?? 1);
                            $statusItem = (string) ($item['status'] ?? '');
                            [$badgeLabel, $badgeClass] = $statusBadge($statusItem);
                            $tglPinjam = (string) ($item['tanggal_pinjam'] ?? '');
                            $tglTempo = (string) ($item['tanggal_jatuh_tempo'] ?? '');
                            $tglPengajuan = (string) ($item['tanggal_pengajuan'] ?? '');
                            $tglDiterima = (string) ($item['tanggal_diterima'] ?? '');
                            $hariTerlambat = (int) ($item['hari_terlambat'] ?? 0);
                            $kondisi = (string) ($item['kondisi'] ?? '');
                            $denda = (float) ($item['denda'] ?? 0);
                            $canVerify = ($statusItem === 'return_requested');
                            ?>
                            <tr>
                                <td>
                                    <p class="font-mono text-xs font-bold text-indigo-700"><?= e($kode) ?></p>
                                    <p class="text-sm font-semibold text-slate-900"><?= e($peminjamNama) ?></p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900"><?= e($alatNama) ?></p>
                                    <p class="text-xs text-slate-500 tabular-nums font-medium">
                                        <?= e((string) $jumlah) ?> unit
                                    </p>
                                </td>
                                <td>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Pinjam:</span> <?= e($formatDate($tglPinjam)) ?>
                                    </p>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Tempo:</span> <?= e($formatDate($tglTempo)) ?>
                                    </p>
                                    <?php if ($hariTerlambat > 0): ?>
                                        <p class="mt-1 inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700">
                                            <span class="app-icon size-3" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
                                            Terlambat <?= e((string) $hariTerlambat) ?> hari
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-sm <?= $badgeClass ?> font-medium">
                                        <?= e($badgeLabel) ?>
                                    </span>
                                    <?php if ($tglPengajuan !== '' && $statusItem === 'return_requested'): ?>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Pengajuan: <?= e($formatDate($tglPengajuan)) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($statusItem === 'returned'): ?>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Kondisi: <span class="font-semibold"><?= e($kondisiLabel($kondisi)) ?></span>
                                        </p>
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
                                    <?php if ($canVerify): ?>
                                        <a href="/petugas/pengembalian/<?= e($loanId) ?>/verify" class="btn btn-primary btn-sm">
                                            <span class="app-icon" aria-hidden="true"><?= icon('square-check') ?></span>
                                            Verifikasi Fisik
                                        </a>
                                    <?php elseif ($statusItem === 'returned'): ?>
                                        <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                            <span class="app-icon size-4 text-emerald-600" aria-hidden="true"><?= icon('check') ?></span>
                                            Selesai
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-slate-400">
                                            Menunggu pengembalian
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
                        <nav class="join" aria-label="Paginasi monitoring pengembalian">
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
