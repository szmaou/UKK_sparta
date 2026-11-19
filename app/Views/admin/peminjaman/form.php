<?php
/** @var array $loan */
/** @var array $values */
/** @var array $errors */
/** @var string $today */
/** @var string $csrfToken */

$loan = is_array($loan ?? null) ? $loan : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$today = (string) ($today ?? date('Y-m-d'));
$pageTitle = 'Koreksi Peminjaman';
$activeNav = 'peminjaman';

$loanId = (string) ($loan['id'] ?? '');
$kode = (string) ($loan['kode'] ?? '—');
$peminjam = (string) ($loan['peminjam_nama'] ?? '—');
$alat = (string) ($loan['alat_nama'] ?? '—');
$stokTersedia = (int) ($loan['stok_tersedia'] ?? 0);
$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';

ob_start();
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="/admin/peminjaman" class="btn btn-ghost btn-sm -ml-3">← Kembali ke data peminjaman</a>
        <h2 class="mt-3 text-3xl font-black tracking-tight">Koreksi pengajuan</h2>
        <p class="mt-2 text-slate-600">Perubahan hanya berlaku selama status masih menunggu. Stok belum dialokasikan pada tahap ini.</p>
    </div>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Kode</p>
                <p class="font-mono text-sm font-bold text-indigo-700"><?= e($kode) ?></p>
            </div>
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Peminjam</p>
                <p class="text-sm font-semibold"><?= e($peminjam) ?></p>
            </div>
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Alat</p>
                <p class="text-sm font-semibold"><?= e($alat) ?></p>
            </div>
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Stok tersedia saat ini</p>
                <p class="text-sm font-semibold tabular-nums"><?= e((string) $stokTersedia) ?> unit</p>
            </div>
        </div>
    </section>

    <?php if ($errors !== []): ?>
        <div role="alert" class="alert alert-error alert-soft">
            <span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
            <div>
                <p class="font-bold">Periksa kesalahan formulir:</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    <?php foreach ($errors as $fieldMessage): ?><li><?= e($fieldMessage) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/peminjaman/<?= e($loanId) ?>/edit" class="card border border-base-300 bg-base-100 shadow-sm">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <fieldset class="fieldset card-body gap-5 p-5 sm:p-7">
            <legend class="sr-only">Data peminjaman</legend>

            <label class="fieldset-label font-semibold" for="jumlah">Jumlah unit <span class="text-red-700">*</span></label>
            <input id="jumlah" name="jumlah" type="number" min="1" max="<?= e((string) $stokTersedia) ?>" required value="<?= e((string) ($values['jumlah'] ?? '')) ?>" class="input w-full <?= $fieldError('jumlah') !== '' ? 'input-error' : '' ?>" aria-invalid="<?= $fieldError('jumlah') !== '' ? 'true' : 'false' ?>">
            <?php if ($fieldError('jumlah') !== ''): ?><p class="text-sm font-medium text-red-700"><?= e($fieldError('jumlah')) ?></p><?php endif; ?>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="fieldset-label font-semibold" for="tanggal_pinjam">Tanggal pinjam <span class="text-red-700">*</span></label>
                    <input id="tanggal_pinjam" name="tanggal_pinjam" type="date" required value="<?= e((string) ($values['tanggal_pinjam'] ?? '')) ?>" class="input w-full <?= $fieldError('tanggal_pinjam') !== '' ? 'input-error' : '' ?>" aria-invalid="<?= $fieldError('tanggal_pinjam') !== '' ? 'true' : 'false' ?>">
                    <?php if ($fieldError('tanggal_pinjam') !== ''): ?><p class="text-sm font-medium text-red-700"><?= e($fieldError('tanggal_pinjam')) ?></p><?php endif; ?>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="fieldset-label font-semibold" for="tanggal_jatuh_tempo">Jatuh tempo <span class="text-red-700">*</span></label>
                    <input id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" type="date" required value="<?= e((string) ($values['tanggal_jatuh_tempo'] ?? '')) ?>" class="input w-full <?= $fieldError('tanggal_jatuh_tempo') !== '' ? 'input-error' : '' ?>" aria-invalid="<?= $fieldError('tanggal_jatuh_tempo') !== '' ? 'true' : 'false' ?>">
                    <?php if ($fieldError('tanggal_jatuh_tempo') !== ''): ?><p class="text-sm font-medium text-red-700"><?= e($fieldError('tanggal_jatuh_tempo')) ?></p><?php endif; ?>
                </div>
            </div>

            <label class="fieldset-label font-semibold" for="catatan">Tujuan / catatan <span class="font-normal text-slate-500">(opsional)</span></label>
            <textarea id="catatan" name="catatan" rows="4" maxlength="1000" class="textarea w-full <?= $fieldError('catatan') !== '' ? 'textarea-error' : '' ?>"><?= e((string) ($values['catatan'] ?? '')) ?></textarea>
            <?php if ($fieldError('catatan') !== ''): ?><p class="text-sm font-medium text-red-700"><?= e($fieldError('catatan')) ?></p><?php endif; ?>

            <div class="mt-2 flex flex-col-reverse gap-3 border-t border-base-300 pt-5 sm:flex-row sm:justify-end">
                <a href="/admin/peminjaman" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </fieldset>
    </form>

    <section class="card border border-red-200 bg-red-50 shadow-sm">
        <div class="card-body p-5 sm:p-6">
            <h3 class="card-title text-base text-red-900">Hapus pengajuan</h3>
            <p class="text-sm text-red-800">Penghapusan hanya untuk pengajuan menunggu atau ditolak. Transaksi yang sudah disetujui menyimpan riwayat stok.</p>
            <form method="post" action="/admin/peminjaman/<?= e($loanId) ?>/delete" onsubmit="return confirm('Hapus pengajuan ini? Tindakan tidak dapat dibatalkan.')">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-error mt-3"><span class="app-icon" aria-hidden="true"><?= icon('trash') ?></span>Hapus Pengajuan</button>
            </form>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
