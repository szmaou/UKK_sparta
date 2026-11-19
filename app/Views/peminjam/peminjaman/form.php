<?php
/** @var array $alat */
/** @var array $values */
/** @var array $errors */
/** @var string $csrfToken */
/** @var string|null $error */

$alat = is_array($alat ?? null) ? $alat : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];

$pageTitle = 'Ajukan Peminjaman';
$activeNav = 'katalog';

$alatId = (string) ($values['alat_id'] ?? $alat['id'] ?? '');
$alatNama = (string) ($alat['nama'] ?? 'Alat');
$alatKode = (string) ($alat['kode'] ?? '—');
$stokTersedia = (int) ($alat['stok_tersedia'] ?? 0);
$alatFoto = trim((string) ($alat['foto'] ?? ''));

$today = date('Y-m-d');
$defaultDue = date('Y-m-d', strtotime('+3 days'));

$valJumlah = (string) ($values['jumlah'] ?? '1');
$valTanggalPinjam = (string) ($values['tanggal_pinjam'] ?? $today);
$valTanggalJatuhTempo = (string) ($values['tanggal_jatuh_tempo'] ?? $defaultDue);
$valTujuan = (string) ($values['tujuan'] ?? '');

$fieldError = static fn (string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';

ob_start();
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="/peminjam/alat" class="btn btn-ghost btn-sm -ml-3">
            ← Kembali ke Katalog Alat
        </a>
        <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Ajukan Peminjaman</h2>
        <p class="mt-2 text-slate-600">Lengkapi data rencana peminjaman alat praktik sekolah.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div role="alert" class="alert alert-error alert-soft">
            <span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Selected Tool Overview -->
    <div class="card border border-indigo-100 bg-indigo-50/50 shadow-xs">
        <div class="card-body p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <?php if ($alatFoto !== ''): ?>
                        <img src="/uploads/alat/<?= rawurlencode($alatFoto) ?>" alt="Foto <?= e($alatNama) ?>" class="h-20 w-20 shrink-0 rounded-xl border border-indigo-100 bg-white object-cover">
                    <?php else: ?>
                        <span class="app-icon grid h-20 w-20 shrink-0 place-items-center rounded-xl border border-indigo-100 bg-white text-indigo-300" aria-hidden="true"><?= icon('image') ?></span>
                    <?php endif; ?>
                    <div>
                        <span class="font-mono text-xs font-semibold text-indigo-700"><?= e($alatKode) ?></span>
                        <h3 class="text-xl font-bold text-slate-900"><?= e($alatNama) ?></h3>
                    </div>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-white px-4 py-2 text-right">
                    <p class="text-xs text-slate-500">Stok Tersedia Saat Ini</p>
                    <p class="text-lg font-bold text-indigo-900"><?= e((string) $stokTersedia) ?> <span class="text-xs font-normal">unit</span></p>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-600">
                Persetujuan oleh petugas akan mencadangkan unit dari stok tersedia. Pengajuan berstatus menunggu tidak langsung memotong stok.
            </p>
        </div>
    </div>

    <form method="post" action="/peminjam/peminjaman/create" class="card border border-base-300 bg-base-100 shadow-sm">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="alat_id" value="<?= e($alatId) ?>">

        <fieldset class="fieldset card-body gap-5 p-5 sm:p-7">
            <legend class="sr-only">Formulir Peminjaman Alat</legend>

            <!-- Quantity -->
            <div>
                <label class="fieldset-label font-semibold" for="jumlah">
                    Jumlah Unit yang Dipinjam <span class="text-red-700">*</span>
                </label>
                <input
                    id="jumlah"
                    name="jumlah"
                    type="number"
                    min="1"
                    <?= $stokTersedia > 0 ? 'max="' . $stokTersedia . '"' : '' ?>
                    step="1"
                    required
                    value="<?= e($valJumlah) ?>"
                    class="input mt-2 w-full sm:max-w-xs <?= $fieldError('jumlah') !== '' ? 'input-error' : '' ?>"
                    aria-describedby="jumlah-help<?= $fieldError('jumlah') !== '' ? ' jumlah-error' : '' ?>"
                    aria-invalid="<?= $fieldError('jumlah') !== '' ? 'true' : 'false' ?>"
                >
                <p id="jumlah-help" class="mt-2 text-xs text-slate-500">
                    Jumlah bilangan bulat positif (minimal 1 unit<?= $stokTersedia > 0 ? ', maksimal ' . $stokTersedia . ' unit' : '' ?>).
                </p>
                <?php if ($fieldError('jumlah') !== ''): ?>
                    <p id="jumlah-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('jumlah')) ?></p>
                <?php endif; ?>
            </div>

            <!-- Dates (Borrow date & Due date) -->
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="fieldset-label font-semibold" for="tanggal_pinjam">
                        Tanggal Mulai Pinjam <span class="text-red-700">*</span>
                    </label>
                    <input
                        id="tanggal_pinjam"
                        name="tanggal_pinjam"
                        type="date"
                        required
                        value="<?= e($valTanggalPinjam) ?>"
                        class="input mt-2 w-full <?= $fieldError('tanggal_pinjam') !== '' ? 'input-error' : '' ?>"
                        aria-describedby="tgl-pinjam-help<?= $fieldError('tanggal_pinjam') !== '' ? ' tgl-pinjam-error' : '' ?>"
                        aria-invalid="<?= $fieldError('tanggal_pinjam') !== '' ? 'true' : 'false' ?>"
                    >
                    <p id="tgl-pinjam-help" class="mt-2 text-xs text-slate-500">Rencana tanggal pengambilan alat.</p>
                    <?php if ($fieldError('tanggal_pinjam') !== ''): ?>
                        <p id="tgl-pinjam-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('tanggal_pinjam')) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="fieldset-label font-semibold" for="tanggal_jatuh_tempo">
                        Tanggal Jatuh Tempo (Pengembalian) <span class="text-red-700">*</span>
                    </label>
                    <input
                        id="tanggal_jatuh_tempo"
                        name="tanggal_jatuh_tempo"
                        type="date"
                        required
                        value="<?= e($valTanggalJatuhTempo) ?>"
                        class="input mt-2 w-full <?= $fieldError('tanggal_jatuh_tempo') !== '' ? 'input-error' : '' ?>"
                        aria-describedby="tgl-tempo-help<?= $fieldError('tanggal_jatuh_tempo') !== '' ? ' tgl-tempo-error' : '' ?>"
                        aria-invalid="<?= $fieldError('tanggal_jatuh_tempo') !== '' ? 'true' : 'false' ?>"
                    >
                    <p id="tgl-tempo-help" class="mt-2 text-xs text-slate-500">Batas akhir pengembalian (harus sama atau setelah tanggal mulai pinjam).</p>
                    <?php if ($fieldError('tanggal_jatuh_tempo') !== ''): ?>
                        <p id="tgl-tempo-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('tanggal_jatuh_tempo')) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Purpose / Tujuan -->
            <div>
                <label class="fieldset-label font-semibold" for="tujuan">
                    Tujuan Peminjaman <span class="text-red-700">*</span>
                </label>
                <textarea
                    id="tujuan"
                    name="tujuan"
                    required
                    maxlength="1000"
                    rows="4"
                    class="textarea mt-2 w-full <?= $fieldError('tujuan') !== '' ? 'textarea-error' : '' ?>"
                    placeholder="Contoh: Praktik pemesinan bubut mata pelajaran Dasar Teknik Mesin kelas XI..."
                    aria-describedby="tujuan-help<?= $fieldError('tujuan') !== '' ? ' tujuan-error' : '' ?>"
                    aria-invalid="<?= $fieldError('tujuan') !== '' ? 'true' : 'false' ?>"
                ><?= e($valTujuan) ?></textarea>
                <p id="tujuan-help" class="mt-2 text-xs text-slate-500">Jelaskan kegiatan atau mata pelajaran yang memerlukan alat ini (maksimal 1.000 karakter).</p>
                <?php if ($fieldError('tujuan') !== ''): ?>
                    <p id="tujuan-error" class="mt-2 text-sm font-medium text-red-700"><?= e($fieldError('tujuan')) ?></p>
                <?php endif; ?>
            </div>

            <div class="mt-4 flex flex-col-reverse gap-3 border-t border-base-300 pt-5 sm:flex-row sm:justify-end">
                <a href="/peminjam/alat" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary" <?= $stokTersedia < 1 ? 'disabled' : '' ?>>
                    <span class="app-icon" aria-hidden="true"><?= icon('check') ?></span>
                    Kirim Pengajuan Peminjaman
                </button>
            </div>
        </fieldset>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
