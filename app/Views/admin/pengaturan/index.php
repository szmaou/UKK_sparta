<?php
/** @var array $errors */
/** @var array $values */
/** @var string|null $success */
/** @var string|null $error */

$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
$tarif = (string) ($values['tarif_denda'] ?? '0');
$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';
$formatRupiah = static fn (int|float|string $value): string => 'Rp ' . number_format((float) $value, 0, ',', '.');
$pageTitle = 'Pengaturan Denda';
$activeNav = 'pengaturan';

ob_start();
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <p class="text-sm font-semibold text-indigo-700">Pengaturan sistem</p>
        <h2 class="mt-1 text-3xl font-black tracking-tight">Tarif Denda</h2>
        <p class="mt-2 text-slate-600">Atur tarif keterlambatan yang dipakai saat Petugas memverifikasi pengembalian.</p>
    </div>

    <?php if (!empty($success)): ?>
        <div role="alert" class="alert alert-success alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('check') ?></span><span><?= e($success) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div role="alert" class="alert alert-error alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div class="stat-title">Tarif saat ini</div>
            <div class="stat-value text-2xl tabular-nums"><?= e($formatRupiah($tarif)) ?></div>
            <div class="stat-desc whitespace-normal">per hari per unit</div>
        </div>
        <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div class="stat-title">Rumus</div>
            <div class="stat-value text-lg">Hari &times; Unit &times; Tarif</div>
            <div class="stat-desc whitespace-normal">Hanya hari setelah jatuh tempo yang dihitung.</div>
        </div>
    </div>

    <form method="post" action="/admin/pengaturan" class="card border border-base-300 bg-base-100 shadow-sm">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <fieldset class="fieldset card-body gap-5 p-5 sm:p-7">
            <legend class="sr-only">Tarif denda</legend>
            <label class="fieldset-label font-semibold" for="tarif_denda">Tarif denda per hari per unit <span class="text-red-700">*</span></label>
            <div class="join w-full sm:max-w-xs">
                <span class="btn join-item pointer-events-none bg-base-200 text-slate-600">Rp</span>
                <input id="tarif_denda" name="tarif_denda" type="number" min="0" max="1000000" step="1" required
                       value="<?= e($tarif) ?>"
                       class="input join-item w-full <?= $fieldError('tarif_denda') !== '' ? 'input-error' : '' ?>"
                       aria-describedby="tarif-help<?= $fieldError('tarif_denda') !== '' ? ' tarif-error' : '' ?>"
                       aria-invalid="<?= $fieldError('tarif_denda') !== '' ? 'true' : 'false' ?>">
            </div>
            <p id="tarif-help" class="fieldset-label text-slate-500">Isi 0 untuk bebas denda. Maksimal Rp 1.000.000 per hari per unit.</p>
            <?php if ($fieldError('tarif_denda') !== ''): ?><p id="tarif-error" class="text-sm font-medium text-red-700"><?= e($fieldError('tarif_denda')) ?></p><?php endif; ?>
            <p class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs leading-relaxed text-slate-700">
                Perubahan tarif hanya berlaku untuk verifikasi pengembalian berikutnya. Denda yang sudah tercatat pada riwayat
                pengembalian tidak dihitung ulang.
            </p>
            <div class="mt-2 flex flex-col-reverse gap-3 border-t border-base-300 pt-5 sm:flex-row sm:justify-end">
                <a href="/admin/dashboard" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Tarif</button>
            </div>
        </fieldset>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
