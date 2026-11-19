<?php
/** @var array $values */
/** @var array $errors */
/** @var array $items */
/** @var array $summary */
/** @var bool $truncated */
/** @var string $csrfToken */

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$items = is_array($items ?? null) ? $items : [];
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'unit' => 0, 'denda' => 0.0, 'per_status' => []];
$truncated = (bool) ($truncated ?? false);
$pageTitle = 'Laporan Peminjaman';
$activeNav = 'laporan';

$statusLabels = [
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
    'return_requested' => 'Menunggu Verifikasi',
    'returned' => 'Dikembalikan',
];
$formatRupiah = static fn (float|int|string $value): string => 'Rp ' . number_format((float) $value, 0, ',', '.');
$printUrl = '/petugas/laporan/cetak?' . http_build_query(array_filter([
    'from' => (string) ($values['from'] ?? ''),
    'to' => (string) ($values['to'] ?? ''),
    'status' => (string) ($values['status'] ?? ''),
], static fn ($v) => $v !== ''));
$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-indigo-700">Petugas</p>
            <h2 class="mt-1 text-3xl font-black tracking-tight">Laporan peminjaman</h2>
            <p class="mt-2 text-slate-600">Rekap transaksi per rentang tanggal. Angka diambil langsung dari database.</p>
        </div>
        <a href="<?= e($printUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary">
            <span class="app-icon" aria-hidden="true"><?= icon('file-text') ?></span>
            Cetak Laporan
        </a>
    </section>

    <?php if ($errors !== []): ?>
        <div role="alert" class="alert alert-error alert-soft">
            <span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
            <span><?= e(implode(' ', $errors)) ?></span>
        </div>
    <?php endif; ?>

    <form method="get" action="/petugas/laporan" class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body grid gap-4 p-4 sm:grid-cols-4 sm:p-6">
            <label class="fieldset-label flex-col items-start gap-1 font-semibold" for="from">
                Dari tanggal
                <input id="from" name="from" type="date" value="<?= e((string) ($values['from'] ?? '')) ?>" class="input w-full <?= $fieldError('from') !== '' ? 'input-error' : '' ?>" required>
            </label>
            <label class="fieldset-label flex-col items-start gap-1 font-semibold" for="to">
                Sampai tanggal
                <input id="to" name="to" type="date" value="<?= e((string) ($values['to'] ?? '')) ?>" max="<?= e(date('Y-m-d')) ?>" class="input w-full <?= $fieldError('to') !== '' ? 'input-error' : '' ?>" required>
            </label>
            <label class="fieldset-label flex-col items-start gap-1 font-semibold" for="status">
                Status
                <select id="status" name="status" class="select w-full <?= $fieldError('status') !== '' ? 'select-error' : '' ?>">
                    <option value="">Semua status</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= (string) ($values['status'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit" class="btn btn-neutral w-full">Tampilkan</button>
            </div>
        </div>
    </form>

    <section class="grid gap-4 sm:grid-cols-3">
        <div class="stat rounded-xl border border-base-300 bg-base-100 shadow-sm">
            <div class="stat-title">Jumlah transaksi</div>
            <div class="stat-value text-2xl tabular-nums"><?= e((string) $summary['total']) ?></div>
        </div>
        <div class="stat rounded-xl border border-base-300 bg-base-100 shadow-sm">
            <div class="stat-title">Total unit dipinjam</div>
            <div class="stat-value text-2xl tabular-nums"><?= e((string) $summary['unit']) ?></div>
        </div>
        <div class="stat rounded-xl border border-base-300 bg-base-100 shadow-sm">
            <div class="stat-title">Total denda</div>
            <div class="stat-value text-2xl tabular-nums"><?= e($formatRupiah($summary['denda'])) ?></div>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-4 p-4 sm:p-6">
            <?php if ($truncated): ?>
                <div role="status" class="alert alert-warning alert-soft">
                    <span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
                    <span>Hasil dibatasi <?= e((string) \App\Models\Laporan::MAX_ROWS) ?> baris. Persempit rentang tanggal agar laporan lengkap.</span>
                </div>
            <?php endif; ?>

            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-12 text-center">
                    <span class="app-icon mx-auto grid size-12 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true"><?= icon('file-text') ?></span>
                    <h3 class="mt-4 text-lg font-bold">Tidak ada transaksi</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">Tidak ada peminjaman pada rentang tanggal dan status yang dipilih.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Rekap peminjaman</caption>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Peminjam</th>
                                <th>Alat &amp; kategori</th>
                                <th>Jumlah</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th class="text-right">Denda</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="font-mono text-xs font-bold text-indigo-700"><?= e((string) ($item['kode'] ?? '')) ?></td>
                                <td class="font-semibold"><?= e((string) ($item['peminjam'] ?? '')) ?></td>
                                <td>
                                    <p><?= e((string) ($item['alat'] ?? '')) ?></p>
                                    <p class="text-xs text-slate-500"><?= e((string) ($item['kategori'] ?? '')) ?></p>
                                </td>
                                <td class="tabular-nums"><?= e((string) ($item['jumlah'] ?? '')) ?></td>
                                <td class="whitespace-nowrap text-xs tabular-nums text-slate-600">
                                    <?= e((string) ($item['tanggal_pinjam'] ?? '')) ?> → <?= e((string) ($item['tanggal_jatuh_tempo'] ?? '')) ?>
                                </td>
                                <td><span class="badge badge-sm badge-ghost"><?= e($statusLabels[(string) ($item['status'] ?? '')] ?? (string) ($item['status'] ?? '')) ?></span></td>
                                <td class="text-right tabular-nums"><?= e($formatRupiah((float) ($item['denda'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
