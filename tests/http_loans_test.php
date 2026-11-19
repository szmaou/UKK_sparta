<?php
declare(strict_types=1);
// Focused HTTP integration checks for borrower catalog/requests and Petugas
// approval (FR-09, FR-12, FR-13, BR-02..BR-05, BR-08).
//   DB_PORT=33306 DB_USER=sparta DB_PASSWORD=sparta php tests/http_loans_test.php
// Exit 0 = pass, 1 = fail, 2 = skipped (DB unreachable or not disposable).
//
// SAFETY: fixtures are prefixed ITLN-/itln_ and only those rows are removed.
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

/** Minimal DELIMITER support so the audit trigger under test really exists. */
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
function auditRows(PDO $db, string $entity, int $id): array
{
    $stmt = $db->prepare('SELECT action, user_id, description FROM activity_logs WHERE entity_type = ? AND entity_id = ? ORDER BY id');
    $stmt->execute([$entity, $id]);
    return $stmt->fetchAll();
}
function loanId(PDO $db, int $userId, int $alatId): int
{
    return (int) dbValue(
        $db,
        'SELECT id FROM peminjaman WHERE user_id = ? AND alat_id = ? ORDER BY id DESC LIMIT 1',
        [$userId, $alatId]
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
 * Fire two authenticated POSTs at different single-worker servers so they
 * really overlap in the database.
 *
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
    $ids = 'SELECT p.id FROM peminjaman p JOIN users u ON u.id = p.user_id WHERE u.username LIKE \'itln_%\'';
    $db->exec("DELETE FROM activity_logs WHERE entity_type = 'peminjaman' AND entity_id IN ($ids)");
    $db->exec("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'itln_%')");
    $db->exec("DELETE FROM peminjaman WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'itln_%')");
    $db->exec("DELETE FROM alat WHERE kode LIKE 'ITLN-%'");
    $db->exec("DELETE FROM kategori WHERE nama LIKE 'ITLN %'");
    $db->exec("DELETE FROM users WHERE username LIKE 'itln_%'");
};
$cleanup();
$password = 'test-secret-123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$insertUser = $db->prepare('INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
foreach ([
    'itln_peminjam' => ['Peminjam Satu', 'peminjam'],
    'itln_peminjam2' => ['Peminjam Dua', 'peminjam'],
    'itln_petugas' => ['Petugas Satu', 'petugas'],
    'itln_petugas2' => ['Petugas Dua', 'petugas'],
    'itln_admin' => ['Admin Uji', 'admin'],
] as $username => [$name, $role]) {
    $insertUser->execute([$name, $username, $hash, $role]);
}
$user1 = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itln_peminjam'");
$user2 = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itln_peminjam2'");
$petugas = (int) dbValue($db, "SELECT id FROM users WHERE username = 'itln_petugas'");
$db->prepare("INSERT INTO kategori (nama, deskripsi) VALUES ('ITLN Utama', 'Fixture')")->execute();
$db->prepare("INSERT INTO kategori (nama) VALUES ('ITLN Lain')")->execute();
$catMain = (int) dbValue($db, "SELECT id FROM kategori WHERE nama = 'ITLN Utama'");
$catOther = (int) dbValue($db, "SELECT id FROM kategori WHERE nama = 'ITLN Lain'");
$insertTool = $db->prepare(
    'INSERT INTO alat (kode, kategori_id, nama, stok_total, stok_tersedia, is_active) VALUES (?, ?, ?, ?, ?, ?)'
);
$tools = [
    'A1' => ['ITLN Proyektor', $catMain, 5, 5, 1],
    'A2' => ['ITLN Bor Listrik', $catMain, 5, 5, 1],
    'A3' => ['ITLN Alat Nonaktif', $catMain, 5, 5, 0],
    'A4' => ['ITLN Unit Terakhir', $catMain, 1, 1, 1],
    'A5' => ['ITLN Gergaji', $catMain, 2, 2, 1],
    'A6' => ['ITLN Mesin Bubut', $catMain, 5, 5, 1],
];
for ($i = 7; $i <= 13; $i++) {
    $tools['A' . $i] = ['ITLN Alat Tambahan ' . $i, $catOther, 4, 4, 1];
}
foreach ($tools as $suffix => [$nama, $kategori, $total, $available, $active]) {
    $insertTool->execute(['ITLN-' . $suffix, $kategori, $nama, $total, $available, $active]);
}
$tool = static fn (string $suffix): int => (int) dbValue($db, 'SELECT id FROM alat WHERE kode = ?', ['ITLN-' . $suffix]);
$alat = [];
foreach (array_keys($tools) as $suffix) {
    $alat[$suffix] = $tool($suffix);
}

$env = [
    'DB_HOST' => $host,
    'DB_PORT' => $dbPort,
    'DB_NAME' => $dbName,
    'DB_USER' => getenv('DB_USER') ?: 'root',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ?: '',
    'PATH' => (string) getenv('PATH'),
];
$portMain = 20000 + random_int(0, 4000);
$portSecond = $portMain + 5000;
$base = 'http://127.0.0.1:' . $portMain;
$baseSecond = 'http://127.0.0.1:' . $portSecond;
$server = startServer($root, $portMain, $env);
$serverSecond = startServer($root, $portSecond, $env);
try {
    check('main HTTP server started', waitServer($portMain));
    check('second HTTP server started', waitServer($portSecond));
    $tmp = sys_get_temp_dir() . '/sparta-loans-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0700, true);
    $anonJar = $tmp . '/anon.cookies';
    $jar1 = $tmp . '/peminjam1.cookies';
    $jar2 = $tmp . '/peminjam2.cookies';
    $petugasJar = $tmp . '/petugas1.cookies';
    $petugasJar2 = $tmp . '/petugas2.cookies';
    $adminJar = $tmp . '/admin.cookies';
    login($base, $jar1, 'itln_peminjam', $password);
    login($base, $jar2, 'itln_peminjam2', $password);
    login($base, $petugasJar, 'itln_petugas', $password);
    login($baseSecond, $petugasJar2, 'itln_petugas2', $password);
    login($base, $adminJar, 'itln_admin', $password);

    // --- RBAC ---------------------------------------------------------------
    check('anonymous catalog redirects to login', request($base, 'GET', '/peminjam/alat', [], $anonJar)['status'] === 302);
    check('petugas cannot open borrower catalog',
        request($base, 'GET', '/peminjam/alat', [], $petugasJar)['status'] === 403);
    check('peminjam cannot open approval queue',
        request($base, 'GET', '/petugas/persetujuan', [], $jar1)['status'] === 403);
    check('admin cannot open approval queue',
        request($base, 'GET', '/petugas/persetujuan', [], $adminJar)['status'] === 403);
    check('peminjam cannot POST approval',
        request($base, 'POST', '/petugas/peminjaman/1/approve', ['_token' => str_repeat('a', 64)], $jar1)['status'] === 403);

    // --- catalog: active only, search, category and pagination --------------
    $catalog = request($base, 'GET', '/peminjam/alat', [], $jar1);
    check('catalog is 200', $catalog['status'] === 200);
    check('catalog shows active tool', str_contains($catalog['body'], 'ITLN Alat Tambahan'));
    check('catalog hides inactive tool', !str_contains($catalog['body'], 'ITLN Alat Nonaktif'));
    $search = request($base, 'GET', '/peminjam/alat?q=ITLN-A1', [], $jar1);
    check('catalog search filters by kode', $search['status'] === 200
        && str_contains($search['body'], 'ITLN Proyektor') && !str_contains($search['body'], 'ITLN Mesin Bubut'));
    $filtered = request($base, 'GET', '/peminjam/alat?q=ITLN&kategori_id=' . $catMain, [], $jar1);
    check('catalog category filter excludes other categories', $filtered['status'] === 200
        && str_contains($filtered['body'], 'ITLN Proyektor') && !str_contains($filtered['body'], 'ITLN Alat Tambahan'));
    $pageTwo = request($base, 'GET', '/peminjam/alat?q=ITLN&page=2', [], $jar1);
    check('catalog pagination page 1 has next page', str_contains($catalog['body'], 'Halaman 1 dari 2'));
    check('catalog pagination page 2 is valid', $pageTwo['status'] === 200
        && str_contains($pageTwo['body'], 'Halaman 2 dari 2'));
    check('invalid catalog page falls back to 1',
        str_contains(request($base, 'GET', '/peminjam/alat?page=abc', [], $jar1)['body'], 'Halaman 1 dari'));

    // --- create form --------------------------------------------------------
    $form = request($base, 'GET', '/peminjam/peminjaman/create?alat_id=' . $alat['A1'], [], $jar1);
    $pToken = csrfFrom($form['body']);
    check('create form is 200 with selected tool', $form['status'] === 200
        && str_contains($form['body'], 'ITLN Proyektor') && str_contains($form['body'], 'name="tujuan"'));
    check('inactive tool form is 404',
        request($base, 'GET', '/peminjam/peminjaman/create?alat_id=' . $alat['A3'], [], $jar1)['status'] === 404);
    check('unknown tool form is 404',
        request($base, 'GET', '/peminjam/peminjaman/create?alat_id=99999999', [], $jar1)['status'] === 404);

    // --- submit pending: owner from session, stock untouched -----------------
    $valid = [
        '_token' => $pToken, 'alat_id' => (string) $alat['A1'], 'jumlah' => '2',
        'tanggal_pinjam' => '2026-12-01', 'tanggal_jatuh_tempo' => '2026-12-08',
        'tujuan' => 'Praktik proyektor kelas XI',
    ];
    $created = request($base, 'POST', '/peminjam/peminjaman/create', $valid, $jar1);
    check('valid request redirects to own history', $created['status'] === 302
        && str_contains($created['headers'], 'Location: /peminjam/peminjaman'));
    $loanA1 = loanId($db, $user1, $alat['A1']);
    $row = $db->query("SELECT * FROM peminjaman WHERE id = {$loanA1}")->fetch();
    check('pending row belongs to session user', (int) $row['user_id'] === $user1);
    check('pending row stores purpose as catatan', $row['catatan'] === 'Praktik proyektor kelas XI');
    check('pending status and generated code', $row['status'] === 'pending'
        && preg_match('/^PJM-\d{6}-[0-9A-F]{12}$/', (string) $row['kode']) === 1
        && strlen((string) $row['kode']) <= 30);
    check('pending does not decrement stock',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A1']]) === 5);
    check('create is audited once', count(array_filter(auditRows($db, 'peminjaman', $loanA1),
        static fn (array $a): bool => $a['action'] === 'create')) === 1);
    check('pending insert fires no status audit', count(array_filter(auditRows($db, 'peminjaman', $loanA1),
        static fn (array $a): bool => $a['action'] === 'status_change')) === 0);
    $history = request($base, 'GET', '/peminjam/peminjaman', [], $jar1);
    check('own history shows request and success flash', $history['status'] === 200
        && str_contains($history['body'], (string) $row['kode'])
        && str_contains($history['body'], 'berhasil dikirim'));
    // --- reject quantity above currently available stock -------------------
    $countBeforeExcess = (int) dbValue($db, 'SELECT COUNT(*) FROM peminjaman WHERE user_id = ?', [$user1]);
    $stockBeforeExcess = (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A1']]);
    $excess = request($base, 'POST', '/peminjam/peminjaman/create',
        ['jumlah' => '6'] + $valid, $jar1);
    check('quantity above available stock is rejected with stock message', $excess['status'] === 422
        && str_contains($excess['body'], 'Jumlah melebihi stok tersedia saat ini (5 unit)'));
    check('excess quantity creates no loan or stock change',
        (int) dbValue($db, 'SELECT COUNT(*) FROM peminjaman WHERE user_id = ?', [$user1]) === $countBeforeExcess
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A1']]) === $stockBeforeExcess);

    // --- validation ---------------------------------------------------------
    $before = (int) dbValue($db, 'SELECT COUNT(*) FROM peminjaman WHERE user_id = ?', [$user1]);
    foreach ([
        'zero quantity' => ['jumlah' => '0'],
        'negative quantity' => ['jumlah' => '-2'],
        'decimal quantity' => ['jumlah' => '1.5'],
        'non-numeric quantity' => ['jumlah' => 'abc'],
        'invalid calendar date' => ['tanggal_pinjam' => '2026-02-30'],
        'invalid due date' => ['tanggal_jatuh_tempo' => 'not-a-date'],
        'due before start' => ['tanggal_pinjam' => '2026-12-10', 'tanggal_jatuh_tempo' => '2026-12-09'],
        'empty purpose' => ['tujuan' => '   '],
        'overlong purpose' => ['tujuan' => str_repeat('x', 1001)],
        'inactive tool' => ['alat_id' => (string) $alat['A3']],
    ] as $label => $override) {
        $response = request($base, 'POST', '/peminjam/peminjaman/create', $override + $valid, $jar1);
        check('validation rejects ' . $label, $response['status'] === 422);
    }
    check('missing tool on POST is 404',
        request($base, 'POST', '/peminjam/peminjaman/create', ['alat_id' => '99999999'] + $valid, $jar1)['status'] === 404);
    check('CSRF missing blocks create',
        request($base, 'POST', '/peminjam/peminjaman/create', array_diff_key($valid, ['_token' => true]), $jar1)['status'] === 403);
    check('invalid submissions inserted nothing',
        (int) dbValue($db, 'SELECT COUNT(*) FROM peminjaman WHERE user_id = ?', [$user1]) === $before);

    // Forged user_id must be ignored; this also seeds the inactive-tool case.
    $forged = ['user_id' => (string) $user2, 'alat_id' => (string) $alat['A6'], 'jumlah' => '1'] + $valid;
    $forgedResponse = request($base, 'POST', '/peminjam/peminjaman/create', $forged, $jar1);
    $loanA6 = loanId($db, $user1, $alat['A6']);
    check('forged user_id cannot create for another owner', $forgedResponse['status'] === 302
        && (int) dbValue($db, 'SELECT user_id FROM peminjaman WHERE id = ?', [$loanA6]) === $user1);
    $otherHistory = request($base, 'GET', '/peminjam/peminjaman', [], $jar2);
    check('borrower history is owner-scoped', $otherHistory['status'] === 200
        && !str_contains($otherHistory['body'], (string) $row['kode']));

    // Remaining pending fixtures.
    $pToken2 = csrfFrom(request($base, 'GET', '/peminjam/peminjaman/create?alat_id=' . $alat['A4'], [], $jar2)['body']);
    foreach ([
        ['A2', '2', $jar1, 'Uji stok tidak cukup'],
        ['A5', '1', $jar1, 'Uji penolakan'],
        ['A5', '1', $jar1, 'Uji catatan penolakan terlalu panjang'],
        ['A4', '1', $jar1, 'Uji balapan satu unit'],
        ['A4', '1', $jar2, 'Uji balapan kedua'],
    ] as [$suffix, $jumlah, $jar, $tujuan]) {
        $response = request($base, 'POST', '/peminjam/peminjaman/create', [
            '_token' => $jar === $jar2 ? $pToken2 : $pToken, 'alat_id' => (string) $alat[$suffix], 'jumlah' => $jumlah,
            'tanggal_pinjam' => '2026-12-01', 'tanggal_jatuh_tempo' => '2026-12-08', 'tujuan' => $tujuan,
        ], $jar);
        check('fixture request ' . $suffix . '/' . $tujuan . ' created', $response['status'] === 302);
    }
    $loanA2 = loanId($db, $user1, $alat['A2']);
    $loanA5 = (int) dbValue($db, 'SELECT id FROM peminjaman WHERE user_id = ? AND alat_id = ? AND catatan = ?',
        [$user1, $alat['A5'], 'Uji penolakan']);
    $loanA5long = (int) dbValue($db, 'SELECT id FROM peminjaman WHERE user_id = ? AND alat_id = ? AND catatan = ?',
        [$user1, $alat['A5'], 'Uji catatan penolakan terlalu panjang']);
    $loanA4a = (int) dbValue($db, 'SELECT id FROM peminjaman WHERE user_id = ? AND alat_id = ? AND catatan = ?',
        [$user1, $alat['A4'], 'Uji balapan satu unit']);
    $loanA4b = (int) dbValue($db, 'SELECT id FROM peminjaman WHERE user_id = ? AND alat_id = ? AND catatan = ?',
        [$user2, $alat['A4'], 'Uji balapan kedua']);
    // Four extra pending rows make the queue two pages without more HTTP work.
    $insertPending = $db->prepare(
        'INSERT INTO peminjaman (kode, user_id, alat_id, jumlah, tanggal_pinjam, tanggal_jatuh_tempo, catatan)
         VALUES (?, ?, ?, 1, ?, ?, ?)'
    );
    for ($i = 1; $i <= 4; $i++) {
        $insertPending->execute(['ITLN-PG-' . $i, $user1, $alat['A1'], '2026-12-01', '2026-12-08', 'Paginasi antrean']);
    }

    // --- approval queue -----------------------------------------------------
    $queue = request($base, 'GET', '/petugas/persetujuan', [], $petugasJar);
    $petugasToken = csrfFrom($queue['body']);
    check('approval queue is 200', $queue['status'] === 200);
    check('queue shows pending borrower and stock', str_contains($queue['body'], 'Peminjam Satu')
        && str_contains($queue['body'], 'ITLN Proyektor') && str_contains($queue['body'], 'Stok gudang'));
    check('queue paginates pending rows', str_contains($queue['body'], 'Halaman 1 dari 2')
        && request($base, 'GET', '/petugas/persetujuan?page=2', [], $petugasJar)['status'] === 200);

    // --- reject: optional note, stock untouched, purpose preserved -----------
    $reject = request($base, 'POST', '/petugas/peminjaman/' . $loanA5 . '/reject',
        ['_token' => $petugasToken, 'catatan' => 'Tidak sesuai jadwal praktik'], $petugasJar);
    check('reject redirects to queue', $reject['status'] === 302
        && str_contains($reject['headers'], 'Location: /petugas/persetujuan'));
    $rejected = $db->query("SELECT status, catatan FROM peminjaman WHERE id = {$loanA5}")->fetch();
    check('reject changes only status', $rejected['status'] === 'rejected'
        && $rejected['catatan'] === 'Uji penolakan');
    check('reject leaves stock untouched',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A5']]) === 2);
    $rejectAudit = auditRows($db, 'peminjaman', $loanA5);
    check('reject trigger audit uses acting petugas', count(array_filter($rejectAudit,
        static fn (array $a): bool => $a['action'] === 'status_change' && (int) $a['user_id'] === $petugas)) === 1);
    check('reject note is appended to audit, not catatan', count(array_filter($rejectAudit,
        static fn (array $a): bool => $a['action'] === 'reject_note'
            && $a['description'] === 'Tidak sesuai jadwal praktik')) === 1);
    $beforeStatusCount = count(array_filter($rejectAudit, static fn (array $a): bool => $a['action'] === 'status_change'));
    request($base, 'POST', '/petugas/peminjaman/' . $loanA5 . '/reject',
        ['_token' => $petugasToken, 'catatan' => 'Kedua'], $petugasJar);
    check('second reject is blocked', $db->query("SELECT status FROM peminjaman WHERE id = {$loanA5}")->fetchColumn() === 'rejected'
        && count(array_filter(auditRows($db, 'peminjaman', $loanA5),
            static fn (array $a): bool => $a['action'] === 'status_change')) === $beforeStatusCount);
    request($base, 'POST', '/petugas/peminjaman/' . $loanA5long . '/reject',
        ['_token' => $petugasToken, 'catatan' => str_repeat('z', 1001)], $petugasJar);
    check('overlong rejection note is refused', $db->query("SELECT status FROM peminjaman WHERE id = {$loanA5long}")->fetchColumn() === 'pending'
        && !in_array('reject_note', array_column(auditRows($db, 'peminjaman', $loanA5long), 'action'), true));

    // --- approve once: stock -1, audit once, duplicate blocked --------------
    $approve = request($base, 'POST', '/petugas/peminjaman/' . $loanA1 . '/approve',
        ['_token' => $petugasToken], $petugasJar);
    check('approve redirects to queue', $approve['status'] === 302);
    $approved = $db->query("SELECT status, approved_by, approved_at FROM peminjaman WHERE id = {$loanA1}")->fetch();
    check('approve records decision metadata', $approved['status'] === 'approved'
        && (int) $approved['approved_by'] === $petugas && $approved['approved_at'] !== null);
    check('approve decrements stock exactly once',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A1']]) === 3);
    $approveAudit = auditRows($db, 'peminjaman', $loanA1);
    check('approve writes one trigger status audit only', count(array_filter($approveAudit,
        static fn (array $a): bool => $a['action'] === 'status_change')) === 1);
    request($base, 'POST', '/petugas/peminjaman/' . $loanA1 . '/approve',
        ['_token' => $petugasToken], $petugasJar);
    check('duplicate approve blocked without stock change',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A1']]) === 3
        && $db->query("SELECT status FROM peminjaman WHERE id = {$loanA1}")->fetchColumn() === 'approved'
        && count(array_filter(auditRows($db, 'peminjaman', $loanA1),
            static fn (array $a): bool => $a['action'] === 'status_change')) === 1);
    check('approve unknown id is 404',
        request($base, 'POST', '/petugas/peminjaman/99999999/approve', ['_token' => $petugasToken], $petugasJar)['status'] === 404);
    check('approve non-positive id is 404',
        request($base, 'POST', '/petugas/peminjaman/0/approve', ['_token' => $petugasToken], $petugasJar)['status'] === 404);

    // --- insufficient stock: rollback status, stock and audit ---------------
    $db->prepare('UPDATE alat SET stok_tersedia = 0 WHERE id = ?')->execute([$alat['A2']]);
    request($base, 'POST', '/petugas/peminjaman/' . $loanA2 . '/approve',
        ['_token' => $petugasToken], $petugasJar);
    check('insufficient approval leaves status pending', $db->query("SELECT status FROM peminjaman WHERE id = {$loanA2}")->fetchColumn() === 'pending');
    check('insufficient approval leaves stock unchanged and no status audit',
        (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A2']]) === 0
        && !in_array('status_change', array_column(auditRows($db, 'peminjaman', $loanA2), 'action'), true));
    request($base, 'POST', '/petugas/peminjaman/' . $loanA2 . '/reject',
        ['_token' => $petugasToken], $petugasJar);
    check('reject after failed approval changes status only',
        $db->query("SELECT status FROM peminjaman WHERE id = {$loanA2}")->fetchColumn() === 'rejected'
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A2']]) === 0);

    // --- tool deactivated after submission ----------------------------------
    $db->prepare('UPDATE alat SET is_active = 0 WHERE id = ?')->execute([$alat['A6']]);
    request($base, 'POST', '/petugas/peminjaman/' . $loanA6 . '/approve',
        ['_token' => $petugasToken], $petugasJar);
    check('inactive tool cannot be approved and stock is unchanged',
        $db->query("SELECT status FROM peminjaman WHERE id = {$loanA6}")->fetchColumn() === 'pending'
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A6']]) === 5
        && !in_array('status_change', array_column(auditRows($db, 'peminjaman', $loanA6), 'action'), true));

    // --- concurrent last unit across two server processes -------------------
    $tokenPetugas2 = csrfFrom(request($baseSecond, 'GET', '/petugas/persetujuan', [], $petugasJar2)['body']);
    $codes = postConcurrently([
        ['base' => $base, 'path' => '/petugas/peminjaman/' . $loanA4a . '/approve',
         'data' => ['_token' => $petugasToken], 'jar' => $petugasJar],
        ['base' => $baseSecond, 'path' => '/petugas/peminjaman/' . $loanA4b . '/approve',
         'data' => ['_token' => $tokenPetugas2], 'jar' => $petugasJar2],
    ]);
    $statuses = $db->query(
        "SELECT status FROM peminjaman WHERE id IN ({$loanA4a}, {$loanA4b}) ORDER BY id"
    )->fetchAll(PDO::FETCH_COLUMN);
    check('concurrent approvals both complete', $codes === [302, 302]);
    check('last unit approved exactly once',
        count(array_filter($statuses, static fn (string $s): bool => $s === 'approved')) === 1
        && (int) dbValue($db, 'SELECT stok_tersedia FROM alat WHERE id = ?', [$alat['A4']]) === 0);

    // --- seed guard ---------------------------------------------------------
    $remaining = (int) dbValue($db, "SELECT COUNT(*) FROM peminjaman WHERE kode LIKE 'ITLN-PG-%'");
    check('fixture rows still identifiable for cleanup', $remaining === 4);
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
