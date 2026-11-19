<?php
/** @var array $loan */
/** @var array $values */
/** @var array $errors */
/** @var int $tarifDenda */
/** @var string $today */
/** @var string $csrfToken */

$loan = is_array($loan ?? null) ? $loan : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$tarifDenda = (int) ($tarifDenda ?? 0);
$today = (string) ($today ?? date('Y-m-d'));
$pageTitle = 'Verifikasi Pengembalian';
$activeNav = 'pengembalian';

$loanId = (string) ($loan['id'] ?? '');
$kode = (string) ($loan['kode'] ?? '—');
$peminjamNama = (string) ($loan['peminjam_nama'] ?? '—');
$alatNama = (string) ($loan['alat_nama'] ?? '—');
$jumlah = (int) ($loan['jumlah'] ?? 1);
$tglPinjam = (string) ($loan['tanggal_pinjam'] ?? '');
$tglTempo = (string) ($loan['tanggal_jatuh_tempo'] ?? '');
$tglPengajuan = (string) ($loan['tanggal_pengajuan'] ?? '');

$selectedTglDiterima = (string) ($values['tanggal_diterima'] ?? $today);
$selectedKondisi = (string) ($values['kondisi'] ?? 'baik');

$formatDate = static function (?string $date): string {
    if (!$date) return '—';
    $time = strtotime($date);
    return $time ? date('d M Y', $time) : $date;
};

// Date bounds for HTML input
// Backend rule: >= max(tglPinjam, tglPengajuan) and <= today
$minDate = $tglPinjam;
if ($tglPengajuan !== '' && $tglPengajuan > $minDate) {
    $minDate = $tglPengajuan;
}

$kondisiOptions = [
    'baik' => 'Baik — Alat lengkap, berfungsi normal tanpa kerusakan',
    'rusak_ringan' => 'Rusak Ringan — Berfungsi dengan cacat kosmetik / aus wajar',
    'rusak_berat' => 'Rusak Berat — Tidak berfungsi / komponen utama rusak',
    'hilang' => 'Hilang — Alat atau kelengkapannya tidak dikembalikan',
];

