<?php
/** @var array $row */
/** @var array $values */
/** @var array $errors */
/** @var string $today */
/** @var string $csrfToken */

$row = is_array($row ?? null) ? $row : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$today = (string) ($today ?? date('Y-m-d'));
$pageTitle = 'Koreksi Pengembalian';
$activeNav = 'pengembalian';

$loanId = (string) ($row['loan_id'] ?? '');
$tglPinjam = substr((string) ($row['tanggal_pinjam'] ?? ''), 0, 10);
$tglPengajuan = substr((string) ($row['tanggal_pengajuan'] ?? ''), 0, 10);
$minDate = max($tglPinjam, $tglPengajuan);
$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';
$kondisiOptions = [
    'baik' => 'Baik — Alat lengkap, berfungsi normal tanpa kerusakan',
    'rusak_ringan' => 'Rusak Ringan — Berfungsi dengan cacat kosmetik / aus wajar',
    'rusak_berat' => 'Rusak Berat — Tidak berfungsi / komponen utama rusak',
    'hilang' => 'Hilang — Alat atau kelengkapannya tidak dikembalikan',
];

ob_start();
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="/admin/pengembalian" class="btn btn-ghost btn-sm -ml-3">← Kembali ke data pengembalian</a>
        <h2 class="mt-3 text-3xl font-black tracking-tight">Koreksi pengembalian</h2>
        <p class="mt-2 text-slate-600">Kondisi dan tanggal penerimaan dapat diperbaiki. Denda dihitung ulang dari tanggal jatuh tempo. Stok tersedia hanya berubah bila kondisi berubah antara <span class="font-semibold">Baik</span> dan kondisi rusak/hilang.</p>
    </div>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Kode</p>
                <p class="font-mono text-sm font-bold text-indigo-700"><?= e((string) ($row['kode'] ?? '')) ?></p>
            </div>
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Peminjam</p>
                <p class="text-sm font-semibold"><?= e((string) ($row['peminjam_nama'] ?? '')) ?></p>
            </div>
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Alat</p>
                <p class="text-sm font-semibold"><?= e((string) ($row['alat_nama'] ?? '')) ?> (<?= e((string) ($row['jumlah'] ?? '')) ?> unit)</p>
            </div>
            <div class="rounded-lg bg-base-200/60 p-3">
                <p class="text-xs text-slate-500">Jatuh tempo</p>
                <p class="text-sm font-semibold tabular-nums"><?= e(substr((string) ($row['tanggal_jatuh_tempo'] ?? ''), 0, 10)) ?></p>
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

    <form method="post" action="/admin/pengembalian/<?= e($loanId) ?>/edit" class="card border border-base-300 bg-base-100 shadow-sm">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <fieldset class="fieldset card-body gap-5 p-5 sm:p-7">
            <legend class="sr-only">Data pengembalian</legend>

            <label class="fieldset-label font-semibold" for="tanggal_diterima">Tanggal diterima <span class="text-red-700">*</span></label>
            <input id="tanggal_diterima" name="tanggal_diterima" type="date" required min="<?= e($minDate) ?>" max="<?= e($today) ?>" value="<?= e((string) ($values['tanggal_diterima'] ?? '')) ?>" class="input w-full <?= $fieldError('tanggal_diterima') !== '' ? 'input-error' : '' ?>" aria-invalid="<?= $fieldError('tanggal_diterima') !== '' ? 'true' : 'false' ?>">
            <p class="fieldset-label text-slate-500">Tidak boleh sebelum tanggal pinjam atau pengajuan, dan tidak melebihi hari ini.</p>
            <?php if ($fieldError('tanggal_diterima') !== ''): ?><p class="text-sm font-medium text-red-700"><?= e($fieldError('tanggal_diterima')) ?></p><?php endif; ?>

            <label class="fieldset-label font-semibold" for="kondisi">Kondisi alat <span class="text-red-700">*</span></label>
            <select id="kondisi" name="kondisi" class="select w-full <?= $fieldError('kondisi') !== '' ? 'select-error' : '' ?>" aria-invalid="<?= $fieldError('kondisi') !== '' ? 'true' : 'false' ?>">
                <?php foreach ($kondisiOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= (string) ($values['kondisi'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('kondisi') !== ''): ?><p class="text-sm font-medium text-red-700"><?= e($fieldError('kondisi')) ?></p><?php endif; ?>

            <div class="mt-2 flex flex-col-reverse gap-3 border-t border-base-300 pt-5 sm:flex-row sm:justify-end">
                <a href="/admin/pengembalian" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </fieldset>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
