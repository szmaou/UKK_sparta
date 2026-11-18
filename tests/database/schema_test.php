<?php

declare(strict_types=1);

// Executable database checks: php tests/database/schema_test.php
// Exit 0 = pass, 1 = fail, 2 = skipped (no MariaDB reachable).
// Safe to re-run: schema is IF NOT EXISTS, fixtures are rolled back,
// and the seeder skips rows that already exist.

$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];

function check(string $label, bool $ok): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $label;
        fwrite(STDOUT, "FAIL {$label}\n");
    }
}

function throws(PDO $db, callable $fn): bool
{
    try {
        $fn();
    } catch (PDOException) {
        return true;
    }

    return false;
}

function applySql(PDO $db, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("cannot read {$file}");
    }

    // Minimal DELIMITER support for routines.sql; schema.sql has no delimiter changes.
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m) === 1) {
            $delimiter = $m[1];
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), $delimiter)) {
            // PDO::exec wants one statement without the client delimiter.
            $statement = rtrim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            if ($statement !== '') {
                $db->exec($statement);
            }
            $buffer = '';
        }
    }
}

try {
    $container = require $root . '/bootstrap/app.php';
    $db = $container['db'];
    check('bootstrap/app.php exposes PDO under db', $db instanceof PDO);
} catch (PDOException $e) {
    fwrite(STDERR, "SKIP: MariaDB not reachable: {$e->getMessage()}\n");
    exit(2);
}

applySql($db, $root . '/database/schema.sql');
applySql($db, $root . '/database/routines.sql');

// --- Structure ---------------------------------------------------------------
$expectedTables = ['users', 'kategori', 'alat', 'peminjaman', 'pengembalian', 'activity_logs'];
$tables = $db->query(
    "SELECT table_name, engine FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name IN ('users','kategori','alat','peminjaman','pengembalian','activity_logs')"
)->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($expectedTables as $table) {
    check("table {$table} exists with InnoDB", ($tables[$table] ?? null) === 'InnoDB');
}

$roles = $db->query(
    "SELECT column_type FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'"
)->fetchColumn();
check('users.role enum has admin/petugas/peminjam', $roles !== false
    && str_contains($roles, "'admin'") && str_contains($roles, "'petugas'") && str_contains($roles, "'peminjam'"));

$nullable = $db->query(
    "SELECT is_nullable FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'activity_logs' AND column_name = 'user_id'"
)->fetchColumn();
check('activity_logs.user_id nullable', $nullable === 'YES');

$fkCount = (int) $db->query(
    "SELECT COUNT(*) FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE()"
)->fetchColumn();
check('7 foreign keys present', $fkCount === 7);

// --- Constraint behaviour ----------------------------------------------------
$db->beginTransaction();

$db->prepare("INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)")
    ->execute(['Test Admin', 'test_admin_check', password_hash('x', PASSWORD_DEFAULT), 'admin']);
$userId = (int) $db->lastInsertId();

check('users.username unique', throws($db, fn () => $db
    ->prepare("INSERT INTO users (name, username, password_hash) VALUES (?, ?, ?)")
    ->execute(['Dup', 'test_admin_check', 'x'])));

check('users.role rejects unknown value', throws($db, fn () => $db
    ->prepare("INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)")
    ->execute(['Bad', 'test_bad_role', 'x', 'owner'])));

$db->prepare("INSERT INTO kategori (nama) VALUES (?)")->execute(['Test Kategori']);
$kategoriId = (int) $db->lastInsertId();

check('kategori.nama unique', throws($db, fn () => $db
    ->prepare("INSERT INTO kategori (nama) VALUES (?)")->execute(['Test Kategori'])));

$db->prepare(
    "INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia) VALUES (?, ?, ?, ?, ?)"
)->execute(['TEST-001', $kategoriId, 'Alat Uji', 5, 5]);
$alatId = (int) $db->lastInsertId();

check('alat.stok_tersedia <= stok_total', throws($db, fn () => $db
    ->prepare("INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia) VALUES (?, ?, ?, ?, ?)")
    ->execute(['TEST-002', $kategoriId, 'Alat Uji 2', 1, 2])));

check('alat.kategori_id FK restricted', throws($db, fn () => $db
    ->prepare("INSERT INTO alat (kode, kategori_id, nama) VALUES (?, ?, ?)")
    ->execute(['TEST-003', 999999, 'Alat Uji 3'])));

