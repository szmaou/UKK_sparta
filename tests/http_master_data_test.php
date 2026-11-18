<?php
declare(strict_types=1);
// Focused HTTP integration checks for admin kategori + alat CRUD (FR-04, FR-05).
//   php tests/http_master_data_test.php
// Exit 0 = pass, 1 = fail, 2 = skipped (DB not reachable or not disposable).
//
// SAFETY: this test deletes only rows whose names/codes start with ITMD-. It
// refuses to run against anything other than the disposable DB on port 33306
// unless SPARTA_ALLOW_NONDISPOSABLE_DB=1 is set explicitly.
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

// --- fixtures (prefix ITMD- so cleanup can never touch seed/demo rows) -------
$seedCounts = [
    'kategori' => (int) $db->query("SELECT COUNT(*) FROM kategori WHERE nama NOT LIKE 'ITMD%'")->fetchColumn(),
    'alat' => (int) $db->query("SELECT COUNT(*) FROM alat WHERE kode NOT LIKE 'ITMD-%'")->fetchColumn(),
    'peminjaman' => (int) $db->query("SELECT COUNT(*) FROM peminjaman WHERE kode NOT LIKE 'ITMD-%'")->fetchColumn(),
];
$cleanup = static function () use ($db): void {
    $db->exec("DELETE FROM activity_logs WHERE entity_type IN ('kategori','alat') AND entity_id IN (
        SELECT id FROM kategori WHERE nama LIKE 'ITMD%'
        UNION SELECT id FROM alat WHERE kode LIKE 'ITMD-%')");
    $db->exec("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'itmd_%')");
    $db->exec("DELETE FROM peminjaman WHERE kode LIKE 'ITMD-%'");
    $db->exec("DELETE FROM alat WHERE kode LIKE 'ITMD-%'");
    $db->exec("DELETE FROM kategori WHERE nama LIKE 'ITMD%'");
    $db->exec("DELETE FROM users WHERE username LIKE 'itmd_%'");
};
$cleanup();
$password = 'test-secret-123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$insertUser = $db->prepare('INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
foreach (['itmd_admin' => 'admin', 'itmd_petugas' => 'petugas', 'itmd_peminjam' => 'peminjam'] as $username => $role) {
    $insertUser->execute([ucfirst($username), $username, $hash, $role]);
}
$adminId = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itmd_admin'");
$peminjamId = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itmd_peminjam'");
$db->prepare("INSERT INTO kategori (nama, deskripsi) VALUES ('ITMD Elektronik', 'Fixture kategori')")->execute();
$db->prepare("INSERT INTO kategori (nama) VALUES ('ITMD Perkakas')")->execute();
$catElektronik = (int) dbValue($db, "SELECT id FROM kategori WHERE nama = 'ITMD Elektronik'");
$catPerkakas = (int) dbValue($db, "SELECT id FROM kategori WHERE nama = 'ITMD Perkakas'");
// Seed 11 extra rows so pagination has 13 total under the ITMD prefix.
$insertCat = $db->prepare('INSERT INTO kategori (nama) VALUES (?)');
for ($i = 1; $i <= 11; $i++) {
    $insertCat->execute([sprintf('ITMD Paginasi %02d', $i)]);
}
// One tool with 2 of 5 units borrowed; one tool referenced by a loan.
$db->prepare('INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia, is_active) VALUES (?, ?, ?, ?, ?, 1)')
    ->execute(['ITMD-A1', $catElektronik, 'ITMD Proyektor', 5, 3]);
$alatBorrowed = (int) $db->lastInsertId();
$db->prepare('INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia, is_active) VALUES (?, ?, ?, ?, ?, 1)')
    ->execute(['ITMD-A2', $catPerkakas, 'ITMD Bor', 4, 4]);
