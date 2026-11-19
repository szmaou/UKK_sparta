<?php
/** @var array|null $user */
/** @var string $role */
$role = strtolower((string) ($role ?? ''));
if (!in_array($role, ['admin', 'petugas', 'peminjam'], true)) {
    $role = 'peminjam';
}
$homeUrl = $user === null ? '/login' : '/' . $role . '/dashboard';
?>
<!doctype html>
<html lang="id" data-theme="sparta">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Halaman tidak ditemukan — SPARTA">
    <title>Halaman tidak ditemukan · SPARTA</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/SMK%20Negeri%201%20Leuwimunding.svg">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-base-200 text-base-content antialiased">
<main class="grid min-h-screen place-items-center">
    <section class="card w-full max-w-xl border border-base-300 bg-base-100 text-center shadow-sm" aria-labelledby="error-title">
        <div class="card-body items-center p-8 sm:p-12">
            <div class="grid size-16 place-items-center rounded-full bg-slate-100 text-slate-700 app-icon" aria-hidden="true"><?= icon('search') ?></div>
            <p class="mt-4 text-sm font-bold uppercase tracking-widest text-slate-600">Error 404</p>
            <h1 id="error-title" class="text-3xl font-black tracking-tight">Halaman tidak ditemukan</h1>
            <p class="mt-2 max-w-md leading-7 text-slate-600">Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.</p>
            <div class="card-actions mt-6">
                <a href="<?= e($homeUrl) ?>" class="btn btn-primary"><?= $user === null ? 'Masuk ke SPARTA' : 'Kembali ke dashboard' ?></a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
