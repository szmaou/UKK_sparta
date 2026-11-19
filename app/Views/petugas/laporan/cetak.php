<?php
/** @var array $values */
/** @var array $errors */
/** @var array $items */
/** @var array $summary */
/** @var bool $truncated */
/** @var string $printedAt */

$values = is_array($values ?? null) ? $values : [];
$items = is_array($items ?? null) ? $items : [];
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'unit' => 0, 'denda' => 0.0, 'per_status' => []];
$truncated = (bool) ($truncated ?? false);
$printedAt = (string) ($printedAt ?? date('d-m-Y H:i'));
$statusLabels = [
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
    'return_requested' => 'Menunggu Verifikasi',
    'returned' => 'Dikembalikan',
];
$selectedStatus = (string) ($values['status'] ?? '');
$statusText = $selectedStatus === '' ? 'Semua status' : ($statusLabels[$selectedStatus] ?? $selectedStatus);
$formatRupiah = static fn (float|int|string $value): string => 'Rp ' . number_format((float) $value, 0, ',', '.');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Laporan Peminjaman <?= e((string) ($values['from'] ?? '')) ?> — <?= e((string) ($values['to'] ?? '')) ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 12px; color: #0f172a; background: #fff; }
        header { border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 20px; }
        .meta { margin-top: 6px; color: #475569; }
        .summary { display: flex; gap: 24px; margin: 16px 0; flex-wrap: wrap; }
        .summary div { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; }
        .summary strong { display: block; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        thead th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .note { margin-top: 10px; color: #92400e; }
        footer { margin-top: 20px; color: #64748b; font-size: 11px; display: flex; justify-content: space-between; gap: 16px; }
        .toolbar { margin-bottom: 16px; display: flex; gap: 8px; }
        .toolbar button, .toolbar a { font: inherit; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; color: inherit; text-decoration: none; cursor: pointer; }
        .toolbar button { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <a href="/petugas/laporan?<?= e(http_build_query(array_filter([
            'from' => (string) ($values['from'] ?? ''),
            'to' => (string) ($values['to'] ?? ''),
            'status' => $selectedStatus,
        ], static fn ($v) => $v !== ''))) ?>">Kembali</a>
    </div>

    <header>
        <h1>SPARTA — Laporan Peminjaman Barang &amp; Alat</h1>
        <p class="meta">
            Periode <strong><?= e((string) ($values['from'] ?? '')) ?></strong> sampai
            <strong><?= e((string) ($values['to'] ?? '')) ?></strong> ·
            Status: <strong><?= e($statusText) ?></strong>
        </p>
    </header>

    <div class="summary">
        <div>Jumlah transaksi<strong><?= e((string) $summary['total']) ?></strong></div>
        <div>Total unit<strong><?= e((string) $summary['unit']) ?></strong></div>
        <div>Total denda<strong><?= e($formatRupiah($summary['denda'])) ?></strong></div>
    </div>

    <?php if ($truncated): ?>
        <p class="note">Hasil dibatasi 1.000 baris teratas. Persempit rentang tanggal untuk laporan lengkap.</p>
    <?php endif; ?>

    <table>
        <caption style="caption-side:bottom;text-align:left;padding-top:8px;color:#64748b">
            Total <?= e((string) $summary['total']) ?> transaksi pada periode ini.
        </caption>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Peminjam</th>
                <th>Alat</th>
                <th>Kategori</th>
                <th class="num">Jumlah</th>
                <th>Pinjam</th>
                <th>Jatuh tempo</th>
                <th>Status</th>
                <th class="num">Denda</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $index => $item): ?>
            <tr>
                <td class="num"><?= e((string) ($index + 1)) ?></td>
                <td><?= e((string) ($item['kode'] ?? '')) ?></td>
                <td><?= e((string) ($item['peminjam'] ?? '')) ?></td>
                <td><?= e((string) ($item['alat'] ?? '')) ?></td>
                <td><?= e((string) ($item['kategori'] ?? '')) ?></td>
                <td class="num"><?= e((string) ($item['jumlah'] ?? '')) ?></td>
                <td><?= e((string) ($item['tanggal_pinjam'] ?? '')) ?></td>
                <td><?= e((string) ($item['tanggal_jatuh_tempo'] ?? '')) ?></td>
                <td><?= e($statusLabels[(string) ($item['status'] ?? '')] ?? (string) ($item['status'] ?? '')) ?></td>
                <td class="num"><?= e($formatRupiah((float) ($item['denda'] ?? 0))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
            <tr><td colspan="10">Tidak ada transaksi pada periode dan status ini.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <footer>
        <span>Dicetak: <?= e($printedAt) ?></span>
        <span>SPARTA · Sistem Peminjaman Barang &amp; Alat</span>
    </footer>
</body>
</html>
