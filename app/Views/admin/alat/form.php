<?php
/** @var bool $isEdit */
/** @var array $errors */
/** @var array $values */
/** @var array $categories */
/** @var string|null $error */

$isEdit = (bool) ($isEdit ?? false);
$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
$categories = is_array($categories ?? null) ? $categories : [];
$id = (string) ($values['id'] ?? '');
$pageTitle = $isEdit ? 'Edit Alat' : 'Tambah Alat';
$activeNav = 'alat';
$action = $isEdit ? '/admin/alat/' . rawurlencode($id) . '/edit' : '/admin/alat/create';
$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';
$activeValue = (string) ($values['is_active'] ?? '1');

ob_start();
?>
<div class="mx-auto max-w-4xl space-y-6">
    <div><a href="/admin/alat" class="btn btn-ghost btn-sm -ml-3">← Kembali ke data alat</a><h2 class="mt-3 text-3xl font-black tracking-tight"><?= e($pageTitle) ?></h2><p class="mt-2 text-slate-600"><?= $isEdit ? 'Perbarui data master dan status alat.' : 'Tambahkan alat baru ke inventaris sekolah.' ?></p></div>
    <?php if (!empty($error)): ?><div role="alert" class="alert alert-error alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span><span><?= e($error) ?></span></div><?php endif; ?>

    <form method="post" action="<?= e($action) ?>" class="card border border-base-300 bg-base-100 shadow-sm">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <fieldset class="fieldset card-body gap-5 p-5 sm:p-7">
            <legend class="sr-only">Data alat</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="fieldset-label font-semibold" for="kode">Kode alat <span class="text-red-700">*</span></label>
                    <input id="kode" name="kode" type="text" maxlength="30" required value="<?= e((string) ($values['kode'] ?? '')) ?>" class="input mt-2 w-full <?= $fieldError('kode') !== '' ? 'input-error' : '' ?>" aria-describedby="kode-help<?= $fieldError('kode') !== '' ? ' kode-error' : '' ?>" aria-invalid="<?= $fieldError('kode') !== '' ? 'true' : 'false' ?>">
                    <p id="kode-help" class="mt-2 text-xs text-slate-500">Kode unik, maksimal 30 karakter.</p><?php if ($fieldError('kode') !== ''): ?><p id="kode-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('kode')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="fieldset-label font-semibold" for="nama">Nama alat <span class="text-red-700">*</span></label>
                    <input id="nama" name="nama" type="text" maxlength="150" required value="<?= e((string) ($values['nama'] ?? '')) ?>" class="input mt-2 w-full <?= $fieldError('nama') !== '' ? 'input-error' : '' ?>" aria-describedby="nama-help<?= $fieldError('nama') !== '' ? ' nama-error' : '' ?>" aria-invalid="<?= $fieldError('nama') !== '' ? 'true' : 'false' ?>">
                    <p id="nama-help" class="mt-2 text-xs text-slate-500">Nama jelas, maksimal 150 karakter.</p><?php if ($fieldError('nama') !== ''): ?><p id="nama-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('nama')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="fieldset-label font-semibold" for="kategori_id">Kategori <span class="text-red-700">*</span></label>
                    <select id="kategori_id" name="kategori_id" required class="select mt-2 w-full <?= $fieldError('kategori_id') !== '' ? 'select-error' : '' ?>" aria-describedby="kategori-help<?= $fieldError('kategori_id') !== '' ? ' kategori-error' : '' ?>" aria-invalid="<?= $fieldError('kategori_id') !== '' ? 'true' : 'false' ?>">
                        <option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><option value="<?= e((string) ($category['id'] ?? '')) ?>" <?= (string) ($values['kategori_id'] ?? '') === (string) ($category['id'] ?? '') ? 'selected' : '' ?>><?= e((string) ($category['nama'] ?? '')) ?></option><?php endforeach; ?>
                    </select>
                    <p id="kategori-help" class="mt-2 text-xs text-slate-500">Pilih kelompok alat yang sesuai.</p><?php if ($fieldError('kategori_id') !== ''): ?><p id="kategori-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('kategori_id')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="fieldset-label font-semibold" for="stok_total">Stok total <span class="text-red-700">*</span></label>
                    <input id="stok_total" name="stok_total" type="number" min="0" max="4294967295" step="1" required value="<?= e((string) ($values['stok_total'] ?? '0')) ?>" class="input mt-2 w-full <?= $fieldError('stok_total') !== '' ? 'input-error' : '' ?>" aria-describedby="stok-help<?= $fieldError('stok_total') !== '' ? ' stok-error' : '' ?>" aria-invalid="<?= $fieldError('stok_total') !== '' ? 'true' : 'false' ?>">
                    <p id="stok-help" class="mt-2 text-xs text-slate-500">Bilangan bulat 0–4.294.967.295. Stok aktif tidak boleh lebih kecil dari jumlah dipinjam.</p><?php if ($fieldError('stok_total') !== ''): ?><p id="stok-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('stok_total')) ?></p><?php endif; ?>
                </div>
            </div>

            <label class="fieldset-label font-semibold" for="deskripsi">Deskripsi <span class="font-normal text-slate-500">(opsional)</span></label>
            <textarea id="deskripsi" name="deskripsi" maxlength="1000" rows="5" class="textarea w-full <?= $fieldError('deskripsi') !== '' ? 'textarea-error' : '' ?>" aria-describedby="deskripsi-help<?= $fieldError('deskripsi') !== '' ? ' deskripsi-error' : '' ?>" aria-invalid="<?= $fieldError('deskripsi') !== '' ? 'true' : 'false' ?>"><?= e((string) ($values['deskripsi'] ?? '')) ?></textarea>
            <p id="deskripsi-help" class="fieldset-label text-slate-500">Catatan spesifikasi atau penggunaan. Maksimal 1.000 karakter.</p><?php if ($fieldError('deskripsi') !== ''): ?><p id="deskripsi-error" class="text-sm font-medium text-red-700"><?= e($fieldError('deskripsi')) ?></p><?php endif; ?>

            <label class="fieldset-label font-semibold" for="is_active">Status alat <span class="text-red-700">*</span></label>
            <select id="is_active" name="is_active" required class="select w-full sm:max-w-xs <?= $fieldError('is_active') !== '' ? 'select-error' : '' ?>" aria-describedby="status-help<?= $fieldError('is_active') !== '' ? ' status-error' : '' ?>" aria-invalid="<?= $fieldError('is_active') !== '' ? 'true' : 'false' ?>"><option value="1" <?= $activeValue === '1' ? 'selected' : '' ?>>Aktif</option><option value="0" <?= $activeValue === '0' ? 'selected' : '' ?>>Nonaktif</option></select>
            <p id="status-help" class="fieldset-label text-slate-500">Alat nonaktif tetap tersimpan, tetapi tidak tersedia untuk transaksi baru.</p><?php if ($fieldError('is_active') !== ''): ?><p id="status-error" class="text-sm font-medium text-red-700"><?= e($fieldError('is_active')) ?></p><?php endif; ?>

            <div class="mt-2 flex flex-col-reverse gap-3 border-t border-base-300 pt-5 sm:flex-row sm:justify-end"><a href="/admin/alat" class="btn btn-ghost">Batal</a><button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Alat' ?></button></div>
        </fieldset>
    </form>

    <?php if ($isEdit): ?>
        <section class="card border border-red-200 bg-red-50 shadow-sm"><div class="card-body p-5 sm:p-6"><h3 class="card-title text-base text-red-900">Hapus atau nonaktifkan alat</h3><p class="text-sm text-red-800">Jika alat memiliki riwayat transaksi, sistem akan menonaktifkannya agar histori tetap utuh.</p><form method="post" action="/admin/alat/<?= e($id) ?>/delete" onsubmit="return confirm('Hapus alat ini? Alat dengan riwayat transaksi akan dinonaktifkan.')"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button type="submit" class="btn btn-error mt-3"><span class="app-icon" aria-hidden="true"><?= icon('trash') ?></span>Hapus Alat</button></form></div></section>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
