<?php
/** @var array $items */
/** @var list<string> $actions */
/** @var string $q */
/** @var string $action */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */

$items = is_array($items ?? null) ? $items : [];
$actions = is_array($actions ?? null) ? $actions : [];
$q = (string) ($q ?? '');
$action = (string) ($action ?? '');
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$total = max(0, (int) ($total ?? count($items)));
$pageTitle = 'Log Aktivitas';
$activeNav = 'log';

$pageUrl = static function (int $target) use ($q, $action): string {
    $params = array_filter(['q' => $q, 'action' => $action, 'page' => $target > 1 ? (string) $target : ''], static fn ($v) => $v !== '');

    return '/admin/activity-logs' . ($params === [] ? '' : '?' . http_build_query($params));
};

$formatTime = static function (?string $time): string {
    if (!$time) return '—';
    $ts = strtotime($time);

    return $ts ? date('d M Y H:i', $ts) : $time;
};

$actionLabels = [
    'login' => 'Login',
    'logout' => 'Logout',
    'create' => 'Tambah',
    'update' => 'Ubah',
    'delete' => 'Hapus',
    'deactivate' => 'Nonaktifkan',
    'status_change' => 'Perubahan Status',
    'reject_note' => 'Catatan Penolakan',
];

ob_start();
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-indigo-700">Audit</p>
            <h2 class="mt-1 text-3xl font-black tracking-tight">Log aktivitas</h2>
            <p class="mt-2 text-slate-600">Jejak perubahan data dan sesi pengguna. Log bersifat hanya-baca.</p>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-5 p-4 sm:p-6">
            <form method="get" action="/admin/activity-logs" class="flex flex-col gap-3 sm:flex-row" role="search">
                <label class="input w-full sm:max-w-md">
                    <span class="app-icon text-slate-400" aria-hidden="true"><?= icon('search') ?></span>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari aksi, entitas, atau pelaku" aria-label="Cari log">
                </label>
                <select name="action" class="select w-full sm:max-w-56" aria-label="Filter aksi">
                    <option value="">Semua aksi</option>
                    <?php foreach ($actions as $name): ?>
                        <option value="<?= e($name) ?>"<?= $name === $action ? ' selected' : '' ?>><?= e($actionLabels[$name] ?? $name) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-neutral">Terapkan</button>
                <?php if ($q !== '' || $action !== ''): ?><a href="/admin/activity-logs" class="btn btn-ghost">Reset</a><?php endif; ?>
            </form>

            <?php if ($items === []): ?>
                <div class="rounded-xl border border-dashed border-base-300 bg-base-200/60 px-6 py-12 text-center">
                    <span class="app-icon mx-auto grid size-12 place-items-center rounded-full bg-indigo-50 text-indigo-700" aria-hidden="true"><?= icon('logs') ?></span>
                    <h3 class="mt-4 text-lg font-bold">Belum ada aktivitas</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600"><?= $q !== '' || $action !== '' ? 'Tidak ada log yang cocok dengan filter tersebut.' : 'Log akan terisi saat pengguna masuk atau data berubah.' ?></p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-base-300">
                    <table class="table">
                        <caption class="sr-only">Daftar log aktivitas</caption>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pelaku</th>
                                <th>Aksi</th>
                                <th>Entitas</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $actorName = (string) ($item['actor_name'] ?? '');
                            $actorUsername = (string) ($item['actor_username'] ?? '');
                            $entityType = (string) ($item['entity_type'] ?? '');
                            $entityId = (string) ($item['entity_id'] ?? '');
                            ?>
                            <tr>
                                <td class="whitespace-nowrap text-xs tabular-nums text-slate-600"><?= e($formatTime((string) ($item['created_at'] ?? ''))) ?></td>
                                <td>
                                    <?php if ($actorName !== ''): ?>
                                        <p class="font-semibold"><?= e($actorName) ?></p>
                                        <p class="font-mono text-xs text-slate-500">@<?= e($actorUsername) ?></p>
                                    <?php else: ?>
                                        <span class="text-xs italic text-slate-400">Sistem</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-sm badge-ghost font-medium"><?= e($actionLabels[(string) ($item['action'] ?? '')] ?? (string) ($item['action'] ?? '')) ?></span>
                                </td>
                                <td class="text-xs text-slate-600">
                                    <?= $entityType === '' ? '—' : e($entityType) ?>
                                    <?= $entityId !== '' ? '#' . e($entityId) : '' ?>
                                </td>
                                <td class="max-w-md text-sm text-slate-700"><?= e((string) ($item['description'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center">
                    <p class="text-sm text-slate-600"><strong><?= e((string) $total) ?></strong> log · Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?></p>
                    <?php if ($totalPages > 1): ?>
                        <nav class="join" aria-label="Paginasi log aktivitas">
                            <?php if ($page > 1): ?><a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page - 1)) ?>">Sebelumnya</a><?php else: ?><span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Sebelumnya</span><?php endif; ?>
                            <?php if ($page < $totalPages): ?><a class="btn join-item btn-outline btn-sm" href="<?= e($pageUrl($page + 1)) ?>">Berikutnya</a><?php else: ?><span class="btn join-item btn-outline btn-sm btn-disabled" aria-disabled="true">Berikutnya</span><?php endif; ?>
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
