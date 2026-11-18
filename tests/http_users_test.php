<?php

declare(strict_types=1);

// Focused HTTP integration checks for admin user CRUD (FR-03).
//   DB_PORT=33306 DB_PASSWORD=sparta php tests/http_users_test.php
// Exit 0 = pass, 1 = fail, 2 = skipped (DB not reachable or not disposable).
//
// SAFETY: fixtures use the ITUS-/itus_ prefix so cleanup only touches test
// rows. Refuses the real local DB on port 3306 unless explicitly allowed.
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
    fwrite(STDERR, "Use DB_PORT=33306 (disposable DB) or set SPARTA_ALLOW_NONDISPOSABLE_DB=1.\n");
    exit(2);
}
require_once $root . '/app/autoload.php';
$db = $container['db'];
$db->exec((string) file_get_contents($root . '/database/schema.sql'));
$userModel = new App\Models\User($db, new App\Models\ActivityLog($db));

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
    $stmt = $db->prepare('SELECT action FROM activity_logs WHERE entity_type = ? AND entity_id = ? ORDER BY id');
    $stmt->execute([$entity, $id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$cleanup = static function () use ($db): void {
    $db->exec("DELETE FROM activity_logs WHERE entity_type = 'user' AND entity_id IN (
        SELECT id FROM users WHERE username LIKE 'itus\\_%')");
    $db->exec("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'itus\\_%')");
    $db->exec("DELETE FROM peminjaman WHERE kode LIKE 'ITUS-%'");
    $db->exec("DELETE FROM alat WHERE kode LIKE 'ITUS-%'");
    $db->exec("DELETE FROM kategori WHERE nama LIKE 'ITUS-%'");
    $db->exec("DELETE FROM users WHERE username LIKE 'itus\\_%'");
};
$cleanup();

$seedCounts = [
    'users' => (int) $db->query("SELECT COUNT(*) FROM users WHERE username NOT LIKE 'itus\\_%'")->fetchColumn(),
    'peminjaman' => (int) $db->query("SELECT COUNT(*) FROM peminjaman WHERE kode NOT LIKE 'ITUS-%'")->fetchColumn(),
];
$password = 'test-secret-123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$insertUser = $db->prepare('INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
$fixtures = [
    'itus_admin' => ['ITUS Admin', 'admin'],
    'itus_admin2' => ['ITUS Admin 2', 'admin'],
    'itus_petugas' => ['ITUS Petugas', 'petugas'],
    'itus_peminjam' => ['ITUS Peminjam', 'peminjam'],
];
for ($i = 1; $i <= 9; $i++) {
    $fixtures[sprintf('itus_page%02d', $i)] = [sprintf('ITUS Page %02d', $i), 'peminjam'];
}
foreach ($fixtures as $username => [$name, $role]) {
    $insertUser->execute([$name, $username, $hash, $role]);
}
$adminId = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itus_admin'");
$admin2Id = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itus_admin2'");
$petugasId = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itus_petugas'");
$peminjamId = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itus_peminjam'");
$db->prepare("INSERT INTO kategori (nama) VALUES ('ITUS-Kategori')")->execute();
$kategoriId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia, is_active)
    VALUES ('ITUS-A1', ?, 'ITUS Alat', 2, 2, 1)")->execute([$kategoriId]);
$alatId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo)
    VALUES ('ITUS-PJ1', ?, ?, 1, '2026-11-01', '2026-11-10')")->execute([$peminjamId, $alatId]);

