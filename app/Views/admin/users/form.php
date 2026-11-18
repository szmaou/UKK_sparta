<?php
/** @var bool $isEdit */
/** @var array $errors */
/** @var array $values */
/** @var string|null $error */
/** @var array|null $user */

$isEdit = (bool) ($isEdit ?? false);
$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
$id = (string) ($values['id'] ?? '');
$pageTitle = $isEdit ? 'Edit Pengguna' : 'Tambah Pengguna';
$activeNav = 'pengguna';
$action = $isEdit ? '/admin/users/' . rawurlencode($id) . '/edit' : '/admin/users/create';
$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';

$currentUserId = (string) ($user['id'] ?? '');
$isSelf = $isEdit && $id !== '' && (string) $id === $currentUserId;

$selectedRole = strtolower((string) ($values['role'] ?? 'peminjam'));
$activeValue = (string) ($values['is_active'] ?? '1');

ob_start();
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="/admin/users" class="btn btn-ghost btn-sm -ml-3">← Kembali ke kelola pengguna</a>
        <h2 class="mt-3 text-3xl font-black tracking-tight"><?= e($pageTitle) ?></h2>
        <p class="mt-2 text-slate-600"><?= $isEdit ? 'Perbarui identitas, hak akses, atau status akun pengguna.' : 'Buat akun pengguna baru dengan peran yang sesuai.' ?></p>
    </div>

    <?php if (!empty($error)): ?>
        <div role="alert" class="alert alert-error alert-soft"><span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <?php if ($isSelf): ?>
        <div role="note" class="alert alert-warning alert-soft">
            <span class="app-icon" aria-hidden="true"><?= icon('circle-exclamation-point') ?></span>
            <span>Anda sedang mengedit akun sendiri. Perubahan peran atau penonaktifan akun sendiri dibatasi oleh sistem untuk mencegah kehilangan akses.</span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" class="card border border-base-300 bg-base-100 shadow-sm">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <fieldset class="fieldset card-body gap-5 p-5 sm:p-7">
            <legend class="sr-only">Data pengguna</legend>

            <div>
                <label class="fieldset-label font-semibold" for="name">Nama lengkap <span class="text-red-700">*</span></label>
                <input id="name" name="name" type="text" maxlength="100" required value="<?= e((string) ($values['name'] ?? '')) ?>" class="input mt-2 w-full <?= $fieldError('name') !== '' ? 'input-error' : '' ?>" aria-describedby="name-help<?= $fieldError('name') !== '' ? ' name-error' : '' ?>" aria-invalid="<?= $fieldError('name') !== '' ? 'true' : 'false' ?>">
                <p id="name-help" class="mt-2 text-xs text-slate-500">Maksimal 100 karakter.</p>
                <?php if ($fieldError('name') !== ''): ?><p id="name-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('name')) ?></p><?php endif; ?>
            </div>

            <div>
                <label class="fieldset-label font-semibold" for="username">Username <span class="text-red-700">*</span></label>
                <input id="username" name="username" type="text" maxlength="50" required value="<?= e((string) ($values['username'] ?? '')) ?>" class="input mt-2 w-full <?= $fieldError('username') !== '' ? 'input-error' : '' ?>" aria-describedby="username-help<?= $fieldError('username') !== '' ? ' username-error' : '' ?>" aria-invalid="<?= $fieldError('username') !== '' ? 'true' : 'false' ?>">
                <p id="username-help" class="mt-2 text-xs text-slate-500">Maksimal 50 karakter, digunakan saat masuk ke sistem.</p>
                <?php if ($fieldError('username') !== ''): ?><p id="username-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('username')) ?></p><?php endif; ?>
            </div>

            <div>
                <label class="fieldset-label font-semibold" for="password">Password <?= $isEdit ? '<span class="font-normal text-slate-500">(opsional)</span>' : '<span class="text-red-700">*</span>' ?></label>
                <input id="password" name="password" type="password" <?= $isEdit ? '' : 'required' ?> minlength="8" autocomplete="<?= $isEdit ? 'new-password' : 'current-password' ?>" value="" class="input mt-2 w-full <?= $fieldError('password') !== '' ? 'input-error' : '' ?>" aria-describedby="password-help<?= $fieldError('password') !== '' ? ' password-error' : '' ?>" aria-invalid="<?= $fieldError('password') !== '' ? 'true' : 'false' ?>">
                <p id="password-help" class="mt-2 text-xs text-slate-500"><?= $isEdit ? 'Kosongkan jika tidak ingin mengubah password. Minimal 8 karakter, maksimal 72 byte.' : 'Minimal 8 karakter, maksimal 72 byte.' ?></p>
                <?php if ($fieldError('password') !== ''): ?><p id="password-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('password')) ?></p><?php endif; ?>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="fieldset-label font-semibold" for="role">Peran <span class="text-red-700">*</span></label>
                    <select id="role" name="role" required class="select mt-2 w-full <?= $fieldError('role') !== '' ? 'select-error' : '' ?>" aria-describedby="role-help<?= $fieldError('role') !== '' ? ' role-error' : '' ?>" aria-invalid="<?= $fieldError('role') !== '' ? 'true' : 'false' ?>">
                        <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="petugas" <?= $selectedRole === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        <option value="peminjam" <?= $selectedRole === 'peminjam' ? 'selected' : '' ?>>Peminjam</option>
                    </select>
                    <p id="role-help" class="mt-2 text-xs text-slate-500">Menentukan menu dan hak akses akun.</p>
                    <?php if ($fieldError('role') !== ''): ?><p id="role-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('role')) ?></p><?php endif; ?>
                </div>

                <div>
                    <label class="fieldset-label font-semibold" for="is_active">Status akun <span class="text-red-700">*</span></label>
                    <select id="is_active" name="is_active" required class="select mt-2 w-full <?= $fieldError('is_active') !== '' ? 'select-error' : '' ?>" aria-describedby="status-help<?= $fieldError('is_active') !== '' ? ' status-error' : '' ?>" aria-invalid="<?= $fieldError('is_active') !== '' ? 'true' : 'false' ?>">
                        <option value="1" <?= $activeValue === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $activeValue === '0' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <p id="status-help" class="mt-2 text-xs text-slate-500">Akun nonaktif tidak dapat masuk ke aplikasi.</p>
                    <?php if ($fieldError('is_active') !== ''): ?><p id="status-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('is_active')) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="mt-2 flex flex-col-reverse gap-3 border-t border-base-300 pt-5 sm:flex-row sm:justify-end">
                <a href="/admin/users" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna' ?></button>
            </div>
        </fieldset>
    </form>

    <?php if ($isEdit && !$isSelf): ?>
        <section class="card border border-warning/30 bg-warning/5 shadow-sm">
            <div class="card-body p-5 sm:p-6">
                <h3 class="card-title text-base text-warning-content font-bold">Nonaktifkan akun pengguna</h3>
                <p class="text-sm text-slate-600">Akun pengguna tidak dihapus permanen agar riwayat peminjaman dan audit log tetap utuh. Tindakan ini mengubah status menjadi nonaktif.</p>
                <form method="post" action="/admin/users/<?= e($id) ?>/delete" onsubmit="return confirm('Nonaktifkan akun pengguna ini? Pengguna tidak akan dapat masuk kembali sampai diaktifkan.')">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn btn-warning mt-3">
                        <span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
                        Nonaktifkan Pengguna
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