check('peminjaman.jumlah > 0', throws($db, fn () => $db
    ->prepare(
        "INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo)
         VALUES (?, ?, ?, ?, ?, ?)"
    )
    ->execute(['TEST-PJ-0', $userId, $alatId, 0, '2026-01-01', '2026-01-02'])));

check('peminjaman.jatuh_tempo >= tanggal_pinjam', throws($db, fn () => $db
    ->prepare(
        "INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo)
         VALUES (?, ?, ?, ?, ?, ?)"
    )
    ->execute(['TEST-PJ-1', $userId, $alatId, 1, '2026-01-05', '2026-01-04'])));

$db->prepare(
    "INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo)
     VALUES (?, ?, ?, ?, ?, ?)"
)->execute(['TEST-PJ-2', $userId, $alatId, 2, '2026-01-01', '2026-01-05']);
$peminjamanId = (int) $db->lastInsertId();

check('peminjaman.kode unique', throws($db, fn () => $db
    ->prepare(
        "INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo)
         VALUES (?, ?, ?, ?, ?, ?)"
    )
    ->execute(['TEST-PJ-2', $userId, $alatId, 1, '2026-01-01', '2026-01-02'])));

$db->prepare("INSERT INTO pengembalian (peminjaman_id, jumlah) VALUES (?, ?)")
    ->execute([$peminjamanId, 2]);
check('pengembalian.peminjaman_id unique', throws($db, fn () => $db
    ->prepare("INSERT INTO pengembalian (peminjaman_id, jumlah) VALUES (?, ?)")
    ->execute([$peminjamanId, 1])));

// --- Routines ----------------------------------------------------------------
$denda = $db->query("SELECT fn_hitung_denda('2026-01-05', '2026-01-08', 2, 1000)")->fetchColumn();
check('fn_hitung_denda returns 3 days x 2 x 1000', (string) $denda === '6000.00');

$dendaZero = $db->query("SELECT fn_hitung_denda('2026-01-05', '2026-01-05', 2, 1000)")->fetchColumn();
check('fn_hitung_denda is 0 when not late', (string) $dendaZero === '0.00');

$db->exec("SET @sparta_actor_id = {$userId}");
$db->prepare("UPDATE peminjaman SET status = 'approved' WHERE id = ?")->execute([$peminjamanId]);
$logCount = (int) $db->query(
    "SELECT COUNT(*) FROM activity_logs WHERE entity_type = 'peminjaman' AND entity_id = {$peminjamanId} AND action = 'status_change'"
)->fetchColumn();
check('trigger writes activity_logs on status change', $logCount === 1);

$report = $db->query("CALL sp_rekap_peminjaman('2026-01-01', '2026-01-31')");
$rows = $report->fetchAll();
$report->closeCursor();
check('sp_rekap_peminjaman returns the joined report row', count($rows) === 1
    && ($rows[0]['peminjam'] ?? '') === 'Test Admin'
    && ($rows[0]['kategori'] ?? '') === 'Test Kategori');

$db->rollBack();

// --- Seeder, run twice -------------------------------------------------------
ob_start();
require $root . '/database/seeders/seed.php';
ob_end_clean();
ob_start();
require $root . '/database/seeders/seed.php';
ob_end_clean();

$accounts = $db->query(
    "SELECT username, role, password_hash FROM users WHERE username IN ('admin','petugas','peminjam')"
)->fetchAll(PDO::FETCH_UNIQUE);
check('three demo accounts exist', count($accounts) === 3);
check('demo roles correct', ($accounts['admin']['role'] ?? '') === 'admin'
    && ($accounts['petugas']['role'] ?? '') === 'petugas'
    && ($accounts['peminjam']['role'] ?? '') === 'peminjam');
check('demo passwords verify', password_verify('admin123', $accounts['admin']['password_hash'] ?? '')
    && password_verify('petugas123', $accounts['petugas']['password_hash'] ?? '')
    && password_verify('peminjam123', $accounts['peminjam']['password_hash'] ?? ''));
check('seeder is idempotent', (int) $db->query(
    "SELECT COUNT(*) FROM users WHERE username IN ('admin','petugas','peminjam')"
)->fetchColumn() === 3);

fwrite(STDOUT, sprintf("%d checks, %d failed\n", $checks, count($failures)));
exit($failures === [] ? 0 : 1);