$alatReferenced = (int) $db->lastInsertId();
$db->prepare("INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo)
    VALUES ('ITMD-PJ1', ?, ?, 2, '2026-11-01', '2026-11-10')")
    ->execute([$peminjamId, $alatReferenced]);

$port = 18080 + random_int(0, 400);
$base = 'http://127.0.0.1:' . $port;
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $root,
    ['DB_HOST' => $host, 'DB_PORT' => $dbPort, 'DB_NAME' => $dbName,
     'DB_USER' => getenv('DB_USER') ?: 'sparta', 'DB_PASSWORD' => getenv('DB_PASSWORD') ?: '',
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
    $tmp = sys_get_temp_dir() . '/sparta-md-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0700, true);
    $adminJar = $tmp . '/admin.cookies';
    $petugasJar = $tmp . '/petugas.cookies';
    $peminjamJar = $tmp . '/peminjam.cookies';

    // --- auth + RBAC ---------------------------------------------------------
    check('anonymous kategori list redirects to login',
        request($base, 'GET', '/admin/kategori', [], $tmp . '/anon.cookies')['status'] === 302);
    login($base, $petugasJar, 'itmd_petugas', $password);
    login($base, $peminjamJar, 'itmd_peminjam', $password);
    login($base, $adminJar, 'itmd_admin', $password);
    check('petugas GET kategori is 403', request($base, 'GET', '/admin/kategori', [], $petugasJar)['status'] === 403);
    check('petugas GET alat is 403', request($base, 'GET', '/admin/alat', [], $petugasJar)['status'] === 403);
    check('peminjam GET alat create is 403', request($base, 'GET', '/admin/alat/create', [], $peminjamJar)['status'] === 403);
    $list = request($base, 'GET', '/admin/kategori', [], $adminJar);
    check('admin GET kategori list is 200', $list['status'] === 200);
    check('list shows fixture category', str_contains($list['body'], 'ITMD Elektronik'));
    $petugasPage = request($base, 'GET', '/admin/kategori', [], $petugasJar);
    check('petugas POST kategori create is 403 before CSRF',
        request($base, 'POST', '/admin/kategori/create', ['nama' => 'ITMD Petugas'], $petugasJar)['status'] === 403);
    check('petugas page leaks no admin form', !str_contains($petugasPage['body'], '/admin/kategori/create'));

    // --- CSRF ----------------------------------------------------------------
    $token = csrfFrom(request($base, 'GET', '/admin/kategori/create', [], $adminJar)['body']);
    check('create form carries csrf token', $token !== '');
    check('create without csrf is 403',
        request($base, 'POST', '/admin/kategori/create', ['nama' => 'ITMD NoCsrf'], $adminJar)['status'] === 403);
    check('create with wrong csrf is 403', request($base, 'POST', '/admin/kategori/create',
        ['_token' => str_repeat('f', 64), 'nama' => 'ITMD NoCsrf'], $adminJar)['status'] === 403);
    check('csrf-rejected insert did not happen', dbValue($db, "SELECT COUNT(*) FROM kategori WHERE nama = 'ITMD NoCsrf'") === 0);

    // --- kategori create / validation ---------------------------------------
    $created = request($base, 'POST', '/admin/kategori/create',
        ['_token' => $token, 'nama' => 'ITMD Kategori Baru', 'deskripsi' => 'Dibuat uji'], $adminJar);
    check('kategori create redirects to list', $created['status'] === 302
        && str_contains($created['headers'], 'Location: /admin/kategori'));
    $newCatId = (int) dbValue($db, "SELECT id FROM kategori WHERE nama = 'ITMD Kategori Baru'");
    check('kategori row inserted', $newCatId > 0);
    check('kategori create is audited', in_array('create', auditRows($db, 'kategori', $newCatId), true));
    $flashList = request($base, 'GET', '/admin/kategori', [], $adminJar);
    check('kategori success flash shows once', str_contains($flashList['body'], 'Kategori berhasil ditambahkan'));
    check('kategori success flash is consumed',
        !str_contains(request($base, 'GET', '/admin/kategori', [], $adminJar)['body'], 'Kategori berhasil ditambahkan'));

    $dupe = request($base, 'POST', '/admin/kategori/create',
        ['_token' => $token, 'nama' => 'ITMD Elektronik'], $adminJar);
    check('duplicate kategori is 422', $dupe['status'] === 422);
    check('duplicate kategori shows friendly error', str_contains($dupe['body'], 'Nama kategori sudah digunakan'));
    check('duplicate kategori not inserted',
        (int) dbValue($db, "SELECT COUNT(*) FROM kategori WHERE nama = 'ITMD Elektronik'") === 1);
    $empty = request($base, 'POST', '/admin/kategori/create', ['_token' => $token, 'nama' => '   '], $adminJar);
    check('empty kategori name is 422', $empty['status'] === 422 && str_contains($empty['body'], 'wajib diisi'));
    $tooLong = request($base, 'POST', '/admin/kategori/create',
        ['_token' => $token, 'nama' => str_repeat('x', 101)], $adminJar);
    check('overlong kategori name is 422', $tooLong['status'] === 422);
    $badDesc = request($base, 'POST', '/admin/kategori/create',
        ['_token' => $token, 'nama' => 'ITMD Desc', 'deskripsi' => str_repeat('y', 1001)], $adminJar);
    check('overlong kategori description is 422', $badDesc['status'] === 422);

    // --- kategori search + pagination ----------------------------------------
    $search = request($base, 'GET', '/admin/kategori?q=ITMD%20Elektronik', [], $adminJar);
    check('kategori search filters rows', $search['status'] === 200
        && str_contains($search['body'], 'ITMD Elektronik')
        && !str_contains($search['body'], 'ITMD Paginasi'));
    $searchNone = request($base, 'GET', '/admin/kategori?q=zzz-no-match', [], $adminJar);
    check('kategori search with no hit is empty state', $searchNone['status'] === 200
        && str_contains($searchNone['body'], 'Kategori tidak ditemukan'));
    $page1 = request($base, 'GET', '/admin/kategori?q=ITMD', [], $adminJar);
    check('kategori page 1 paginates', str_contains($page1['body'], 'Halaman 1 dari 2'));
    $page2 = request($base, 'GET', '/admin/kategori?q=ITMD&page=2', [], $adminJar);
    check('kategori page 2 is 200', $page2['status'] === 200 && str_contains($page2['body'], 'Halaman 2 dari 2'));
    $pageBad = request($base, 'GET', '/admin/kategori?q=ITMD&page=abc', [], $adminJar);
    check('invalid page falls back to 1', $pageBad['status'] === 200
        && str_contains($pageBad['body'], 'Halaman 1 dari 2'));
    $pageHuge = request($base, 'GET', '/admin/kategori?q=ITMD&page=999', [], $adminJar);
    check('page beyond range returns empty table, not error', $pageHuge['status'] === 200);

    // --- kategori edit / delete guard --------------------------------------
    $editForm = request($base, 'GET', '/admin/kategori/' . $newCatId . '/edit', [], $adminJar);
    check('kategori edit form is 200', $editForm['status'] === 200);
    check('kategori edit form prefills name', str_contains($editForm['body'], 'ITMD Kategori Baru'));
    check('kategori edit form includes delete action',
        str_contains($editForm['body'], '/admin/kategori/' . $newCatId . '/delete'));
    $updated = request($base, 'POST', '/admin/kategori/' . $newCatId . '/edit',
        ['_token' => $token, 'nama' => 'ITMD Kategori Ubah', 'deskripsi' => ''], $adminJar);
    check('kategori update redirects', $updated['status'] === 302);
    check('kategori update persisted',
        dbValue($db, 'SELECT nama FROM kategori WHERE id = ?', [$newCatId]) === 'ITMD Kategori Ubah');
    check('kategori update is audited', in_array('update', auditRows($db, 'kategori', $newCatId), true));
    $guard = request($base, 'POST', '/admin/kategori/' . $catElektronik . '/delete', ['_token' => $token], $adminJar);
    check('referenced kategori delete redirects back to edit', $guard['status'] === 302
        && str_contains($guard['headers'], 'Location: /admin/kategori/' . $catElektronik . '/edit'));
    check('referenced kategori survives', dbValue($db, 'SELECT COUNT(*) FROM kategori WHERE id = ?', [$catElektronik]) === 1);
    check('referenced kategori emits notice',
        str_contains(request($base, 'GET', '/admin/kategori/' . $catElektronik . '/edit', [], $adminJar)['body'],
            'masih digunakan oleh alat'));
    $freeCatId = (int) dbValue($db, "SELECT id FROM kategori WHERE nama = 'ITMD Paginasi 11'");
    $deleted = request($base, 'POST', '/admin/kategori/' . $freeCatId . '/delete', ['_token' => $token], $adminJar);
    check('unreferenced kategori delete redirects to list', $deleted['status'] === 302
        && str_contains($deleted['headers'], 'Location: /admin/kategori'));
    check('unreferenced kategori removed', dbValue($db, 'SELECT COUNT(*) FROM kategori WHERE id = ?', [$freeCatId]) === 0);
    check('kategori delete is audited', in_array('delete', auditRows($db, 'kategori', $freeCatId), true));
    check('non-positive kategori id is 404',
        request($base, 'GET', '/admin/kategori/0/edit', [], $adminJar)['status'] === 404);
    check('non-numeric kategori id is 404',
        request($base, 'GET', '/admin/kategori/abc/edit', [], $adminJar)['status'] === 404);
    check('unknown kategori id is 404',
        request($base, 'GET', '/admin/kategori/99999999/edit', [], $adminJar)['status'] === 404);

    // --- alat create / validation -------------------------------------------
    $alatForm = request($base, 'GET', '/admin/alat/create', [], $adminJar);
    check('alat create form is 200', $alatForm['status'] === 200);
    check('alat form lists categories', str_contains($alatForm['body'], 'ITMD Elektronik'));
    $alatCreated = request($base, 'POST', '/admin/alat/create', [
        '_token' => $token, 'kode' => 'ITMD-A3', 'nama' => 'ITMD Gergaji', 'kategori_id' => (string) $catPerkakas,
        'deskripsi' => 'Fixture', 'stok_total' => '6', 'is_active' => '1',
    ], $adminJar);
    check('alat create redirects to list', $alatCreated['status'] === 302
        && str_contains($alatCreated['headers'], 'Location: /admin/alat'));
    $newAlatId = (int) dbValue($db, "SELECT id FROM alat WHERE kode = 'ITMD-A3'");
    check('alat inserted with stok_tersedia = stok_total',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$newAlatId]) === 6);
    check('alat create is audited', in_array('create', auditRows($db, 'alat', $newAlatId), true));
    $alatDupe = request($base, 'POST', '/admin/alat/create', [
        '_token' => $token, 'kode' => 'ITMD-A3', 'nama' => 'ITMD Duplikat', 'kategori_id' => (string) $catPerkakas,
        'stok_total' => '1', 'is_active' => '1',
    ], $adminJar);
    check('duplicate alat kode is 422', $alatDupe['status'] === 422);
    check('duplicate alat kode shows friendly error', str_contains($alatDupe['body'], 'Kode alat sudah digunakan'));
    $badCategory = request($base, 'POST', '/admin/alat/create', [
        '_token' => $token, 'kode' => 'ITMD-A9', 'nama' => 'ITMD Tanpa Kategori', 'kategori_id' => '99999999',
        'stok_total' => '1', 'is_active' => '1',
    ], $adminJar);
    check('unknown kategori rejected', $badCategory['status'] === 422
        && str_contains($badCategory['body'], 'Kategori tidak ditemukan'));
    foreach (['-1' => 'negative', 'abc' => 'non-numeric', '1.5' => 'decimal'] as $value => $label) {
        $badStock = request($base, 'POST', '/admin/alat/create', [
            '_token' => $token, 'kode' => 'ITMD-A9', 'nama' => 'ITMD Stok', 'kategori_id' => (string) $catPerkakas,
            'stok_total' => (string) $value, 'is_active' => '1',
        ], $adminJar);
        check("alat {$label} stok_total rejected", $badStock['status'] === 422
            && str_contains($badStock['body'], 'Stok total harus bilangan bulat'));
    }
    $badStatus = request($base, 'POST', '/admin/alat/create', [
        '_token' => $token, 'kode' => 'ITMD-A9', 'nama' => 'ITMD Status', 'kategori_id' => (string) $catPerkakas,
        'stok_total' => '1', 'is_active' => '2',
    ], $adminJar);
    check('alat is_active must be 0 or 1', $badStatus['status'] === 422
        && str_contains($badStatus['body'], 'Status alat tidak valid'));
    $arrayStatus = request($base, 'POST', '/admin/alat/create', [
        '_token' => $token, 'kode' => 'ITMD-A9', 'nama' => 'ITMD Status Array', 'kategori_id' => (string) $catPerkakas,
        'stok_total' => '1', 'is_active' => ['1'],
    ], $adminJar);
    check('alat is_active array is rejected', $arrayStatus['status'] === 422
        && str_contains($arrayStatus['body'], 'Status alat tidak valid'));
    check('invalid alat attempts inserted nothing',
        (int) dbValue($db, "SELECT COUNT(*) FROM alat WHERE kode = 'ITMD-A9'") === 0);
    $missingStatus = request($base, 'POST', '/admin/alat/create', [
        '_token' => $token, 'kode' => 'ITMD-A8', 'nama' => 'ITMD Default Aktif',
        'kategori_id' => (string) $catPerkakas, 'stok_total' => '1',
    ], $adminJar);
    check('missing status defaults to active on create', $missingStatus['status'] === 302
        && (int) dbValue($db, "SELECT is_active FROM alat WHERE kode = 'ITMD-A8'") === 1);
    check('defaulted status create is audited',
        in_array('create', auditRows($db, 'alat', (int) dbValue($db, "SELECT id FROM alat WHERE kode = 'ITMD-A8'")), true));

    // --- alat search --------------------------------------------------------
    $alatSearch = request($base, 'GET', '/admin/alat?q=ITMD%20Proyektor', [], $adminJar);
    check('alat search by name filters', $alatSearch['status'] === 200
        && str_contains($alatSearch['body'], 'ITMD Proyektor')
        && !str_contains($alatSearch['body'], 'ITMD Bor'));
    $kodeSearch = request($base, 'GET', '/admin/alat?q=ITMD-A2', [], $adminJar);
    check('alat search by kode filters', $kodeSearch['status'] === 200
        && str_contains($kodeSearch['body'], 'ITMD Bor')
        && !str_contains($kodeSearch['body'], 'ITMD Proyektor'));

    // --- alat edit: stock invariant + forgery --------------------------------
    $alatEdit = request($base, 'GET', '/admin/alat/' . $alatBorrowed . '/edit', [], $adminJar);
    check('alat edit form is 200', $alatEdit['status'] === 200);
    check('alat edit form prefills kode', str_contains($alatEdit['body'], 'ITMD-A1'));
    $edited = request($base, 'POST', '/admin/alat/' . $alatBorrowed . '/edit', [
        '_token' => $token, 'kode' => 'ITMD-A1', 'nama' => 'ITMD Proyektor Ubah',
        'kategori_id' => (string) $catElektronik, 'deskripsi' => 'Ubah', 'stok_total' => '7', 'is_active' => '1',
    ], $adminJar);
    check('alat update redirects to list with flash', $edited['status'] === 302
        && str_contains($edited['headers'], 'Location: /admin/alat'));
    check('alat update flash is shown and consumed', str_contains(
        request($base, 'GET', '/admin/alat', [], $adminJar)['body'],
        'Alat berhasil diperbarui'
    ));
    $row = $db->query("SELECT nama, stok_total, stok_tersedia FROM alat WHERE id = {$alatBorrowed}")->fetch();
    check('alat update persisted name', $row['nama'] === 'ITMD Proyektor Ubah');
    check('alat update keeps borrowed amount (2 of 7 -> 5 available)',
        (int) $row['stok_total'] === 7 && (int) $row['stok_tersedia'] === 5);
    check('alat update is audited', in_array('update', auditRows($db, 'alat', $alatBorrowed), true));
    $forged = request($base, 'POST', '/admin/alat/' . $alatBorrowed . '/edit', [
        '_token' => $token, 'kode' => 'ITMD-A1', 'nama' => 'ITMD Proyektor Ubah',
        'kategori_id' => (string) $catElektronik, 'stok_total' => '7', 'stok_tersedia' => '999', 'is_active' => '1',
    ], $adminJar);
    check('forged stok_tersedia request is ignored', $forged['status'] === 302
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alatBorrowed]) === 5);
    $updatesBefore = count(array_filter(auditRows($db, 'alat', $alatBorrowed), static fn ($a) => $a === 'update'));
    $underBorrowed = request($base, 'POST', '/admin/alat/' . $alatBorrowed . '/edit', [
        '_token' => $token, 'kode' => 'ITMD-A1', 'nama' => 'ITMD Proyektor Ubah',
        'kategori_id' => (string) $catElektronik, 'stok_total' => '1', 'is_active' => '1',
    ], $adminJar);
    check('stok_total below borrowed is 422', $underBorrowed['status'] === 422);
    check('stok_total below borrowed message names the amount',
        str_contains($underBorrowed['body'], '2 unit yang sedang dipinjam'));
    $afterFail = $db->query("SELECT stok_total, stok_tersedia FROM alat WHERE id = {$alatBorrowed}")->fetch();
    check('rejected stock edit rolled back', (int) $afterFail['stok_total'] === 7 && (int) $afterFail['stok_tersedia'] === 5);
    check('rejected stock edit rolled back its audit row',
        count(array_filter(auditRows($db, 'alat', $alatBorrowed), static fn ($a) => $a === 'update')) === $updatesBefore);
    // Simulate a concurrent change while the form was open: borrowed amount is
    // re-read under the row lock and the new total must still cover it.
    $db->prepare('UPDATE alat SET stok_total = 10, stok_tersedia = 6 WHERE id = ?')->execute([$alatBorrowed]);
    $recheck = request($base, 'POST', '/admin/alat/' . $alatBorrowed . '/edit', [
        '_token' => $token, 'kode' => 'ITMD-A1', 'nama' => 'ITMD Proyektor Ubah',
        'kategori_id' => (string) $catElektronik, 'stok_total' => '3', 'is_active' => '1',
    ], $adminJar);
    check('locked re-read rejects total below new borrowed amount', $recheck['status'] === 422
        && str_contains($recheck['body'], '4 unit yang sedang dipinjam'));
    $recheckOk = request($base, 'POST', '/admin/alat/' . $alatBorrowed . '/edit', [
        '_token' => $token, 'kode' => 'ITMD-A1', 'nama' => 'ITMD Proyektor Ubah',
        'kategori_id' => (string) $catElektronik, 'stok_total' => '9', 'is_active' => '1',
    ], $adminJar);
    check('locked re-read recomputes availability', $recheckOk['status'] === 302
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alatBorrowed]) === 5);

    // --- alat delete: history is never erased --------------------------------
    $refDelete = request($base, 'POST', '/admin/alat/' . $alatReferenced . '/delete', ['_token' => $token], $adminJar);
    check('referenced alat delete redirects back to edit', $refDelete['status'] === 302
        && str_contains($refDelete['headers'], 'Location: /admin/alat/' . $alatReferenced . '/edit'));
    $refRow = $db->query("SELECT is_active FROM alat WHERE id = {$alatReferenced}")->fetch();
    check('referenced alat kept as row', is_array($refRow));
    check('referenced alat deactivated', (int) $refRow['is_active'] === 0);
    check('referenced alat deactivation is audited', in_array('deactivate', auditRows($db, 'alat', $alatReferenced), true));
    check('peminjaman history preserved',
        (int) dbValue($db, "SELECT COUNT(*) FROM peminjaman WHERE kode = 'ITMD-PJ1'") === 1);
    check('deactivation notice shown', str_contains(
        request($base, 'GET', '/admin/alat/' . $alatReferenced . '/edit', [], $adminJar)['body'],
        'dinonaktifkan agar histori tetap utuh'
    ));
    $freeAlatId = (int) dbValue($db, "SELECT id FROM alat WHERE kode = 'ITMD-A8'");
    $freeDelete = request($base, 'POST', '/admin/alat/' . $freeAlatId . '/delete', ['_token' => $token], $adminJar);
    check('unreferenced alat delete redirects to list', $freeDelete['status'] === 302
        && str_contains($freeDelete['headers'], 'Location: /admin/alat'));
    check('unreferenced alat removed', dbValue($db, 'SELECT COUNT(*) FROM alat WHERE id = ?', [$freeAlatId]) === 0);
    check('alat delete is audited', in_array('delete', auditRows($db, 'alat', $freeAlatId), true));
    check('non-positive alat id is 404', request($base, 'GET', '/admin/alat/0/edit', [], $adminJar)['status'] === 404);
    check('unknown alat id is 404', request($base, 'GET', '/admin/alat/99999999/edit', [], $adminJar)['status'] === 404);

    // --- seed untouched ------------------------------------------------------
    $seedAfter = [
        'kategori' => (int) $db->query("SELECT COUNT(*) FROM kategori WHERE nama NOT LIKE 'ITMD%'")->fetchColumn(),
        'alat' => (int) $db->query("SELECT COUNT(*) FROM alat WHERE kode NOT LIKE 'ITMD-%'")->fetchColumn(),
        'peminjaman' => (int) $db->query("SELECT COUNT(*) FROM peminjaman WHERE kode NOT LIKE 'ITMD-%'")->fetchColumn(),
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
    $cleanup();
}
printf("%d checks, %d failed\n", $checks, count($failures));
foreach ($failures as $failure) {
    fwrite(STDOUT, "FAIL {$failure}\n");
}
exit($failures === [] ? 0 : 1);
