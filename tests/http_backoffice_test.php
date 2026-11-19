<?php
declare(strict_types=1);
// Focused HTTP integration checks for the Admin back office and Petugas report
// (FR-06, FR-07, FR-08, FR-11).
//   DB_PORT=33306 DB_USER=sparta DB_PASSWORD=sparta php tests/http_backoffice_test.php
// Exit 0 = pass, 1 = fail, 2 = skipped (DB unreachable or not disposable).
//
// SAFETY: fixtures are prefixed TST-/itx_ and only those rows are removed.
// The test refuses a non-disposable DB unless SPARTA_ALLOW_NONDISPOSABLE_DB=1.
$root = dirname(__DIR__);
try {
    $container = require $root . '/bootstrap/app.php';
    $container['db']->query('SELECT 1');
} catch (Throwable $exception) {
    fwrite(STDERR, 'SKIP: MariaDB not reachable: ' . $exception->getMessage() . "\n");
    exit(2);
}
$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'sparta';
if (($host !== '127.0.0.1' || $dbPort !== '33306') && getenv('SPARTA_ALLOW_NONDISPOSABLE_DB') !== '1') {
    fwrite(STDERR, "SKIP: refusing to run destructive fixtures against {$host}:{$dbPort}/{$dbName}.\n");
    exit(2);
}
require_once $root . '/app/autoload.php';
$db = $container['db'];
$db->exec((string) file_get_contents($root . '/database/schema.sql'));

/** Minimal DELIMITER support so fn_hitung_denda and the audit trigger exist. */
function applyRoutines(PDO $db, string $file): void
{
    $sql = (string) file_get_contents($file);
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m) === 1) {
            $delimiter = $m[1];
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), $delimiter)) {
            $statement = rtrim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            if ($statement !== '') {
                $db->exec($statement);
            }
            $buffer = '';
        }
    }
}
applyRoutines($db, $root . '/database/routines.sql');

$checks = 0;
$failures = [];
function check(string $label, bool $ok): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $label;
    }
}
/** @return array{status: int, headers: string, body: string} */
function request(string $base, string $method, string $path, array $post = [], ?string $jar = null): array
{
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($post !== []) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    if ($jar !== null) {
        curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    }
    $raw = (string) curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize)];
}
function csrfFrom(string $html): string
{
    return preg_match('/name="_token" value="([0-9a-f]{64})"/', $html, $m) === 1 ? $m[1] : '';
}
function login(string $base, string $jar, string $username, string $password): array
{
    $token = csrfFrom(request($base, 'GET', '/login', [], $jar)['body']);
    return request($base, 'POST', '/login', ['_token' => $token, 'username' => $username, 'password' => $password], $jar);
}
function dbValue(PDO $db, string $sql, array $params = []): mixed
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}
/** @return list<array<string, mixed>> */
function auditRows(PDO $db, string $entity, int $id): array
{
    $stmt = $db->prepare('SELECT action, description FROM activity_logs WHERE entity_type = ? AND entity_id = ? ORDER BY id');
    $stmt->execute([$entity, $id]);
    return $stmt->fetchAll();
}

$password = 'test-secret-123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$usernames = ['itx_admin', 'itx_petugas', 'itx_peminjam'];

// Clean any previous run (activity_logs first, then entity rows).
$in = implode(',', array_fill(0, count($usernames), '?'));
$db->prepare("DELETE al FROM activity_logs al JOIN users u ON u.id = al.user_id WHERE u.username IN ($in)")->execute($usernames);
$db->prepare("DELETE FROM users WHERE username IN ($in)")->execute($usernames);
$db->exec("DELETE pg FROM pengembalian pg JOIN peminjaman p ON p.id = pg.peminjaman_id WHERE p.kode LIKE 'TST-%'");
$db->exec("DELETE FROM peminjaman WHERE kode LIKE 'TST-%'");
$db->exec("DELETE FROM alat WHERE kode LIKE 'TST-%'");
$db->exec("DELETE FROM kategori WHERE nama LIKE 'TST-%'");
$db->exec("DELETE FROM activity_logs WHERE description LIKE '%TST-%'");