$foreignAdmins = $db->query(
    "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 AND username NOT LIKE 'itus\\_%'"
)->fetchAll(PDO::FETCH_COLUMN);
$port = 18600 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $port;
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $root,
    ['DB_HOST' => $host, 'DB_PORT' => $dbPort, 'DB_NAME' => $dbName,
     'DB_USER' => getenv('DB_USER') ?: 'sparta', 'DB_PASSWORD' => getenv('DB_PASSWORD') ?: 'sparta',
     'PATH' => (string) getenv('PATH')]
);
try {
    $ready = false;
    for ($i = 0; $i < 100 && !$ready; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if (is_resource($probe)) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(50000);
    }
    check('built-in server started', $ready);
    $tmp = sys_get_temp_dir() . '/sparta-users-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0700, true);
    $adminJar = $tmp . '/admin.cookies';
    $petugasJar = $tmp . '/petugas.cookies';
    $userJar = $tmp . '/user.cookies';

    // --- auth + RBAC ---------------------------------------------------------
    check('anonymous users list redirects to login',
        request($base, 'GET', '/admin/users', [], $tmp . '/anon.cookies')['status'] === 302);
    login($base, $petugasJar, 'itus_petugas', $password);
    login($base, $adminJar, 'itus_admin', $password);
    login($base, $userJar, 'itus_peminjam', $password);
    check('petugas GET users list is 403', request($base, 'GET', '/admin/users', [], $petugasJar)['status'] === 403);
    check('petugas GET users create is 403',
        request($base, 'GET', '/admin/users/create', [], $petugasJar)['status'] === 403);
    check('petugas POST users create is 403 before CSRF',
        request($base, 'POST', '/admin/users/create', ['name' => 'ITUS Nope'], $petugasJar)['status'] === 403);
    $list = request($base, 'GET', '/admin/users', [], $adminJar);
    check('admin GET users list is 200', $list['status'] === 200);
    check('list shows fixture account', str_contains($list['body'], 'itus_admin'));

    // --- CSRF ----------------------------------------------------------------
    $token = csrfFrom(request($base, 'GET', '/admin/users/create', [], $adminJar)['body']);
    check('create form carries csrf token', $token !== '');
    check('create without csrf is 403',
        request($base, 'POST', '/admin/users/create', ['name' => 'ITUS NoCsrf'], $adminJar)['status'] === 403);
    check('create with wrong csrf is 403', request($base, 'POST', '/admin/users/create',
        ['_token' => str_repeat('f', 64), 'name' => 'ITUS NoCsrf'], $adminJar)['status'] === 403);
    check('csrf-rejected user was not inserted',
        dbValue($db, "SELECT COUNT(*) FROM users WHERE username LIKE 'itus\\_%'") === 13);

    // --- create --------------------------------------------------------------
    $created = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Baru', 'username' => 'itus_new', 'password' => 'secret-123',
        'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    check('user create redirects to list', $created['status'] === 302
        && str_contains($created['headers'], 'Location: /admin/users'));
    $newId = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itus_new'");
    $newHash = (string) dbValue($db, 'SELECT password_hash FROM users WHERE id = ?', [$newId]);
    check('user row inserted active with role', $newId > 0
        && dbValue($db, 'SELECT role FROM users WHERE id = ?', [$newId]) === 'petugas'
        && (int) dbValue($db, 'SELECT is_active FROM users WHERE id = ?', [$newId]) === 1);
    check('password stored as verifiable hash', $newHash !== 'secret-123' && password_verify('secret-123', $newHash));
    check('user create is audited', in_array('create', auditRows($db, 'user', $newId), true));
    check('plaintext password never audited',
        (int) dbValue($db, "SELECT COUNT(*) FROM activity_logs WHERE description LIKE '%secret-123%'") === 0);
    $search = request($base, 'GET', '/admin/users?q=itus_new', [], $adminJar);
    check('user search filters rows', $search['status'] === 200
        && str_contains($search['body'], 'itus_new')
        && !str_contains($search['body'], 'itus_page01'));
    check('list never renders a password',
        !str_contains($search['body'], 'secret-123') && !str_contains($search['body'], $newHash));

    // --- validation ----------------------------------------------------------
    $dupe = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Dupe', 'username' => 'itus_admin', 'password' => 'secret-123',
        'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    check('duplicate username is 422', $dupe['status'] === 422
        && str_contains($dupe['body'], 'Username sudah digunakan'));
    $short = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Short', 'username' => 'itus_short', 'password' => '1234567',
        'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    check('password shorter than 8 is 422', $short['status'] === 422
        && dbValue($db, "SELECT COUNT(*) FROM users WHERE username = 'itus_short'") === 0);
    $long = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Long', 'username' => 'itus_long', 'password' => str_repeat('a', 73),
        'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    check('password over 72 bytes is 422', $long['status'] === 422
        && str_contains($long['body'], '72 byte'));
    $badRole = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Role', 'username' => 'itus_badrole', 'password' => 'secret-123',
        'role' => 'root', 'is_active' => '1',
    ], $adminJar);
    check('unknown role is 422', $badRole['status'] === 422
        && str_contains($badRole['body'], 'Role pengguna tidak valid'));
    $badStatus = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Status', 'username' => 'itus_badstatus', 'password' => 'secret-123',
        'role' => 'petugas', 'is_active' => ['1'],
    ], $adminJar);
    check('array status is 422', $badStatus['status'] === 422
        && str_contains($badStatus['body'], 'Status pengguna tidak valid'));
    $badName = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => str_repeat('x', 101), 'username' => 'itus_longname',
        'password' => 'secret-123', 'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    $badUsername = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Long Username', 'username' => str_repeat('u', 51),
        'password' => 'secret-123', 'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    check('overlong name and username are 422', $badName['status'] === 422 && $badUsername['status'] === 422);
    check('invalid create attempts inserted nothing',
        (int) dbValue($db, "SELECT COUNT(*) FROM users WHERE username LIKE 'itus\\_%'") === 14);
    $defaulted = request($base, 'POST', '/admin/users/create', [
        '_token' => $token, 'name' => 'ITUS Default', 'username' => 'itus_default',
        'password' => 'secret-123', 'role' => 'peminjam',
    ], $adminJar);
    check('missing status defaults to active', $defaulted['status'] === 302
        && (int) dbValue($db, "SELECT is_active FROM users WHERE username = 'itus_default'") === 1);
    $defaultId = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itus_default'");

    // --- search + pagination (model, independent of view copy) ----------------
    $page1 = $userModel->paginate('itus_', 1);
    $page2 = $userModel->paginate('itus_', 2);
    check('search paginates 10 rows across 2 pages', $page1['total'] === 15
        && $page1['totalPages'] === 2 && count($page1['items']) === 10 && count($page2['items']) === 5);
    check('list rows expose contract fields and never a hash',
        array_keys($page1['items'][0]) === ['id', 'name', 'username', 'role', 'is_active', 'created_at']);

    // --- edit: hash preservation, replace, no echo --------------------------
    $editForm = request($base, 'GET', '/admin/users/' . $newId . '/edit', [], $adminJar);
    check('edit form is 200 and prefills username', $editForm['status'] === 200
        && str_contains($editForm['body'], 'itus_new'));
    check('edit form never echoes hash or password',
        !str_contains($editForm['body'], $newHash) && !str_contains($editForm['body'], 'secret-123'));
    $edited = request($base, 'POST', '/admin/users/' . $newId . '/edit', [
        '_token' => $token, 'name' => 'ITUS Baru Ubah', 'username' => 'itus_new', 'password' => '',
        'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    check('empty edit password preserves hash', $edited['status'] === 302
        && dbValue($db, 'SELECT password_hash FROM users WHERE id = ?', [$newId]) === $newHash);
    check('user update persisted and audited',
        dbValue($db, 'SELECT name FROM users WHERE id = ?', [$newId]) === 'ITUS Baru Ubah'
        && in_array('update', auditRows($db, 'user', $newId), true));
    $rehashed = request($base, 'POST', '/admin/users/' . $newId . '/edit', [
        '_token' => $token, 'name' => 'ITUS Baru Ubah', 'username' => 'itus_new', 'password' => 'new-secret-456',
        'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    $newHash2 = (string) dbValue($db, 'SELECT password_hash FROM users WHERE id = ?', [$newId]);
    check('non-empty edit password rotates hash', $rehashed['status'] === 302
        && $newHash2 !== $newHash && password_verify('new-secret-456', $newHash2));
    check('password change audit hides the secret',
        (int) dbValue($db, "SELECT COUNT(*) FROM activity_logs WHERE description LIKE '%new-secret-456%'") === 0);

    // --- current-admin guards ------------------------------------------------
    $adminUpdates = count(array_filter(auditRows($db, 'user', $adminId), static fn ($a) => $a === 'update'));
    $selfRole = request($base, 'POST', '/admin/users/' . $adminId . '/edit', [
        '_token' => $token, 'name' => 'ITUS Admin', 'username' => 'itus_admin', 'password' => '',
        'role' => 'petugas', 'is_active' => '1',
    ], $adminJar);
    check('self role change is rejected', $selfRole['status'] === 422
        && dbValue($db, 'SELECT role FROM users WHERE id = ?', [$adminId]) === 'admin');
    $selfOff = request($base, 'POST', '/admin/users/' . $adminId . '/edit', [
        '_token' => $token, 'name' => 'ITUS Admin', 'username' => 'itus_admin', 'password' => '',
        'role' => 'admin', 'is_active' => '0',
    ], $adminJar);
    check('self deactivation is rejected', $selfOff['status'] === 422
        && (int) dbValue($db, 'SELECT is_active FROM users WHERE id = ?', [$adminId]) === 1);
    $selfDelete = request($base, 'POST', '/admin/users/' . $adminId . '/delete', ['_token' => $token], $adminJar);
    check('self delete redirects back without deactivating', $selfDelete['status'] === 302
        && str_contains($selfDelete['headers'], 'Location: /admin/users/' . $adminId . '/edit')
        && (int) dbValue($db, 'SELECT is_active FROM users WHERE id = ?', [$adminId]) === 1);
    check('rejected self guards rolled back their audit rows',
        count(array_filter(auditRows($db, 'user', $adminId), static fn ($a) => $a === 'update')) === $adminUpdates
        && !in_array('deactivate', auditRows($db, 'user', $adminId), true));

    // --- delete = deactivate, session invalidated, history kept -------------
    check('peminjam session works before deactivation',
        request($base, 'GET', '/peminjam/dashboard', [], $userJar)['status'] === 200);
    $deleted = request($base, 'POST', '/admin/users/' . $peminjamId . '/delete', ['_token' => $token], $adminJar);
    check('user delete redirects to list', $deleted['status'] === 302
        && str_contains($deleted['headers'], 'Location: /admin/users'));
    check('user delete keeps row and deactivates it',
        (int) dbValue($db, 'SELECT COUNT(*) FROM users WHERE id = ?', [$peminjamId]) === 1
        && (int) dbValue($db, 'SELECT is_active FROM users WHERE id = ?', [$peminjamId]) === 0);
    check('user deactivation is audited', in_array('deactivate', auditRows($db, 'user', $peminjamId), true));
    check('loan history preserved after account deactivation',
        (int) dbValue($db, "SELECT COUNT(*) FROM peminjaman WHERE kode = 'ITUS-PJ1'") === 1);
    check('deactivated account loses its session',
        request($base, 'GET', '/peminjam/dashboard', [], $userJar)['status'] === 302);
    check('flash names deactivation', str_contains(
        request($base, 'GET', '/admin/users', [], $adminJar)['body'],
        'dinonaktifkan'
    ));

    // --- malformed reactivation + bad IDs -----------------------------------
    $reactivate = request($base, 'POST', '/admin/users/' . $peminjamId . '/edit', [
        '_token' => $token, 'name' => 'ITUS Peminjam', 'username' => 'itus_peminjam', 'password' => '',
        'role' => 'peminjam', 'is_active' => ['1'],
    ], $adminJar);
    check('array is_active cannot reactivate', $reactivate['status'] === 422
        && (int) dbValue($db, 'SELECT is_active FROM users WHERE id = ?', [$peminjamId]) === 0);
    $reactivateBad = request($base, 'POST', '/admin/users/' . $peminjamId . '/edit', [
        '_token' => $token, 'name' => 'ITUS Peminjam', 'username' => 'itus_peminjam', 'password' => '',
        'role' => 'peminjam', 'is_active' => '2',
    ], $adminJar);
    check('invalid is_active cannot reactivate', $reactivateBad['status'] === 422
        && (int) dbValue($db, 'SELECT is_active FROM users WHERE id = ?', [$peminjamId]) === 0);
    check('non-positive user id is 404', request($base, 'GET', '/admin/users/0/edit', [], $adminJar)['status'] === 404);
    check('non-numeric user id is 404', request($base, 'GET', '/admin/users/abc/edit', [], $adminJar)['status'] === 404);
    check('unknown user id is 404',
        request($base, 'GET', '/admin/users/99999999/edit', [], $adminJar)['status'] === 404);
    check('delete unknown user id is 404',
        request($base, 'POST', '/admin/users/99999999/delete', ['_token' => $token], $adminJar)['status'] === 404);

    // --- last active admin guard (direct model, actor is not the target) ----
    $db->prepare("UPDATE users SET is_active = 0 WHERE role = 'admin' AND is_active = 1 AND id <> ?")
        ->execute([$adminId]);
    $adminUpdates = count(array_filter(auditRows($db, 'user', $adminId), static fn ($a) => $a === 'update'));
    $demote = $userModel->parse(
        ['name' => 'ITUS Admin', 'username' => 'itus_admin', 'role' => 'petugas', 'is_active' => '1'],
        $adminId
    );
    $lastRejected = false;
    try {
        $userModel->update($adminId, $demote['values'], '', $petugasId);
    } catch (DomainException $exception) {
        $lastRejected = str_contains($exception->getMessage(), 'Admin aktif terakhir');
    }
    check('last active admin cannot be demoted', $lastRejected
        && dbValue($db, 'SELECT role FROM users WHERE id = ?', [$adminId]) === 'admin');
    $deleteRejected = false;
    try {
        $userModel->delete($adminId, $petugasId);
    } catch (DomainException $exception) {
        $deleteRejected = str_contains($exception->getMessage(), 'Admin aktif terakhir');
    }
    check('last active admin cannot be deactivated', $deleteRejected
        && (int) dbValue($db, 'SELECT is_active FROM users WHERE id = ?', [$adminId]) === 1);
    check('last-admin rejection rolled back audit rows',
        count(array_filter(auditRows($db, 'user', $adminId), static fn ($a) => $a === 'update')) === $adminUpdates
        && !in_array('deactivate', auditRows($db, 'user', $adminId), true));

    // --- seed untouched ------------------------------------------------------
    $seedAfter = [
        'users' => (int) $db->query("SELECT COUNT(*) FROM users WHERE username NOT LIKE 'itus\\_%'")->fetchColumn(),
        'peminjaman' => (int) $db->query("SELECT COUNT(*) FROM peminjaman WHERE kode NOT LIKE 'ITUS-%'")->fetchColumn(),
    ];
    check('seed rows preserved', $seedAfter === $seedCounts);

    foreach (glob($tmp . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($tmp);
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    if ($foreignAdmins !== []) {
        $placeholders = implode(',', array_fill(0, count($foreignAdmins), '?'));
        $db->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)")
            ->execute(array_map('intval', $foreignAdmins));
    }
    $cleanup();
}
printf("%d checks, %d failed\n", $checks, count($failures));
foreach ($failures as $failure) {
    fwrite(STDOUT, "FAIL {$failure}\n");
}
exit($failures === [] ? 0 : 1);
