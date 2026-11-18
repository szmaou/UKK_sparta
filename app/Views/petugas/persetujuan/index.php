<?php
/** @var array $items */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $csrfToken */
/** @var string|null $success */
/** @var string|null $error */

$items = is_array($items ?? null) ? $items : [];
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$pageTitle = 'Persetujuan Peminjaman';
$activeNav = 'persetujuan';

$pageUrl = static function (int $target): string {
    return '/petugas/persetujuan' . ($target > 1 ? '?page=' . $target : '');
};

$formatDate = static function (?string $date): string {
    if (!$date) return '—';
    $time = strtotime($date);
    return $time ? date('d M Y', $time) : $date;
};

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <span class="badge border-amber-200 bg-amber-50 font-semibold text-amber-800">Menunggu Verifikasi</span>
            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Persetujuan Peminjaman</h2>
            <p class="mt-2 text-slate-600">Verifikasi permohonan peminjaman alat dari siswa dan staf pengajar.</p>
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

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-5 p-4 sm:p-6">
            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-14 text-center">
                    <span class="app-icon mx-auto grid size-14 place-items-center rounded-full bg-emerald-50 text-emerald-700" aria-hidden="true">
                        <?= icon('check') ?>
                    </span>
                    <h3 class="mt-4 text-xl font-bold">Tidak ada pengajuan pending</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">
                        Semua permohonan peminjaman saat ini telah selesai diproses.
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar permohonan menunggu persetujuan</caption>
                        <thead>
                            <tr>
                                <th>Kode & Peminjam</th>
                                <th>Alat & Ketersediaan</th>
                                <th class="text-right">Jumlah</th>
                                <th>Jadwal Peminjaman</th>
                                <th>Tujuan & Catatan</th>
                                <th class="text-right">Aksi Verifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $loanId = (string) ($item['id'] ?? '');
                            $stokTersedia = (int) ($item['stok_tersedia'] ?? 0);
                            $jumlah = (int) ($item['jumlah'] ?? 1);
                            $canApprove = $stokTersedia >= $jumlah;
                            $catatan = trim((string) ($item['catatan'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <p class="font-mono text-xs font-bold text-indigo-700">
                                        <?= e((string) ($item['kode'] ?? '—')) ?>
                                    </p>
                                    <p class="text-sm font-semibold text-slate-900">
                                        <?= e((string) ($item['peminjam_nama'] ?? '—')) ?>
                                    </p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900"><?= e((string) ($item['alat_nama'] ?? '—')) ?></p>
                                    <p class="text-xs <?= $canApprove ? 'text-slate-500' : 'font-semibold text-rose-600' ?>">
                                        Stok gudang: <?= e((string) $stokTersedia) ?> unit
                                        <?php if (!$canApprove): ?>
                                            (Kurang!)
                                        <?php endif; ?>
                                    </p>
                                </td>
                                <td class="text-right tabular-nums font-semibold">
                                    <?= e((string) $jumlah) ?> <span class="text-xs font-normal text-slate-500">unit</span>
                                </td>
                                <td>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Mulai:</span> <?= e($formatDate((string) ($item['tanggal_pinjam'] ?? ''))) ?>
                                    </p>
                                    <p class="text-xs text-slate-700">
                                        <span class="text-slate-400">Tempo:</span> <?= e($formatDate((string) ($item['tanggal_jatuh_tempo'] ?? ''))) ?>
                                    </p>
                                </td>
                                <td class="max-w-xs text-xs text-slate-600">
                                    <?= $catatan !== '' ? e($catatan) : '<span class="italic text-slate-400">Tidak ada catatan</span>' ?>
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <!-- Approve form -->
                                        <form
                                            method="post"
                                            action="/petugas/peminjaman/<?= rawurlencode($loanId) ?>/approve"
                                            onsubmit="return confirm('Setujui peminjaman ini? Stok akan dicadangkan sejumlah <?= e((string) $jumlah) ?> unit.')"
                                        >
                                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                            <button
                                                type="submit"
                                                class="btn btn-success btn-xs sm:btn-sm"
                                                <?= !$canApprove ? 'disabled aria-disabled="true"' : '' ?>
                                                title="<?= !$canApprove ? 'Stok tidak mencukupi untuk disetujui' : 'Setujui peminjaman' ?>"
                                            >
                                                <span class="app-icon" aria-hidden="true"><?= icon('check') ?></span>
                                                Setujui
                                            </button>
                                        </form>

                                        <!-- Reject Trigger (Opens accessible native dialog modal) -->
                                        <button
                                            type="button"
                                            class="btn btn-error btn-outline btn-xs sm:btn-sm"
                                            onclick="document.getElementById('reject-modal-<?= e($loanId) ?>').showModal()"
                                            aria-haspopup="dialog"
                                            aria-controls="reject-modal-<?= e($loanId) ?>"
                                        >
                                            <span class="app-icon" aria-hidden="true"><?= icon('x') ?></span>
                                            Tolak
                                        </button>
                                    </div>

                                    <!-- Reject Dialog Modal -->
                                    <dialog id="reject-modal-<?= e($loanId) ?>" class="modal text-left">
                                        <div class="modal-box max-w-md">
                                            <h3 class="text-lg font-bold text-slate-900">Tolak Permohonan Peminjaman</h3>
                                            <p class="mt-2 text-sm text-slate-600">
                                                Apakah Anda yakin ingin menolak permohonan
                                                <span class="font-semibold text-slate-800"><?= e((string) ($item['kode'] ?? '')) ?></span>
                                                oleh <span class="font-semibold text-slate-800"><?= e((string) ($item['peminjam_nama'] ?? '')) ?></span>?
                                            </p>

                                            <form method="post" action="/petugas/peminjaman/<?= rawurlencode($loanId) ?>/reject" class="mt-4 space-y-4">
                                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

                                                <div>
                                                    <label for="catatan-<?= e($loanId) ?>" class="fieldset-label font-semibold">
                                                        Alasan Penolakan <span class="font-normal text-slate-500">(opsional)</span>
                                                    </label>
                                                    <textarea
                                                        id="catatan-<?= e($loanId) ?>"
                                                        name="catatan"
                                                        rows="3"
                                                        maxlength="1000"
                                                        class="textarea mt-2 w-full"
                                                        placeholder="Contoh: Stok sedang dialokasikan untuk ujian praktik, alat rusak, dsb."
                                                    ></textarea>
                                                    <p class="mt-1 text-xs text-slate-500">Maksimal 1.000 karakter.</p>
                                                </div>

                                                <div class="modal-action flex justify-end gap-2 pt-2">
                                                    <button
                                                        type="button"
                                                        class="btn btn-ghost"
                                                        onclick="document.getElementById('reject-modal-<?= e($loanId) ?>').close()"
                                                    >
                                                        Batal
                                                    </button>
                                                    <button type="submit" class="btn btn-error">
                                                        Tolak Peminjaman
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                        <form method="dialog" class="modal-backdrop">
                                            <button>tutup</button>
                                        </form>
                                    </dialog>
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
                        <strong><?= e((string) $total) ?></strong> pengajuan menunggu · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?>
                    </p>
                    <?php if ($totalPages > 1): ?>
                        <nav class="join" aria-label="Paginasi persetujuan">
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