$insertUser = $db->prepare('INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
foreach ([['itx_admin', 'admin'], ['itx_petugas', 'petugas'], ['itx_peminjam', 'peminjam']] as [$name, $role]) {
    $insertUser->execute([$name, $name, $hash, $role]);
}
$userId = static fn (string $username): int => (int) dbValue($db, 'SELECT id FROM users WHERE username = ?', [$username]);
$adminId = $userId('itx_admin');
$petugasId = $userId('itx_petugas');
$peminjamId = $userId('itx_peminjam');

$db->prepare('INSERT INTO kategori (nama, deskripsi) VALUES (?, ?)')->execute(['TST-Kategori', 'Fixture']);
$kategoriId = (int) $db->lastInsertId();
$insertAlat = $db->prepare('INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia) VALUES (?, ?, ?, ?, ?)');
$alat = [];
foreach ([['TST-ALT-A', 'Alat A', 5, 3], ['TST-ALT-B', 'Alat B', 3, 1], ['TST-ALT-C', 'Alat C', 2, 2]] as [$kode, $nama, $total, $tersedia]) {
    $insertAlat->execute([$kode, $kategoriId, $nama, $total, $tersedia]);
    $alat[$kode] = (int) $db->lastInsertId();
}

$today = date('Y-m-d');
$daysAgo = static fn (int $days): string => date('Y-m-d', strtotime("-{$days} days"));
$insertLoan = $db->prepare(
    'INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo, status, catatan, approved_by, approved_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$loan = [];
$rows = [
    ['TST-JM-PENDING', 'TST-ALT-A', 2, $today, date('Y-m-d', strtotime($today . ' +7 days')), 'pending', null, null, null],
    ['TST-JM-REJECTED', 'TST-ALT-A', 1, $today, $daysAgo(-7), 'rejected', 'Fixture tolak', null, null],
    ['TST-JM-APPROVED', 'TST-ALT-A', 2, $today, $daysAgo(-7), 'approved', null, $petugasId, $today . ' 08:00:00'],
    ['TST-JM-RETREQ', 'TST-ALT-B', 2, $daysAgo(5), $daysAgo(2), 'return_requested', null, $petugasId, $daysAgo(5) . ' 08:00:00'],
    ['TST-JM-RETURNED', 'TST-ALT-C', 2, $daysAgo(10), $daysAgo(3), 'returned', null, $petugasId, $daysAgo(10) . ' 08:00:00'],
];
foreach ($rows as [$kode, $alatKode, $jumlah, $pinjam, $tempo, $status, $catatan, $approver, $approvedAt]) {
    $insertLoan->execute([$kode, $peminjamId, $alat[$alatKode], $jumlah, $pinjam, $tempo, $status, $catatan, $approver, $approvedAt]);
    $loan[$kode] = (int) $db->lastInsertId();
}

$insertReturn = $db->prepare(
    'INSERT INTO pengembalian (peminjaman_id, tanggal_pengajuan, tanggal_diterima, jumlah, kondisi, hari_terlambat, denda, verified_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$insertReturn->execute([$loan['TST-JM-RETREQ'], $daysAgo(1) . ' 09:00:00', null, 2, 'baik', 0, 0, null]);
$insertReturn->execute([$loan['TST-JM-RETURNED'], $daysAgo(2) . ' 09:00:00', $daysAgo(1) . ' 00:00:00', 2, 'baik', 2, 0, $petugasId]);
$oldTarif = dbValue($db, 'SELECT nilai FROM pengaturan WHERE kunci = ?', ['tarif_denda']);

$port = 18400 + random_int(0, 400);
$base = 'http://127.0.0.1:' . $port;
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $root,
    ['DB_HOST' => $host, 'DB_PORT' => $dbPort, 'DB_NAME' => $dbName,
     'DB_USER' => (string) getenv('DB_USER'), 'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
     'PATH' => (string) getenv('PATH')]
);
$ready = false;
for ($i = 0; $i < 100 && !$ready; $i++) {
    $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
    if (is_resource($probe)) {
        fclose($probe);
        $ready = true;
    } else {
        usleep(50000);
    }
}
check('built-in server started', $ready);

$tmp = sys_get_temp_dir() . '/sparta-backoffice-' . getmypid();
@mkdir($tmp);
try {
    $adminJar = $tmp . '/admin.jar';
    $petugasJar = $tmp . '/petugas.jar';
    $peminjamJar = $tmp . '/peminjam.jar';
    check('admin login', login($base, $adminJar, 'itx_admin', $password)['status'] === 302);
    check('petugas login', login($base, $petugasJar, 'itx_petugas', $password)['status'] === 302);
    check('peminjam login', login($base, $peminjamJar, 'itx_peminjam', $password)['status'] === 302);

    // --- FR-06: admin loan list, search, filter ---
    $list = request($base, 'GET', '/admin/peminjaman', [], $adminJar);
    check('admin loan list is 200', $list['status'] === 200);
    check('admin loan list shows fixture loan', str_contains($list['body'], 'TST-JM-PENDING'));
    $filtered = request($base, 'GET', '/admin/peminjaman?status=pending', [], $adminJar);
    check('status filter keeps pending', str_contains($filtered['body'], 'TST-JM-PENDING'));
    check('status filter hides approved', !str_contains($filtered['body'], 'TST-JM-APPROVED'));
    $searched = request($base, 'GET', '/admin/peminjaman?q=TST-JM-REJECTED', [], $adminJar);
    check('search finds loan by code', str_contains($searched['body'], 'TST-JM-REJECTED'));
    check('search hides non-matching loan', !str_contains($searched['body'], 'TST-JM-APPROVED'));

    $editForm = request($base, 'GET', '/admin/peminjaman/' . $loan['TST-JM-PENDING'] . '/edit', [], $adminJar);
    check('pending edit form is 200', $editForm['status'] === 200);
    $token = csrfFrom($editForm['body']);
    check('edit form carries csrf', $token !== '');

    $badEdit = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-PENDING'] . '/edit', [
        '_token' => $token, 'jumlah' => '0', 'tanggal_pinjam' => $today,
        'tanggal_jatuh_tempo' => $daysAgo(-3), 'catatan' => 'x',
    ], $adminJar);
    check('invalid quantity is 422', $badEdit['status'] === 422);
    check('invalid quantity leaves row untouched',
        (string) dbValue($db, 'SELECT jumlah FROM peminjaman WHERE id = ?', [$loan['TST-JM-PENDING']]) === '2');

    $noCsrf = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-PENDING'] . '/edit', [
        'jumlah' => '3', 'tanggal_pinjam' => $today, 'tanggal_jatuh_tempo' => $daysAgo(-3),
    ], $adminJar);
    check('edit without csrf is 403', $noCsrf['status'] === 403);

    $goodEdit = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-PENDING'] . '/edit', [
        '_token' => $token, 'jumlah' => '3', 'tanggal_pinjam' => $today,
        'tanggal_jatuh_tempo' => $daysAgo(-5), 'catatan' => 'Koreksi uji TST-',
    ], $adminJar);
    check('valid correction redirects', $goodEdit['status'] === 302);
    check('correction stored quantity',
        (string) dbValue($db, 'SELECT jumlah FROM peminjaman WHERE id = ?', [$loan['TST-JM-PENDING']]) === '3');
    check('correction stored note',
        (string) dbValue($db, 'SELECT catatan FROM peminjaman WHERE id = ?', [$loan['TST-JM-PENDING']]) === 'Koreksi uji TST-');
    check('correction is audited', in_array('update', array_column(auditRows($db, 'peminjaman', $loan['TST-JM-PENDING']), 'action'), true));

    $noopEdit = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-PENDING'] . '/edit', [
        '_token' => $token, 'jumlah' => '3', 'tanggal_pinjam' => $today,
        'tanggal_jatuh_tempo' => $daysAgo(-5), 'catatan' => 'Koreksi uji TST-',
    ], $adminJar);
    check('unchanged loan correction succeeds', $noopEdit['status'] === 302);
    check('unchanged loan correction keeps quantity',
        (string) dbValue($db, 'SELECT jumlah FROM peminjaman WHERE id = ?', [$loan['TST-JM-PENDING']]) === '3');

    $tooMany = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-PENDING'] . '/edit', [
        '_token' => $token, 'jumlah' => '99', 'tanggal_pinjam' => $today,
        'tanggal_jatuh_tempo' => $daysAgo(-5), 'catatan' => '',
    ], $adminJar);
    check('quantity above stock is 422', $tooMany['status'] === 422);
    check('quantity above stock not stored',
        (string) dbValue($db, 'SELECT jumlah FROM peminjaman WHERE id = ?', [$loan['TST-JM-PENDING']]) === '3');

    $approvedEdit = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-APPROVED'] . '/edit', [
        '_token' => $token, 'jumlah' => '1', 'tanggal_pinjam' => $today,
        'tanggal_jatuh_tempo' => $daysAgo(-5), 'catatan' => 'nope',
    ], $adminJar);
    check('approved loan correction is 409', $approvedEdit['status'] === 409);
    check('approved loan quantity untouched',
        (string) dbValue($db, 'SELECT jumlah FROM peminjaman WHERE id = ?', [$loan['TST-JM-APPROVED']]) === '2');

    $missingEdit = request($base, 'GET', '/admin/peminjaman/999999999/edit', [], $adminJar);
    check('unknown loan edit is 404', $missingEdit['status'] === 404);

    // --- FR-06: delete only pending/rejected ---
    $deleteApproved = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-APPROVED'] . '/delete', ['_token' => $token], $adminJar);
    check('deleting approved loan is 409', $deleteApproved['status'] === 409);
    check('approved loan still exists',
        (string) dbValue($db, 'SELECT COUNT(*) FROM peminjaman WHERE id = ?', [$loan['TST-JM-APPROVED']]) === '1');
    check('approved loan stock unchanged',
        (string) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['TST-ALT-A']]) === '3');

    $deleteRejected = request($base, 'POST', '/admin/peminjaman/' . $loan['TST-JM-REJECTED'] . '/delete', ['_token' => $token], $adminJar);
    check('deleting rejected loan redirects', $deleteRejected['status'] === 302);
    check('rejected loan removed',
        (string) dbValue($db, 'SELECT COUNT(*) FROM peminjaman WHERE id = ?', [$loan['TST-JM-REJECTED']]) === '0');

    // --- FR-07: admin return records ---
    $returns = request($base, 'GET', '/admin/pengembalian', [], $adminJar);
    check('admin return list is 200', $returns['status'] === 200);
    check('return list shows pending request', str_contains($returns['body'], 'TST-JM-RETREQ'));
    check('return list shows verified record', str_contains($returns['body'], 'TST-JM-RETURNED'));
    $returnsPending = request($base, 'GET', '/admin/pengembalian?status=pending', [], $adminJar);
    check('pending return filter hides verified', !str_contains($returnsPending['body'], 'TST-JM-RETURNED'));

    $unverifiedEdit = request($base, 'GET', '/admin/pengembalian/' . $loan['TST-JM-RETREQ'] . '/edit', [], $adminJar);
    check('unverified return edit is 409', $unverifiedEdit['status'] === 409);

    $deleteVerified = request($base, 'POST', '/admin/pengembalian/' . $loan['TST-JM-RETURNED'] . '/delete', ['_token' => $token], $adminJar);
    check('deleting verified return is 409', $deleteVerified['status'] === 409);
    check('verified return row kept',
        (string) dbValue($db, 'SELECT COUNT(*) FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === '1');
    check('returned stock unchanged',
        (string) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['TST-ALT-C']]) === '2');

    $verifiedEditForm = request($base, 'GET', '/admin/pengembalian/' . $loan['TST-JM-RETURNED'] . '/edit', [], $adminJar);
    check('verified return edit form is 200', $verifiedEditForm['status'] === 200);
    $returnToken = csrfFrom($verifiedEditForm['body']);
    check('return edit form carries csrf', $returnToken !== '');

    $db->exec("INSERT INTO pengaturan (kunci, nilai) VALUES ('tarif_denda', '1000') ON DUPLICATE KEY UPDATE nilai = '1000'");
    $returnNoop = request($base, 'POST', '/admin/pengembalian/' . $loan['TST-JM-RETURNED'] . '/edit', [
        '_token' => $returnToken, 'tanggal_diterima' => $daysAgo(1), 'kondisi' => 'baik',
    ], $adminJar);
    check('unchanged return correction succeeds', $returnNoop['status'] === 302);
    check('notes-only correction keeps fine',
        (string) dbValue($db, 'SELECT denda FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === '0.00');
    check('notes-only correction keeps verifier',
        (string) dbValue($db, 'SELECT verified_by FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === (string) $petugasId);

    $returnEdit = request($base, 'POST', '/admin/pengembalian/' . $loan['TST-JM-RETURNED'] . '/edit', [
        '_token' => $returnToken, 'tanggal_diterima' => $daysAgo(1), 'kondisi' => 'rusak_ringan',
    ], $adminJar);
    check('verified return correction redirects', $returnEdit['status'] === 302);
    check('return condition stored',
        (string) dbValue($db, 'SELECT kondisi FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === 'rusak_ringan');
    check('lateness recomputed',
        (string) dbValue($db, 'SELECT hari_terlambat FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === '2');
    check('condition correction keeps verifier',
        (string) dbValue($db, 'SELECT verified_by FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === (string) $petugasId);
    check('condition correction keeps historical fine',
        (string) dbValue($db, 'SELECT denda FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === '0.00');

    $futureDate = request($base, 'POST', '/admin/pengembalian/' . $loan['TST-JM-RETURNED'] . '/edit', [
        '_token' => $returnToken, 'tanggal_diterima' => $daysAgo(-5), 'kondisi' => 'baik',
    ], $adminJar);
    check('future reception date is 422', $futureDate['status'] === 422);
    check('future date not stored',
        (string) dbValue($db, 'SELECT tanggal_diterima FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === $daysAgo(1) . ' 00:00:00');

    $dateCorrection = request($base, 'POST', '/admin/pengembalian/' . $loan['TST-JM-RETURNED'] . '/edit', [
        '_token' => $returnToken, 'tanggal_diterima' => $today, 'kondisi' => 'rusak_ringan',
    ], $adminJar);
    check('date correction redirects', $dateCorrection['status'] === 302);
    check('date correction recomputes lateness',
        (string) dbValue($db, 'SELECT hari_terlambat FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === '3');
    check('date correction reprices at current tariff',
        (string) dbValue($db, 'SELECT denda FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETURNED']]) === '6000.00');

    $deletePending = request($base, 'POST', '/admin/pengembalian/' . $loan['TST-JM-RETREQ'] . '/delete', ['_token' => $returnToken], $adminJar);
    check('deleting unverified return redirects', $deletePending['status'] === 302);
    check('unverified return removed',
        (string) dbValue($db, 'SELECT COUNT(*) FROM pengembalian WHERE peminjaman_id = ?', [$loan['TST-JM-RETREQ']]) === '0');
    check('loan reverted to approved',
        (string) dbValue($db, 'SELECT status FROM peminjaman WHERE id = ?', [$loan['TST-JM-RETREQ']]) === 'approved');
    check('deletion is audited', in_array('delete', array_column(auditRows($db, 'pengembalian', $loan['TST-JM-RETREQ']), 'action'), true));

    // --- FR-08: activity log ---
    $logs = request($base, 'GET', '/admin/activity-logs', [], $adminJar);
    check('activity log is 200', $logs['status'] === 200);
    check('activity log lists corrections', str_contains($logs['body'], 'Koreksi pengajuan peminjaman'));
    check('activity log lists login events', str_contains($logs['body'], 'itx_admin'));
    $logsFiltered = request($base, 'GET', '/admin/activity-logs?action=status_change', [], $adminJar);
    check('activity log action filter is 200', $logsFiltered['status'] === 200);
    check('action filter hides other actions', !str_contains($logsFiltered['body'], 'Koreksi pengajuan peminjaman'));
    $logsSearched = request($base, 'GET', '/admin/activity-logs?q=TST-JM-RETREQ', [], $adminJar);
    check('activity search is 200', $logsSearched['status'] === 200);
    check('activity log stores no password', !str_contains($logs['body'], $password));
    check('audit rows store no session id',
        (string) dbValue($db, "SELECT COUNT(*) FROM activity_logs WHERE description REGEXP '[0-9a-f]{32,}'") === '0');

    // --- RBAC: back office is admin-only ---
    check('petugas blocked from admin loans', request($base, 'GET', '/admin/peminjaman', [], $petugasJar)['status'] === 403);
    check('petugas blocked from admin returns', request($base, 'GET', '/admin/pengembalian', [], $petugasJar)['status'] === 403);
    check('petugas blocked from activity log', request($base, 'GET', '/admin/activity-logs', [], $petugasJar)['status'] === 403);
    check('peminjam blocked from admin loans', request($base, 'GET', '/admin/peminjaman', [], $peminjamJar)['status'] === 403);
    check('peminjam blocked from admin returns', request($base, 'GET', '/admin/pengembalian', [], $peminjamJar)['status'] === 403);
    check('peminjam blocked from activity log', request($base, 'GET', '/admin/activity-logs', [], $peminjamJar)['status'] === 403);

    // --- FR-11: Petugas report ---
    $report = request($base, 'GET', '/petugas/laporan', [], $petugasJar);
    check('report is 200', $report['status'] === 200);
    check('report shows fixture loan', str_contains($report['body'], 'TST-JM-PENDING'));
    check('report shows summary', str_contains($report['body'], 'Jumlah transaksi'));
    $reportCount = (int) dbValue(
        $db,
        'SELECT COUNT(*) FROM peminjaman WHERE tanggal_pinjam BETWEEN ? AND ?',
        [date('Y-m-01'), date('Y-m-d')]
    );
    check('report row count matches database', str_contains($report['body'], '<div class="stat-value text-2xl tabular-nums">' . $reportCount . '</div>'));
    $reportPending = request($base, 'GET', '/petugas/laporan?status=pending', [], $petugasJar);
    check('report status filter is 200', $reportPending['status'] === 200);
    check('report status filter keeps pending', str_contains($reportPending['body'], 'TST-JM-PENDING'));
    check('report status filter hides approved', !str_contains($reportPending['body'], 'TST-JM-APPROVED'));
    $reportInvalid = request($base, 'GET', '/petugas/laporan?from=2026-13-99&to=bad', [], $petugasJar);
    check('invalid report dates are 422', $reportInvalid['status'] === 422);
    $reportInverted = request($base, 'GET', '/petugas/laporan?from=' . $today . '&to=' . $daysAgo(10), [], $petugasJar);
    check('inverted report range is 422', $reportInverted['status'] === 422);
    $reportBogusStatus = request($base, 'GET', '/petugas/laporan?status=everything', [], $petugasJar);
    check('bogus report status is 422', $reportBogusStatus['status'] === 422);

    $print = request($base, 'GET', '/petugas/laporan/cetak', [], $petugasJar);
    check('print view is 200', $print['status'] === 200);
    check('print view shows fixture loan', str_contains($print['body'], 'TST-JM-PENDING'));
    check('print view has print action', str_contains($print['body'], 'window.print()'));
    check('print view has no app drawer', !str_contains($print['body'], 'drawer-side'));
    check('admin blocked from report', request($base, 'GET', '/petugas/laporan', [], $adminJar)['status'] === 403);
    check('peminjam blocked from report', request($base, 'GET', '/petugas/laporan', [], $peminjamJar)['status'] === 403);
    check('peminjam blocked from print view', request($base, 'GET', '/petugas/laporan/cetak', [], $peminjamJar)['status'] === 403);
} finally {
    proc_terminate($server);
    if ($oldTarif === false) {
        $db->prepare('DELETE FROM pengaturan WHERE kunci = ?')->execute(['tarif_denda']);
    } else {
        $db->prepare('UPDATE pengaturan SET nilai = ? WHERE kunci = ?')->execute([(string) $oldTarif, 'tarif_denda']);
    }
    // Remove fixtures (pengembalian/loans reference alat and users).
    $db->exec("DELETE pg FROM pengembalian pg JOIN peminjaman p ON p.id = pg.peminjaman_id WHERE p.kode LIKE 'TST-%'");
    $db->exec("DELETE FROM peminjaman WHERE kode LIKE 'TST-%'");
    $db->exec("DELETE FROM alat WHERE kode LIKE 'TST-%'");
    $db->exec("DELETE FROM kategori WHERE nama LIKE 'TST-%'");
    $db->prepare("DELETE al FROM activity_logs al JOIN users u ON u.id = al.user_id WHERE u.username IN ($in)")->execute($usernames);
    $db->exec("DELETE FROM activity_logs WHERE description LIKE '%TST-%'");
    $db->prepare("DELETE FROM users WHERE username IN ($in)")->execute($usernames);
    foreach (glob($tmp . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($tmp);
}

printf("%d checks, %d failed\n", $checks, count($failures));
foreach ($failures as $failure) {
    echo '  FAIL: ' . $failure . "\n";
}
exit($failures === [] ? 0 : 1);
