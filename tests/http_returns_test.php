<?php
declare(strict_types=1);
// Focused HTTP/database checks for returns (FR-10, FR-14, section 4 BR).
//   DB_PORT=33306 DB_USER=sparta DB_PASSWORD=sparta php tests/http_returns_test.php
// Exit 0 = pass, 1 = fail, 2 = skipped (DB unreachable or not disposable).
//
// SAFETY: fixtures are prefixed IRT-/irt_ and only those rows are removed.
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
$db = $container['db'];
require_once $root . '/app/autoload.php';
date_default_timezone_set('Asia/Jakarta');
$db->exec((string) file_get_contents($root . '/database/schema.sql'));

/** Minimal DELIMITER support so the status trigger and fine function exist. */
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
// Tests own the global tariff row; always start from the zero default.
$db->exec("INSERT INTO pengaturan (kunci, nilai) VALUES ('tarif_denda', '0')
           ON DUPLICATE KEY UPDATE nilai = '0'");

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
function auditRows(PDO $db, string $entity, int $id): array
{
    $stmt = $db->prepare('SELECT action, user_id FROM activity_logs WHERE entity_type = ? AND entity_id = ? ORDER BY id');
    $stmt->execute([$entity, $id]);
    return $stmt->fetchAll();
}
function statusAuditCount(PDO $db, int $id): int
{
    return (int) dbValue(
        $db,
        "SELECT COUNT(*) FROM activity_logs WHERE entity_type = 'peminjaman' AND entity_id = ? AND action = 'status_change'",
        [$id]
    );
}
/** @return array{resource: resource, pipes: array<int, resource>} */
function startServer(string $root, int $port, array $env): array
{
    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php'],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        $root,
        $env
    );
    return ['resource' => $process, 'pipes' => $pipes];
}
function waitServer(int $port): bool
{
    for ($i = 0; $i < 100; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if (is_resource($probe)) {
            fclose($probe);
            return true;
        }
        usleep(50000);
    }
    return false;
}
/**
 * @param list<array{base: string, path: string, data: array<string, string>, jar: string}> $requests
 * @return list<int>
 */
function postConcurrently(array $requests): array
{
    $multi = curl_multi_init();
    $handles = [];
    foreach ($requests as $index => $spec) {
        $curl = curl_init($spec['base'] . $spec['path']);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => http_build_query($spec['data']),
            CURLOPT_COOKIEJAR => $spec['jar'],
            CURLOPT_COOKIEFILE => $spec['jar'],
        ]);
        curl_multi_add_handle($multi, $curl);
        $handles[$index] = $curl;
    }
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 0.2);
        }
    } while ($running && $status === CURLM_OK);
    $codes = [];
    foreach ($handles as $index => $curl) {
        $codes[$index] = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_multi_remove_handle($multi, $curl);
        curl_close($curl);
    }
    curl_multi_close($multi);
    return $codes;
}

