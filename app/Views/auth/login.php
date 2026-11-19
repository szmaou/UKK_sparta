<?php
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string $oldUsername */
?>
<!doctype html>
<html lang="id" data-theme="sparta">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Masuk ke SPARTA — Sistem Peminjaman Barang dan Alat">
    <title>Masuk · SPARTA</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/SMK%20Negeri%201%20Leuwimunding.svg">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-base-200 text-base-content antialiased">
<main class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(28rem,0.78fr)]">
    <section class="brand-grid relative hidden overflow-hidden p-12 text-white lg:flex lg:flex-col lg:justify-between" aria-label="Tentang SPARTA">
        <div class="relative z-10 flex items-center gap-4">
            <div class="grid size-16 place-items-center rounded-xl bg-white p-2 shadow-lg">
                <img class="logo-school h-12 w-auto" src="/assets/img/SMK%20Negeri%201%20Leuwimunding.svg" alt="Logo SMK Negeri 1 Leuwimunding">
            </div>
            <div>
                <p class="text-2xl font-black tracking-tight">SPARTA</p>
                <p class="text-sm text-indigo-200">SMK Negeri 1 Leuwimunding</p>
            </div>
        </div>
        <div class="relative z-10 max-w-xl pb-10">
            <p class="mb-5 text-sm font-bold uppercase tracking-[0.24em] text-indigo-200">Sistem peminjaman sekolah</p>
            <h1 class="text-5xl font-black leading-[1.06] tracking-tight">Kelola alat.<br>Pinjam lebih mudah.</h1>
            <p class="mt-6 max-w-lg text-lg leading-8 text-indigo-100">Satu ruang kerja untuk inventaris, peminjaman, dan pengembalian alat sekolah.</p>
        </div>
        <p class="relative z-10 text-sm text-indigo-200">Sistem Peminjaman Barang &amp; Alat</p>
        <div class="absolute -bottom-40 -right-32 size-[34rem] rounded-full border-[5rem] border-white/5" aria-hidden="true"></div>
    </section>

    <section class="flex items-center justify-center p-5 sm:p-10">
        <div class="w-full max-w-md">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <img class="logo-school h-12 w-auto" src="/assets/img/SMK%20Negeri%201%20Leuwimunding.svg" alt="Logo SMK Negeri 1 Leuwimunding">
                <div>
                    <p class="text-xl font-black tracking-tight text-indigo-700">SPARTA</p>
                    <p class="text-xs text-slate-500">SMK Negeri 1 Leuwimunding</p>
                </div>
            </div>

            <div class="mb-8">
                <p class="mb-2 text-sm font-bold text-indigo-700">Selamat datang</p>
                <h2 class="text-3xl font-black tracking-tight sm:text-4xl">Masuk ke SPARTA</h2>
                <p class="mt-3 leading-6 text-slate-600">Gunakan akun yang diberikan sekolah untuk melanjutkan.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error mb-6 items-start bg-red-50 text-red-900" role="alert" id="login-error">
                    <span class="app-icon mt-0.5 shrink-0" aria-hidden="true"><?= icon('circle-exclamation-point') ?></span>
                    <div>
                        <p class="font-semibold">Tidak dapat masuk</p>
                        <p class="text-sm"><?= e($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="/login" class="space-y-5">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <fieldset class="fieldset">
                    <label class="fieldset-legend font-semibold" for="username">Username</label>
                    <input class="input input-bordered h-12 w-full bg-base-100" id="username" name="username" type="text" value="<?= e($oldUsername) ?>" autocomplete="username" required autofocus<?php if (!empty($error)): ?> aria-invalid="true" aria-describedby="login-error"<?php endif; ?>>
                </fieldset>
                <fieldset class="fieldset">
                    <label class="fieldset-legend font-semibold" for="password">Kata sandi</label>
                    <input class="input input-bordered h-12 w-full bg-base-100" id="password" name="password" type="password" autocomplete="current-password" required<?php if (!empty($error)): ?> aria-invalid="true" aria-describedby="login-error"<?php endif; ?>>
                </fieldset>
                <button type="submit" class="btn btn-primary h-12 w-full text-base">Masuk</button>
            </form>

            <div class="mt-8 border-t border-base-300 pt-6 text-sm leading-6 text-slate-500">
                <p>Belum memiliki akun atau lupa kata sandi?</p>
                <p>Hubungi Admin SPARTA di sekolah.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
