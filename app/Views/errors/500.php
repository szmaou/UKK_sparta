<?php
/** @var array|null $user */
/** @var string $csrfToken */
/** @var string $role */
$pageTitle = 'Gangguan sistem';
$errorRole = strtolower((string) ($role ?? $user['role'] ?? 'peminjam'));
if (!in_array($errorRole, ['admin', 'petugas', 'peminjam'], true)) {
    $errorRole = 'peminjam';
}
$dashboardUrl = '/' . $errorRole . '/dashboard';
ob_start();
?>
<div class="grid min-h-[calc(100vh-12rem)] place-items-center">
    <section class="card w-full max-w-xl border border-base-300 bg-base-100 text-center shadow-sm" aria-labelledby="error-title">
        <div class="card-body items-center p-8 sm:p-12">
            <div class="grid size-16 place-items-center rounded-full bg-amber-50 text-amber-800 app-icon" aria-hidden="true"><?= icon('wrench') ?></div>
            <p class="mt-4 text-sm font-bold uppercase tracking-widest text-amber-800">Error 500</p>
            <h2 id="error-title" class="text-3xl font-black tracking-tight">Data belum dapat diproses</h2>
            <p class="mt-2 max-w-md leading-7 text-slate-600">Terjadi gangguan pada sistem. Silakan kembali ke dashboard dan coba lagi.</p>
            <div class="card-actions mt-6">
                <a href="<?= e($dashboardUrl) ?>" class="btn btn-primary">Kembali ke dashboard</a>
            </div>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
