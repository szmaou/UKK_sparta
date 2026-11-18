<?php

declare(strict_types=1);

// CLI seeder: php database/seeders/seed.php
// Creates the three demo accounts and, if absent, one sample category + tool.
// Existing rows are never overwritten, so real accounts survive a re-run.

// Seeding is an operator action; refuse HTTP even if a misconfigured docroot
// exposes this directory.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$db = $container['db'];

$accounts = [
    ['Administrator', 'admin', 'admin123', 'admin'],
    ['Petugas Demo', 'petugas', 'petugas123', 'petugas'],
    ['Peminjam Demo', 'peminjam', 'peminjam123', 'peminjam'],
];

$find = $db->prepare('SELECT id FROM users WHERE username = ?');
$insert = $db->prepare(
    'INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
);

$db->beginTransaction();

foreach ($accounts as [$name, $username, $password, $role]) {
    $find->execute([$username]);
    if ($find->fetchColumn() !== false) {
        fwrite(STDOUT, "skip users.{$username} (already exists)\n");
        continue;
    }
    $insert->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
    fwrite(STDOUT, "create users.{$username} ({$role})\n");
}

$findKategori = $db->prepare('SELECT id FROM kategori WHERE nama = ?');
$findKategori->execute(['Alat Tangan']);
$kategoriId = $findKategori->fetchColumn();
if ($kategoriId === false) {
    $db->prepare('INSERT INTO kategori (nama, deskripsi) VALUES (?, ?)')
        ->execute(['Alat Tangan', 'Contoh kategori alat tangan']);
    $kategoriId = (int) $db->lastInsertId();
    fwrite(STDOUT, "create kategori Alat Tangan\n");
} else {
    fwrite(STDOUT, "skip kategori Alat Tangan (already exists)\n");
}

$findAlat = $db->prepare('SELECT id FROM alat WHERE kode = ?');
$findAlat->execute(['HT-001']);
if ($findAlat->fetchColumn() === false) {
    $db->prepare(
        'INSERT INTO alat (kode, kategori_id, nama, deskripsi, stok_total, stok_tersedia)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute(['HT-001', $kategoriId, 'Tang Kombinasi 8 inci', 'Contoh alat', 10, 10]);
    fwrite(STDOUT, "create alat HT-001\n");
} else {
    fwrite(STDOUT, "skip alat HT-001 (already exists)\n");
}

$db->commit();
fwrite(STDOUT, "seed done\n");