ob_start();
?>
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-2 text-sm text-slate-500">
        <a href="/petugas/pengembalian" class="hover:text-slate-800">Monitoring Pengembalian</a>
        <span>/</span>
        <span class="text-slate-900 font-medium">Verifikasi Fisik</span>
    </div>

    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <span class="badge border-amber-200 bg-amber-50 font-semibold text-amber-800">Verifikasi Fisik Gudang</span>
            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Penerimaan Pengembalian</h2>
            <p class="mt-2 text-slate-600">Periksa kondisi fisik barang secara langsung sebelum mencatat pengembalian.</p>
        </div>
        <a href="/petugas/pengembalian" class="btn btn-outline btn-sm">
            Batal
        </a>
    </section>

    <!-- Ringkasan Pinjaman (Read-Only) -->
    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body p-5 sm:p-6">
            <h3 class="card-title text-base font-bold text-slate-900">Data Peminjaman</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                <div class="rounded-lg bg-base-200/60 p-3">
                    <p class="text-xs text-slate-500">Kode Peminjaman</p>
                    <p class="font-mono text-sm font-bold text-indigo-700"><?= e($kode) ?></p>
                </div>
                <div class="rounded-lg bg-base-200/60 p-3">
                    <p class="text-xs text-slate-500">Nama Peminjam</p>
                    <p class="text-sm font-semibold text-slate-900"><?= e($peminjamNama) ?></p>
                </div>
                <div class="rounded-lg bg-base-200/60 p-3">
                    <p class="text-xs text-slate-500">Alat &amp; Jumlah</p>
                    <p class="text-sm font-semibold text-slate-900"><?= e($alatNama) ?> (<?= e((string) $jumlah) ?> unit)</p>
                </div>
                <div class="rounded-lg bg-base-200/60 p-3">
                    <p class="text-xs text-slate-500">Batas Jatuh Tempo</p>
                    <p class="text-sm font-semibold text-slate-900"><?= e($formatDate($tglTempo)) ?></p>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-500">
                <span>Tanggal Mulai: <strong class="text-slate-700"><?= e($formatDate($tglPinjam)) ?></strong></span>
                <span>Tanggal Permintaan: <strong class="text-slate-700"><?= e($formatDate($tglPengajuan)) ?></strong></span>
            </div>
        </div>
    </div>

    <!-- Form Verifikasi -->
    <form method="post" action="/petugas/pengembalian/<?= e($loanId) ?>/verify" class="card border border-base-300 bg-base-100 shadow-sm"
          onsubmit="return confirm('Verifikasi penerimaan fisik untuk <?= e(addslashes($alatNama)) ?>? Stok gudang akan otomatis dipulihkan sebesar <?= e($jumlah) ?> unit.');">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

        <div class="card-body gap-6 p-5 sm:p-7">
            <h3 class="card-title text-lg font-bold">Formulir Verifikasi Penerimaan</h3>

            <?php if (isset($errors['tanggal_diterima']) || isset($errors['kondisi'])): ?>
                <div role="alert" class="alert alert-error alert-soft">
                    <span class="app-icon" aria-hidden="true"><?= icon('triangle-exclamation-point') ?></span>
                    <div>
                        <p class="font-bold">Periksa kesalahan formulir:</p>
                        <ul class="list-disc list-inside text-xs mt-1">
                            <?php foreach ($errors as $fieldError): ?>
                                <li><?= e($fieldError) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid gap-6 sm:grid-cols-2">
                <!-- Tanggal Diterima -->
                <div class="form-control">
                    <label class="label font-semibold text-slate-800" for="tanggal_diterima">
                        Tanggal Diterima Fisik <span class="text-rose-600">*</span>
                    </label>
                    <input type="date"
                           id="tanggal_diterima"
                           name="tanggal_diterima"
                           value="<?= e($selectedTglDiterima) ?>"
                           min="<?= e($minDate) ?>"
                           max="<?= e($today) ?>"
                           required
                           class="input input-bordered w-full <?= isset($errors['tanggal_diterima']) ? 'input-error' : '' ?>"
                           aria-describedby="tgl-diterima-help<?= isset($errors['tanggal_diterima']) ? ' tgl-diterima-error' : '' ?>">
                    <p id="tgl-diterima-help" class="mt-1 text-xs text-slate-500">
                        Tanggal Petugas menerima barang secara fisik (antara <?= e($formatDate($minDate)) ?> s.d. hari ini).
                    </p>
                    <?php if (isset($errors['tanggal_diterima'])): ?>
                        <p id="tgl-diterima-error" class="mt-1 text-xs text-rose-600 font-medium">
                            <?= e($errors['tanggal_diterima']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Jumlah Diterima (Exact full loan, disabled) -->
                <div class="form-control">
                    <label class="label font-semibold text-slate-800" for="jumlah_display">
                        Jumlah Dikembalikan (Unit)
                    </label>
                    <input type="text"
                           id="jumlah_display"
                           value="<?= e((string) $jumlah) ?> unit (Pengembalian penuh)"
                           disabled
                           class="input input-bordered bg-base-200 text-slate-600 w-full cursor-not-allowed">
                    <p class="mt-1 text-xs text-slate-500">
                        Sesuai ketentuan, pengembalian diproses penuh (tidak mendukung pengembalian bertahap).
                    </p>
                </div>
            </div>

            <!-- Kondisi Fisik -->
            <div class="form-control">
                <label class="label font-semibold text-slate-800" for="kondisi">
                    Kondisi Fisik Saat Diterima <span class="text-rose-600">*</span>
                </label>
                <select id="kondisi"
                        name="kondisi"
                        required
                        class="select select-bordered w-full <?= isset($errors['kondisi']) ? 'select-error' : '' ?>"
                        aria-describedby="kondisi-help<?= isset($errors['kondisi']) ? ' kondisi-error' : '' ?>">
                    <?php foreach ($kondisiOptions as $optVal => $optLabel): ?>
                        <option value="<?= e($optVal) ?>" <?= $selectedKondisi === $optVal ? 'selected' : '' ?>>
                            <?= e($optLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="kondisi-help" class="mt-1 text-xs text-slate-500">
                    Hanya kondisi <strong>Baik</strong> yang mengembalikan stok ke gudang. Alat rusak atau hilang tidak menambah stok tersedia.
                </p>
                <?php if (isset($errors['kondisi'])): ?>
                    <p id="kondisi-error" class="mt-1 text-xs text-rose-600 font-medium">
                        <?= e($errors['kondisi']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Informasi Pemulihan Stok & Denda -->
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                <div class="flex items-start gap-3">
                    <span class="app-icon mt-0.5 text-indigo-700" aria-hidden="true"><?= icon('circle-exclamation-point') ?></span>
                    <div class="text-xs text-slate-700 leading-relaxed">
                        <p class="font-bold text-slate-900">Ketentuan Pemulihan Stok &amp; Denda:</p>
                        <ul class="list-disc list-inside mt-1 space-y-1">
                            <li>Stok gudang <strong><?= e($alatNama) ?></strong> bertambah <strong>+<?= e((string) $jumlah) ?> unit</strong> hanya bila kondisi <strong>Baik</strong>. Kondisi rusak atau hilang tidak menambah stok tersedia.</li>
                            <li>Tarif denda keterlambatan saat ini adalah
                                <strong><?= $tarifDenda > 0 ? 'Rp ' . e(number_format($tarifDenda, 0, ',', '.')) . ' per hari per unit' : 'Rp 0 (Bebas Denda)' ?></strong>.
                                Denda dihitung otomatis dari hari terlambat &times; jumlah unit &times; tarif, dan tidak dapat diubah manual.
                                Tarif dikelola Admin di halaman Pengaturan.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-300">
                <a href="/petugas/pengembalian" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <span class="app-icon" aria-hidden="true"><?= icon('check') ?></span>
                    Simpan Verifikasi
                </button>
            </div>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/app.php';