// --- fixtures ---------------------------------------------------------------
$cleanup = static function () use ($db): void {
    $loans = 'SELECT p.id FROM peminjaman p JOIN users u ON u.id = p.user_id WHERE u.username LIKE \'irt_%\'';
    $db->exec("DELETE FROM activity_logs WHERE entity_type = 'peminjaman' AND entity_id IN ($loans)");
    $db->exec("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'irt_%')");
    $db->exec("DELETE FROM pengembalian WHERE peminjaman_id IN ($loans)");
    $db->exec("DELETE FROM peminjaman WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'irt_%')");
    $db->exec("DELETE FROM alat WHERE kode LIKE 'IRT-%'");
    $db->exec("DELETE FROM kategori WHERE nama LIKE 'IRT %'");
    $db->exec("DELETE FROM users WHERE username LIKE 'irt_%'");
};
$cleanup();
$password = 'test-secret-123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$insertUser = $db->prepare('INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
foreach ([
    'irt_peminjam' => ['Peminjam Satu', 'peminjam'],
    'irt_peminjam2' => ['Peminjam Dua', 'peminjam'],
    'irt_petugas' => ['Petugas Satu', 'petugas'],
    'irt_petugas2' => ['Petugas Dua', 'petugas'],
    'irt_admin' => ['Admin Uji', 'admin'],
] as $username => [$name, $role]) {
    $insertUser->execute([$name, $username, $hash, $role]);
}
$user1 = (int) dbValue($db, "SELECT id FROM users WHERE username = 'irt_peminjam'");
$user2 = (int) dbValue($db, "SELECT id FROM users WHERE username = 'irt_peminjam2'");
$petugas = (int) dbValue($db, "SELECT id FROM users WHERE username = 'irt_petugas'");
$db->prepare("INSERT INTO kategori (nama, deskripsi) VALUES ('IRT Utama', 'Fixture')")->execute();
$kategori = (int) dbValue($db, "SELECT id FROM kategori WHERE nama = 'IRT Utama'");
$insertTool = $db->prepare(
    'INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia, is_active) VALUES (?, ?, ?, ?, ?, 1)'
);
$tools = [];
for ($i = 1; $i <= 12; $i++) {
    $stock = match ($i) {
        1, 5 => [5, 3],
        3 => [2, 0],
        7 => [5, 5], // deliberately inconsistent for rollback coverage
        12 => [5, 3],
        default => [5, 4],
    };
    $insertTool->execute(['IRT-T' . $i, $kategori, 'IRT Alat ' . $i, $stock[0], $stock[1]]);
    $tools[$i] = (int) dbValue($db, 'SELECT id FROM alat WHERE kode = ?', ['IRT-T' . $i]);
}
$insertLoan = $db->prepare(
    'INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$loans = [
    // id => [code, user, tool, qty, pinjam, tempo, status]
    1 => ['IRT-L1', $user1, $tools[1], 2, '2026-10-01', '2026-10-08', 'approved'],
    2 => ['IRT-L2', $user1, $tools[2], 1, '2026-10-01', '2026-10-08', 'approved'],
    3 => ['IRT-L3', $user2, $tools[3], 2, '2026-09-01', '2026-09-10', 'return_requested'],
    4 => ['IRT-L4', $user1, $tools[4], 1, '2026-01-01', '2026-01-10', 'return_requested'],
    5 => ['IRT-L5', $user1, $tools[5], 2, '2026-02-01', '2026-02-05', 'return_requested'],
    6 => ['IRT-L6', $user1, $tools[6], 2, '2026-03-01', '2026-03-10', 'return_requested'],
    7 => ['IRT-L7', $user1, $tools[7], 2, '2026-04-01', '2026-04-10', 'return_requested'],
    8 => ['IRT-L8', $user2, $tools[8], 1, '2026-05-01', '2026-05-10', 'pending'],
    9 => ['IRT-L9', $user1, $tools[9], 1, '2026-06-01', '2026-06-10', 'returned'],
    10 => ['IRT-L10', $user1, $tools[10], 1, '2026-07-01', '2026-07-10', 'rejected'],
    11 => ['IRT-L11', $user1, $tools[11], 1, '2026-08-01', '2026-08-10', 'approved'],
    12 => ['IRT-L12', $user1, $tools[12], 2, '2026-10-01', '2026-10-08', 'return_requested'],
];
$loanIds = [];
foreach ($loans as $index => $row) {
    $insertLoan->execute($row);
    $loanIds[$index] = (int) $db->lastInsertId();
}
$insertReturn = $db->prepare(
    "INSERT INTO pengembalian
        (peminjaman_id, tanggal_pengajuan, jumlah, kondisi, hari_terlambat, denda, tanggal_diterima)
     VALUES (?, ?, ?, ?, 0, 0, ?)"
);
$insertReturn->execute([$loanIds[3], '2026-09-11 09:00:00', 2, 'baik', null]);
$insertReturn->execute([$loanIds[4], '2026-01-05 08:00:00', 1, 'baik', null]);
$insertReturn->execute([$loanIds[5], '2026-02-06 10:00:00', 2, 'baik', null]);
$insertReturn->execute([$loanIds[6], '2026-03-11 10:00:00', 2, 'baik', null]);
$insertReturn->execute([$loanIds[7], '2026-04-11 10:00:00', 2, 'baik', null]);
$insertReturn->execute([$loanIds[9], '2026-06-05 10:00:00', 1, 'baik', '2026-06-06 00:00:00']);
$insertReturn->execute([$loanIds[12], '2026-10-09 10:00:00', 2, 'baik', null]);

