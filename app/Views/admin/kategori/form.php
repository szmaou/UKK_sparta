<?php
/** @var bool $isEdit */
/** @var array $errors */
/** @var array $values */
/** @var string|null $error */

$isEdit = (bool) ($isEdit ?? false);
$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
$id = (string) ($values['id'] ?? '');
$pageTitle = $isEdit ? 'Edit Kategori' : 'Tambah Kategori';
$activeNav = 'kategori';
$action = $isEdit ? '/admin/kategori/' . rawurlencode($id) . '/edit' : '/admin/kategori/create';
$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';

ob_start();
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="/admin/kategori" class="btn btn-ghost btn-sm -ml-3">← Kembali ke kategori</a>
        <h2 class="mt-3 text-3xl font-black tracking-tight"><?= e($pageTitle) ?></h2>
        <p class="mt-2 text-slate-600"><?= $isEdit ? 'Perbarui nama dan deskripsi kategori.' : 'Buat kelompok baru untuk inventaris alat.' ?></p>
    </div>

    <?php if (!empty($error)): ?><div role="alert" class="alert alert-error alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span><span><?= e($error) ?></span></div><?php endif; ?>

    <form method="post" action="<?= e($action) ?>" class="card border border-base-300 bg-base-100 shadow-sm">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <fieldset class="fieldset card-body gap-5 p-5 sm:p-7">
            <legend class="sr-only">Data kategori</legend>
            <label class="fieldset-label font-semibold" for="nama">Nama kategori <span class="text-red-700">*</span></label>
            <input id="nama" name="nama" type="text" maxlength="100" required value="<?= e((string) ($values['nama'] ?? '')) ?>" class="input w-full <?= $fieldError('nama') !== '' ? 'input-error' : '' ?>" aria-describedby="nama-help<?= $fieldError('nama') !== '' ? ' nama-error' : '' ?>" aria-invalid="<?= $fieldError('nama') !== '' ? 'true' : 'false' ?>">
            <p id="nama-help" class="fieldset-label text-slate-500">Maksimal 100 karakter dan harus mudah dikenali.</p>
            <?php if ($fieldError('nama') !== ''): ?><p id="nama-error" class="text-sm font-medium text-red-700"><?= e($fieldError('nama')) ?></p><?php endif; ?>

            <label class="fieldset-label font-semibold" for="deskripsi">Deskripsi <span class="font-normal text-slate-500">(opsional)</span></label>
            <textarea id="deskripsi" name="deskripsi" maxlength="1000" rows="5" class="textarea w-full <?= $fieldError('deskripsi') !== '' ? 'textarea-error' : '' ?>" aria-describedby="deskripsi-help<?= $fieldError('deskripsi') !== '' ? ' deskripsi-error' : '' ?>" aria-invalid="<?= $fieldError('deskripsi') !== '' ? 'true' : 'false' ?>"><?= e((string) ($values['deskripsi'] ?? '')) ?></textarea>
            <p id="deskripsi-help" class="fieldset-label text-slate-500">Jelaskan jenis alat yang termasuk kategori ini. Maksimal 1.000 karakter.</p>
            <?php if ($fieldError('deskripsi') !== ''): ?><p id="deskripsi-error" class="text-sm font-medium text-red-700"><?= e($fieldError('deskripsi')) ?></p><?php endif; ?>

            <div class="mt-2 flex flex-col-reverse gap-3 border-t border-base-300 pt-5 sm:flex-row sm:justify-end">
                <a href="/admin/kategori" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' ?></button>
            </div>
        </fieldset>
    </form>

    <?php if ($isEdit): ?>
        <section class="card border border-red-200 bg-red-50 shadow-sm">
            <div class="card-body p-5 sm:p-6">
                <h3 class="card-title text-base text-red-900">Hapus kategori</h3>
                <p class="text-sm text-red-800">Kategori hanya dapat dihapus jika tidak digunakan alat.</p>
                <form method="post" action="/admin/kategori/<?= e($id) ?>/delete" onsubmit="return confirm('Hapus kategori ini? Tindakan ini tidak dapat dibatalkan.')">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn btn-error mt-3"><span class="app-icon" aria-hidden="true"><?= icon('trash') ?></span>Hapus Kategori</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