$env = [
    'DB_HOST' => $host,
    'DB_PORT' => $dbPort,
    'DB_NAME' => $dbName,
    'DB_USER' => getenv('DB_USER') ?: 'root',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ?: '',
    'PATH' => (string) getenv('PATH'),
];
$port = 21000 + random_int(0, 4000);
$portSecond = $port + 5000;
$base = 'http://127.0.0.1:' . $port;
$baseSecond = 'http://127.0.0.1:' . $portSecond;
$server = startServer($root, $port, $env);
$serverSecond = startServer($root, $portSecond, $env);
try {
    check('HTTP server started', waitServer($port));
    check('second HTTP server started', waitServer($portSecond));
    $tmp = sys_get_temp_dir() . '/sparta-returns-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0700, true);
    $anonJar = $tmp . '/anon.cookies';
    $jar1 = $tmp . '/peminjam1.cookies';
    $jar2 = $tmp . '/peminjam2.cookies';
    $petugasJar = $tmp . '/petugas.cookies';
    $petugasJar2 = $tmp . '/petugas2.cookies';
    $adminJar = $tmp . '/admin.cookies';
    login($base, $jar1, 'irt_peminjam', $password);
    login($base, $jar2, 'irt_peminjam2', $password);
    login($base, $petugasJar, 'irt_petugas', $password);
    login($baseSecond, $petugasJar2, 'irt_petugas2', $password);
    login($base, $adminJar, 'irt_admin', $password);
    $pToken = csrfFrom(request($base, 'GET', '/peminjam/peminjaman', [], $jar1)['body']);
    $pToken2 = csrfFrom(request($base, 'GET', '/peminjam/peminjaman', [], $jar2)['body']);
    $petugasToken = csrfFrom(request($base, 'GET', '/petugas/persetujuan', [], $petugasJar)['body']);
    // --- configurable fine tariff -----------------------------------------
    $settingsPage = request($base, 'GET', '/admin/pengaturan', [], $adminJar);
    check('admin settings page renders configurable fine tariff',
        $settingsPage['status'] === 200 && str_contains($settingsPage['body'], 'name="tarif_denda"'));
    check('peminjam cannot open admin settings',
        request($base, 'GET', '/admin/pengaturan', [], $jar1)['status'] === 403);
    $adminToken = csrfFrom($settingsPage['body']);
    check('settings reject tariff above the maximum',
        request($base, 'POST', '/admin/pengaturan',
            ['_token' => $adminToken, 'tarif_denda' => '1000001'], $adminJar)['status'] === 422);
    check('admin can save a new tariff',
        request($base, 'POST', '/admin/pengaturan',
            ['_token' => $adminToken, 'tarif_denda' => '1000'], $adminJar)['status'] === 302
        && dbValue($db, "SELECT nilai FROM pengaturan WHERE kunci = 'tarif_denda'") === '1000');
    request($base, 'POST', '/admin/pengaturan',
        ['_token' => $adminToken, 'tarif_denda' => '0'], $adminJar);

    // --- shared page contract ----------------------------------------------
    $borrowerPage = request($base, 'GET', '/peminjam/pengembalian', [], $jar1);
    check('borrower returns page renders eligible loans and request form',
        $borrowerPage['status'] === 200 && str_contains($borrowerPage['body'], 'Pengembalian Saya')
        && str_contains($borrowerPage['body'], 'IRT-L1')
        && str_contains($borrowerPage['body'], '/peminjam/peminjaman/' . $loanIds[1] . '/return'));
    $monitorPage = request($base, 'GET', '/petugas/pengembalian', [], $petugasJar);
    check('Petugas active monitoring shows approved and requested only',
        $monitorPage['status'] === 200 && str_contains($monitorPage['body'], 'Monitoring Pengembalian')
        && str_contains($monitorPage['body'], 'IRT-L3') && !str_contains($monitorPage['body'], 'IRT-L9'));
    $returnedPage = request($base, 'GET', '/petugas/pengembalian?status=returned', [], $petugasJar);
    check('Petugas returned filter renders completed loan',
        $returnedPage['status'] === 200 && str_contains($returnedPage['body'], 'IRT-L9')
        && !str_contains($returnedPage['body'], 'IRT-L1'));
    $searchPage = request($base, 'GET', '/petugas/pengembalian?q=IRT-L3', [], $petugasJar);
    check('Petugas search renders matching loan only',
        $searchPage['status'] === 200 && str_contains($searchPage['body'], 'IRT-L3')
        && !str_contains($searchPage['body'], 'IRT-L4'));
    $verifyPage = request($base, 'GET', '/petugas/pengembalian/' . $loanIds[4] . '/verify', [], $petugasJar);
    check('verification form renders loan, date and condition fields',
        $verifyPage['status'] === 200 && str_contains($verifyPage['body'], 'name="tanggal_diterima"')
        && str_contains($verifyPage['body'], 'name="kondisi"') && str_contains($verifyPage['body'], 'Penerimaan Pengembalian'));
    check('verification form rejects non-requested loan',
        request($base, 'GET', '/petugas/pengembalian/' . $loanIds[8] . '/verify', [], $petugasJar)['status'] === 409);

    // --- RBAC and CSRF ------------------------------------------------------
    check('anonymous returns page redirects to login',
        request($base, 'GET', '/peminjam/pengembalian', [], $anonJar)['status'] === 302);
    check('petugas cannot open borrower returns page',
        request($base, 'GET', '/peminjam/pengembalian', [], $petugasJar)['status'] === 403);
    check('peminjam cannot open Petugas monitoring',
        request($base, 'GET', '/petugas/pengembalian', [], $jar1)['status'] === 403);
    check('admin cannot open Petugas monitoring',
        request($base, 'GET', '/petugas/pengembalian', [], $adminJar)['status'] === 403);
    check('petugas cannot POST borrower return request',
        request($base, 'POST', '/peminjam/peminjaman/' . $loanIds[11] . '/return',
            ['_token' => $petugasToken], $petugasJar)['status'] === 403);
    check('peminjam cannot POST verification',
        request($base, 'POST', '/petugas/pengembalian/' . $loanIds[4] . '/verify',
            ['_token' => $pToken, 'tanggal_diterima' => '2026-01-08', 'kondisi' => 'baik'], $jar1)['status'] === 403);

    // --- owner request: one pending row, stock untouched --------------------
    check('missing CSRF blocks return request',
        request($base, 'POST', '/peminjam/peminjaman/' . $loanIds[1] . '/return', [], $jar1)['status'] === 403);
    check('other borrower cannot request return',
        request($base, 'POST', '/peminjam/peminjaman/' . $loanIds[1] . '/return',
            ['_token' => $pToken2], $jar2)['status'] === 403);
    check('ownership rejection leaves loan approved and no return row',
        dbValue($db, 'SELECT status FROM peminjaman WHERE id = ?', [$loanIds[1]]) === 'approved'
        && (int) dbValue($db, 'SELECT COUNT(*) FROM pengembalian WHERE peminjaman_id = ?', [$loanIds[1]]) === 0);
    $requested = request($base, 'POST', '/peminjam/peminjaman/' . $loanIds[1] . '/return',
        ['_token' => $pToken], $jar1);
    check('owner return request redirects to own list', $requested['status'] === 302
        && str_contains($requested['headers'], 'Location: /peminjam/pengembalian'));
    $pendingReturn = $db->prepare('SELECT * FROM pengembalian WHERE peminjaman_id = ?');
    $pendingReturn->execute([$loanIds[1]]);
    $pending = $pendingReturn->fetch();
    check('request creates one pending reception row', is_array($pending)
        && (int) $pending['jumlah'] === 2 && $pending['kondisi'] === 'baik'
        && $pending['tanggal_pengajuan'] !== null && $pending['tanggal_diterima'] === null
        && (int) $pending['hari_terlambat'] === 0 && (float) $pending['denda'] === 0.0);
    check('request marks loan return_requested', dbValue($db,
        'SELECT status FROM peminjaman WHERE id = ?', [$loanIds[1]]) === 'return_requested');
    check('request does not move stock', (int) dbValue($db,
        'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[1]]) === 3);
    $requestAudit = auditRows($db, 'peminjaman', $loanIds[1]);
    check('request writes one trigger status audit by borrower', count(array_filter($requestAudit,
        static fn (array $a): bool => $a['action'] === 'status_change' && (int) $a['user_id'] === $user1)) === 1);
    check('duplicate request is 409',
        request($base, 'POST', '/peminjam/peminjaman/' . $loanIds[1] . '/return',
            ['_token' => $pToken], $jar1)['status'] === 409);
    check('duplicate request adds no row, stock or audit',
        (int) dbValue($db, 'SELECT COUNT(*) FROM pengembalian WHERE peminjaman_id = ?', [$loanIds[1]]) === 1
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[1]]) === 3
        && statusAuditCount($db, $loanIds[1]) === 1);
    check('unknown loan request is 404',
        request($base, 'POST', '/peminjam/peminjaman/99999999/return', ['_token' => $pToken], $jar1)['status'] === 404);
    check('pending loan cannot request return',
        request($base, 'POST', '/peminjam/peminjaman/' . $loanIds[8] . '/return',
            ['_token' => $pToken2], $jar2)['status'] === 409
        && (int) dbValue($db, 'SELECT COUNT(*) FROM pengembalian WHERE peminjaman_id = ?', [$loanIds[8]]) === 0);

    // Forged quantity, condition, fine and owner are ignored on request.
    $forged = request($base, 'POST', '/peminjam/peminjaman/' . $loanIds[2] . '/return', [
        '_token' => $pToken, 'user_id' => (string) $user2, 'jumlah' => '999',
        'kondisi' => 'hilang', 'denda' => '999.99', 'tanggal_diterima' => '2026-10-01',
    ], $jar1);
    $forgedRow = $db->query('SELECT * FROM pengembalian WHERE peminjaman_id = ' . $loanIds[2])->fetch();
    check('forged fields cannot create partial or fined request', $forged['status'] === 302
        && (int) $forgedRow['jumlah'] === 1 && $forgedRow['kondisi'] === 'baik'
        && (float) $forgedRow['denda'] === 0.0 && $forgedRow['tanggal_diterima'] === null);

    // --- verification: full stock back, late/fine, duplicate ---------------
    $verified = request($base, 'POST', '/petugas/pengembalian/' . $loanIds[3] . '/verify', [
        '_token' => $petugasToken, 'tanggal_diterima' => '2026-09-12', 'kondisi' => 'hilang',
        'jumlah' => '999', 'denda' => '999.99',
    ], $petugasJar);
    $received = $db->query('SELECT * FROM pengembalian WHERE peminjaman_id = ' . $loanIds[3])->fetch();
    check('verified return redirects to monitoring', $verified['status'] === 302
        && str_contains($verified['headers'], 'Location: /petugas/pengembalian'));
    check('verification derives quantity, records condition and zero fine',
        (int) $received['jumlah'] === 2 && $received['kondisi'] === 'hilang'
        && (int) $received['hari_terlambat'] === 2 && (float) $received['denda'] === 0.0
        && $received['tanggal_diterima'] === '2026-09-12 00:00:00'
        && (int) $received['verified_by'] === $petugas);
    check('damaged/lost return restores no available stock',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[3]]) === 0
        && dbValue($db, 'SELECT status FROM peminjaman WHERE id = ?', [$loanIds[3]]) === 'returned');
    check('verification writes one trigger status audit by Petugas',
        statusAuditCount($db, $loanIds[3]) === 1
        && (int) dbValue($db, "SELECT user_id FROM activity_logs WHERE entity_type = 'peminjaman'
            AND entity_id = ? AND action = 'status_change'", [$loanIds[3]]) === $petugas);

    check('invalid condition is refused before stock moves',
        request($base, 'POST', '/petugas/pengembalian/' . $loanIds[4] . '/verify', [
            '_token' => $petugasToken, 'tanggal_diterima' => '2026-01-08', 'kondisi' => 'rusak',
        ], $petugasJar)['status'] === 422
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[4]]) === 4);
    check('verification before borrow date is refused with 422',
        request($base, 'POST', '/petugas/pengembalian/' . $loanIds[6] . '/verify', [
            '_token' => $petugasToken, 'tanggal_diterima' => '2026-02-28', 'kondisi' => 'baik',
        ], $petugasJar)['status'] === 422
        && dbValue($db, 'SELECT tanggal_diterima FROM pengembalian WHERE peminjaman_id = ?',
            [$loanIds[6]]) === null);
    $onTime = request($base, 'POST', '/petugas/pengembalian/' . $loanIds[4] . '/verify', [
        '_token' => $petugasToken, 'tanggal_diterima' => '2026-01-08', 'kondisi' => 'baik',
    ], $petugasJar);
    $onTimeRow = $db->query('SELECT * FROM pengembalian WHERE peminjaman_id = ' . $loanIds[4])->fetch();
    check('on-time verification has zero late days and fine', $onTime['status'] === 302
        && (int) $onTimeRow['hari_terlambat'] === 0 && (float) $onTimeRow['denda'] === 0.0
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[4]]) === 5);
    $beforeDuplicateAudit = statusAuditCount($db, $loanIds[4]);
    $beforeDuplicateStock = (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[4]]);
    check('duplicate verification is 409',
        request($base, 'POST', '/petugas/pengembalian/' . $loanIds[4] . '/verify', [
            '_token' => $petugasToken, 'tanggal_diterima' => '2026-01-09', 'kondisi' => 'baik',
        ], $petugasJar)['status'] === 409);
    check('duplicate verification adds no stock or audit',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[4]]) === $beforeDuplicateStock
        && statusAuditCount($db, $loanIds[4]) === $beforeDuplicateAudit);

    $db->prepare("UPDATE pengaturan SET nilai = '1000' WHERE kunci = 'tarif_denda'")->execute();
    request($base, 'POST', '/petugas/pengembalian/' . $loanIds[5] . '/verify', [
        '_token' => $petugasToken, 'tanggal_diterima' => '2026-02-08', 'kondisi' => 'rusak_berat',
    ], $petugasJar);
    $late = $db->query('SELECT * FROM pengembalian WHERE peminjaman_id = ' . $loanIds[5])->fetch();
    check('late return applies configured tariff (3 days x 2 units x Rp1.000)', (int) $late['hari_terlambat'] === 3
        && (float) $late['denda'] === 6000.0 && (int) dbValue($db,
            'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[5]]) === 3);
    check('later tariff changes do not rewrite stored fine', (static function () use ($db, $loanIds): bool {
        $db->prepare("UPDATE pengaturan SET nilai = '0' WHERE kunci = 'tarif_denda'")->execute();
        return (float) dbValue($db, 'SELECT denda FROM pengembalian WHERE peminjaman_id = ?', [$loanIds[5]]) === 6000.0;
    })());
    // --- admin correction keeps stock in sync with condition ----------------
    $returnsFix = new App\Models\Pengembalian(
        $db,
        new App\Models\Pengaturan($db, new App\Models\ActivityLog($db))
    );
    check('correction to baik restores the borrowed quantity',
        $returnsFix->adminUpdate($loanIds[5], '2026-02-08', 'baik', $petugas) === 'updated'
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[5]]) === 5);
    check('correction to hilang removes the borrowed quantity again',
        $returnsFix->adminUpdate($loanIds[5], '2026-02-08', 'hilang', $petugas) === 'updated'
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[5]]) === 3);
    $db->prepare('UPDATE alat SET stok_tersedia = stok_total WHERE id = ?')->execute([$tools[5]]);
    check('correction cannot push available stock past the total',
        $returnsFix->adminUpdate($loanIds[5], '2026-02-08', 'baik', $petugas) === 'stock_conflict'
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[5]]) === 5
        && dbValue($db, 'SELECT kondisi FROM pengembalian WHERE peminjaman_id = ?', [$loanIds[5]]) === 'hilang');

    // --- invalid verification input ----------------------------------------
    $service = new App\Services\PengembalianService();
    $parsed = $service->validateVerify(['tanggal_diterima' => '2026-02-30', 'kondisi' => 'rusak']);
    check('invalid date and condition are rejected before writes',
        isset($parsed['errors']['tanggal_diterima']) && isset($parsed['errors']['kondisi']));
    check('status filter falls back to active', $service->statusFilter('bogus') === 'active'
        && $service->statusFilter('returned') === 'returned');

    $returns = new App\Models\Pengembalian(
        $db,
        new App\Models\Pengaturan($db, new App\Models\ActivityLog($db))
    );
    $stockBeforeInvalid = (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[6]]);
    $auditBeforeInvalid = statusAuditCount($db, $loanIds[6]);
    foreach ([
        'before borrow date' => '2026-02-28',
        'before request date' => '2026-03-05',
        'future date' => date('Y-m-d', strtotime('+1 day')),
        'bad format' => 'not-a-date',
    ] as $label => $date) {
        check('verification rejects ' . $label,
            $returns->verify($loanIds[6], $petugas, $date, 'baik') === 'invalid_date');
    }
    $invalidRow = $db->query('SELECT * FROM pengembalian WHERE peminjaman_id = ' . $loanIds[6])->fetch();
    check('invalid dates leave reception, status, stock and audit untouched',
        $invalidRow['tanggal_diterima'] === null && (int) dbValue($db,
            'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[6]]) === $stockBeforeInvalid
        && dbValue($db, 'SELECT status FROM peminjaman WHERE id = ?', [$loanIds[6]]) === 'return_requested'
        && statusAuditCount($db, $loanIds[6]) === $auditBeforeInvalid);

    // --- stock guard rollback ----------------------------------------------
    $rollbackAudit = statusAuditCount($db, $loanIds[7]);
    check('stock overflow verification is 409',
        request($base, 'POST', '/petugas/pengembalian/' . $loanIds[7] . '/verify', [
            '_token' => $petugasToken, 'tanggal_diterima' => '2026-04-12', 'kondisi' => 'baik',
        ], $petugasJar)['status'] === 409);
    $rollbackRow = $db->query('SELECT * FROM pengembalian WHERE peminjaman_id = ' . $loanIds[7])->fetch();
    check('stock overflow rolls back reception, status, stock and audit',
        $rollbackRow['tanggal_diterima'] === null && (int) dbValue($db,
            'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[7]]) === 5
        && dbValue($db, 'SELECT status FROM peminjaman WHERE id = ?', [$loanIds[7]]) === 'return_requested'
        && statusAuditCount($db, $loanIds[7]) === $rollbackAudit);

    // --- invalid transitions ------------------------------------------------
    check('verify pending loan is 409',
        request($base, 'POST', '/petugas/pengembalian/' . $loanIds[8] . '/verify', [
            '_token' => $petugasToken, 'tanggal_diterima' => '2026-05-09', 'kondisi' => 'baik',
        ], $petugasJar)['status'] === 409);
    check('verify already-returned loan is 409',
        request($base, 'POST', '/petugas/pengembalian/' . $loanIds[9] . '/verify', [
            '_token' => $petugasToken, 'tanggal_diterima' => '2026-06-07', 'kondisi' => 'baik',
        ], $petugasJar)['status'] === 409);
    check('verify unknown loan is 404',
        request($base, 'POST', '/petugas/pengembalian/99999999/verify', [
            '_token' => $petugasToken, 'tanggal_diterima' => '2026-06-07', 'kondisi' => 'baik',
        ], $petugasJar)['status'] === 404);

    // --- concurrent verification: one reception, one stock increment -------
    $petugasToken2 = csrfFrom(request($baseSecond, 'GET', '/petugas/persetujuan', [], $petugasJar2)['body']);
    $codes = postConcurrently([
        ['base' => $base, 'path' => '/petugas/pengembalian/' . $loanIds[12] . '/verify',
         'data' => ['_token' => $petugasToken, 'tanggal_diterima' => '2026-10-09', 'kondisi' => 'baik'],
         'jar' => $petugasJar],
        ['base' => $baseSecond, 'path' => '/petugas/pengembalian/' . $loanIds[12] . '/verify',
         'data' => ['_token' => $petugasToken2, 'tanggal_diterima' => '2026-10-09', 'kondisi' => 'baik'],
         'jar' => $petugasJar2],
    ]);
    sort($codes);
    check('concurrent verification succeeds once and rejects the duplicate', $codes === [302, 409]);
    check('concurrent verification restores stock exactly once',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$tools[12]]) === 5
        && dbValue($db, 'SELECT status FROM peminjaman WHERE id = ?', [$loanIds[12]]) === 'returned'
        && statusAuditCount($db, $loanIds[12]) === 1
        && dbValue($db, 'SELECT tanggal_diterima FROM pengembalian WHERE peminjaman_id = ?',
            [$loanIds[12]]) !== null);

    // --- read models: owner scope, status lanes, search --------------------
    $own = $returns->own($user1, 1);
    $ownStatuses = array_column($own['items'], 'status');
    check('own list is owner-scoped and eligible-status only', $own['total'] > 0
        && !in_array('pending', $ownStatuses, true) && !in_array('rejected', $ownStatuses, true)
        && count(array_filter($own['items'],
            static fn (array $r): bool => (int) $r['id'] === $loanIds[8])) === 0);
    $active = $returns->monitoring('active', 1);
    check('monitoring default lane is approved plus return_requested',
        $active['total'] > 0 && array_diff(array_column($active['items'], 'status'),
            ['approved', 'return_requested']) === []);
    $returnedLane = $returns->monitoring('returned', 1);
    check('monitoring returned lane only shows returned',
        $returnedLane['total'] > 0
        && array_unique(array_column($returnedLane['items'], 'status')) === ['returned']);
    $searched = $returns->monitoring('all', 1, 'IRT-L3');
    check('monitoring search filters by loan code', $searched['total'] === 1
        && (int) $searched['items'][0]['id'] === $loanIds[3]);
    check('monitoring search mismatch is empty', $returns->monitoring('active', 1, 'no-such-irt')['total'] === 0);
    $otherOwn = $returns->own($user2, 1);
    check('other borrower cannot see first borrower loans',
        count(array_filter($otherOwn['items'],
            static fn (array $r): bool => (int) $r['id'] === $loanIds[1])) === 0);

    foreach (glob($tmp . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($tmp);
} finally {
    foreach ([$server, $serverSecond] as $process) {
        if (is_resource($process['resource'])) {
            proc_terminate($process['resource']);
            proc_close($process['resource']);
        }
    }
    $cleanup();
}
printf("%d checks, %d failed\n", $checks, count($failures));
foreach ($failures as $failure) {
    fwrite(STDOUT, "FAIL {$failure}\n");
}
exit($failures === [] ? 0 : 1);
