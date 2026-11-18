<?php

declare(strict_types=1);

// HTTP integration checks for auth + RBAC. Boots the PHP built-in server with
// public/ as document root and drives it with curl.
//   php tests/http_auth_test.php
// Exit 0 = pass, 1 = fail, 2 = skipped (MariaDB unreachable).
//
// The test creates its own users, so point DB_* at a disposable database.
$root = dirname(__DIR__);

try {
    $container = require $root . '/bootstrap/app.php';
    $container['db']->query('SELECT 1');
} catch (Throwable $exception) {
    fwrite(STDERR, 'SKIP: MariaDB not reachable: ' . $exception->getMessage() . "\n");
    exit(2);
}

require_once $root . '/app/autoload.php';

$db = $container['db'];
$db->exec((string) file_get_contents($root . '/database/schema.sql'));

$password = 'test-secret-123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// The HTTP server runs in another process, so fixtures must be committed to be
// visible. Usernames are fixed: cleanup deletes exactly these accounts (and
// their logs) before and after the run.
$accounts = [
    'it_admin' => ['admin', 1],
    'it_petugas' => ['petugas', 1],
    'it_peminjam' => ['peminjam', 1],
    'it_inactive' => ['admin', 0],
];
$usernames = array_keys($accounts);
$in = implode(',', array_fill(0, count($usernames), '?'));
$db->prepare("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE username IN ($in))")
    ->execute($usernames);
$db->prepare("DELETE FROM users WHERE username IN ($in)")->execute($usernames);

$insertUser = $db->prepare(
    'INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)'
);
foreach ($accounts as $username => [$role, $active]) {
    $insertUser->execute([ucfirst($username), $username, $hash, $role, $active]);
}

$port = 18080 + random_int(0, 400);
$base = 'http://127.0.0.1:' . $port;
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $root,
    ['DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1', 'DB_PORT' => getenv('DB_PORT') ?: '3306',
     'DB_NAME' => getenv('DB_NAME') ?: 'sparta', 'DB_USER' => getenv('DB_USER') ?: 'root',
     'DB_PASSWORD' => getenv('DB_PASSWORD') ?: '', 'PATH' => (string) getenv('PATH')]
);

$failures = [];
$checks = 0;
function check(string $label, bool $ok): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $label;
    }
}

/** @return array{status: int, headers: string, body: string} */
function request(string $base, string $method, string $path, array $post = [], ?string $cookieJar = null): array
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
    if ($cookieJar !== null) {
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieJar);
    }

    $raw = (string) curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

function csrfFrom(string $html): string
{
    return preg_match('/name="_token" value="([0-9a-f]{64})"/', $html, $m) === 1 ? $m[1] : '';
}

function login(string $base, string $jar, string $username, string $password): array
{
    $loginPage = request($base, 'GET', '/login', [], $jar);
    $token = csrfFrom($loginPage['body']);

    return request($base, 'POST', '/login', [
        '_token' => $token,
        'username' => $username,
        'password' => $password,
    ], $jar);
}

try {
    // Wait for the server to accept connections (bounded retry, no fixed sleep).
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

    $tmp = sys_get_temp_dir() . '/sparta-it-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0700, true);
    $anonJar = $tmp . '/anon.cookies';
    $adminJar = $tmp . '/admin.cookies';
    $petugasJar = $tmp . '/petugas.cookies';
    $peminjamJar = $tmp . '/peminjam.cookies';

    // --- anonymous ---
    $loginPage = request($base, 'GET', '/login', [], $anonJar);
    check('GET /login is 200', $loginPage['status'] === 200);
    check('login page has forms', str_contains($loginPage['body'], 'action="/login"'));
    check('login page has csrf token', csrfFrom($loginPage['body']) !== '');
    check('login sets httponly cookie', preg_match('/Set-Cookie:.*HttpOnly/i', $loginPage['headers']) === 1);

    $rootRedirect = request($base, 'GET', '/', [], $anonJar);
    check('GET / redirects anonymous to /login', $rootRedirect['status'] === 302
        && str_contains($rootRedirect['headers'], 'Location: /login'));

    foreach (['admin', 'petugas', 'peminjam'] as $role) {
        $protected = request($base, 'GET', '/' . $role . '/dashboard', [], $anonJar);
        check("anonymous /{$role}/dashboard redirects to /login", $protected['status'] === 302
            && str_contains($protected['headers'], 'Location: /login'));
    }

    // --- CSRF ---
    $noToken = request($base, 'POST', '/login', ['username' => 'it_admin', 'password' => $password]);
    check('POST /login without csrf is 403', $noToken['status'] === 403);

    $badToken = request($base, 'POST', '/login', [
        '_token' => str_repeat('f', 64), 'username' => 'it_admin', 'password' => $password,
    ]);
    check('POST /login with wrong csrf is 403', $badToken['status'] === 403);

    // --- credentials ---
    $wrong = login($base, $anonJar, 'it_admin', 'wrong-password');
    check('wrong password is rejected', $wrong['status'] === 401);
    check('wrong password shows generic error', str_contains($wrong['body'], 'Username atau kata sandi tidak sesuai'));
    check('wrong password leaks no account state', !str_contains($wrong['body'], 'password_hash'));

    $inactive = login($base, $anonJar, 'it_inactive', $password);
    check('inactive account is rejected', $inactive['status'] === 401);
    check('inactive error matches generic error', str_contains($inactive['body'], 'Username atau kata sandi tidak sesuai'));

    $unknown = login($base, $anonJar, 'nobody-here', $password);
    check('unknown username is rejected', $unknown['status'] === 401);
    check('unknown error matches generic error', str_contains($unknown['body'], 'Username atau kata sandi tidak sesuai'));

    // --- role logins + dashboards ---
    $admin = login($base, $adminJar, 'it_admin', $password);
    check('admin login redirects to admin dashboard', $admin['status'] === 302
        && str_contains($admin['headers'], 'Location: /admin/dashboard'));

    $adminDashboard = request($base, 'GET', '/admin/dashboard', [], $adminJar);
    check('admin dashboard is 200', $adminDashboard['status'] === 200);
    check('admin dashboard greets user', str_contains($adminDashboard['body'], 'It_admin'));

    $adminHome = request($base, 'GET', '/', [], $adminJar);
    check('GET / sends admin to own dashboard', $adminHome['status'] === 302
        && str_contains($adminHome['headers'], 'Location: /admin/dashboard'));

    $adminOnPetugas = request($base, 'GET', '/petugas/dashboard', [], $adminJar);
    check('admin on petugas dashboard is 403', $adminOnPetugas['status'] === 403);
    check('403 page shows denial, not petugas content', str_contains($adminOnPetugas['body'], 'Akses ditolak')
        && !str_contains($adminOnPetugas['body'], 'Ruang kerja Petugas'));

    $petugas = login($base, $petugasJar, 'it_petugas', $password);
    check('petugas login redirects to petugas dashboard', $petugas['status'] === 302
        && str_contains($petugas['headers'], 'Location: /petugas/dashboard'));
    check('petugas dashboard is 200', request($base, 'GET', '/petugas/dashboard', [], $petugasJar)['status'] === 200);

    $peminjam = login($base, $peminjamJar, 'it_peminjam', $password);
    check('peminjam login redirects to peminjam dashboard', $peminjam['status'] === 302
        && str_contains($peminjam['headers'], 'Location: /peminjam/dashboard'));
    check('peminjam dashboard is 200', request($base, 'GET', '/peminjam/dashboard', [], $peminjamJar)['status'] === 200);

    $peminjamOnAdmin = request($base, 'GET', '/admin/dashboard', [], $peminjamJar);
    check('peminjam on admin dashboard is 403', $peminjamOnAdmin['status'] === 403);

    $adminLogoutByGet = request($base, 'GET', '/logout', [], $adminJar);
    check('GET /logout is not a route', $adminLogoutByGet['status'] === 405);

    // --- session fixation: the pre-login cookie must not become authenticated ---
    $fixedJar = $tmp . '/fixed.cookies';
    $oldJar = $tmp . '/old.cookies';
    request($base, 'GET', '/login', [], $fixedJar);
    copy($fixedJar, $oldJar);
    $preLoginId = preg_match('/SPARTASESS\s+([0-9a-f]+)/', (string) @file_get_contents($fixedJar), $m) === 1 ? $m[1] : '';
    login($base, $fixedJar, 'it_peminjam', $password);
    $postLoginId = preg_match('/SPARTASESS\s+([0-9a-f]+)/', (string) @file_get_contents($fixedJar), $m) === 1 ? $m[1] : '';
    check('session id changes after login', $preLoginId !== '' && $postLoginId !== '' && $preLoginId !== $postLoginId);
    check('pre-login cookie is not authenticated',
        request($base, 'GET', '/peminjam/dashboard', [], $oldJar)['status'] === 302);

    // --- deactivated account loses access on the next request ---
    $db->prepare("UPDATE users SET is_active = 0 WHERE username = 'it_petugas'")->execute();
    check('deactivated user is rejected mid-session',
        request($base, 'GET', '/petugas/dashboard', [], $petugasJar)['status'] === 302);
    $db->prepare("UPDATE users SET is_active = 1 WHERE username = 'it_petugas'")->execute();

    // --- logout requires CSRF even for a logged-in user ---
    $csrfLogoutJar = $tmp . '/csrf-logout.cookies';
    login($base, $csrfLogoutJar, 'it_peminjam', $password);
    $noCsrfLogout = request($base, 'POST', '/logout', [], $csrfLogoutJar);
    check('authenticated logout without csrf is 403', $noCsrfLogout['status'] === 403);
    check('csrf-rejected logout keeps the session',
        request($base, 'GET', '/peminjam/dashboard', [], $csrfLogoutJar)['status'] === 200);

    // --- private paths are unreachable over HTTP ---
    foreach (['/config/database.php', '/bootstrap/app.php', '/database/seeders/seed.php', '/app/routes.php'] as $privatePath) {
        $probe = request($base, 'GET', $privatePath);
        check("private path {$privatePath} is not served", $probe['status'] === 404
            && !str_contains($probe['body'], 'password_hash')
            && !str_contains($probe['body'], 'DB_PASSWORD'));
    }

    // --- seeded demo admin still accepts its documented password ---
    $seeded = $db->query("SELECT id FROM users WHERE username = 'admin' AND is_active = 1")->fetchColumn();
    if ($seeded !== false) {
        $demoJar = $tmp . '/demo.cookies';
        $demo = login($base, $demoJar, 'admin', 'admin123');
        check('seeded demo admin login works', $demo['status'] === 302
            && str_contains($demo['headers'], 'Location: /admin/dashboard'));
    }

    // --- logout ---
    $logoutPage = request($base, 'GET', '/admin/dashboard', [], $adminJar);
    $logoutToken = csrfFrom($logoutPage['body']);
    $logout = request($base, 'POST', '/logout', ['_token' => $logoutToken], $adminJar);
    check('logout redirects to /login', $logout['status'] === 302
        && str_contains($logout['headers'], 'Location: /login'));
    check('dashboard after logout redirects to login', request($base, 'GET', '/admin/dashboard', [], $adminJar)['status'] === 302);
    // Logout destroyed the session, so even the old token cannot authenticate.
    $staleLogout = request($base, 'POST', '/logout', ['_token' => $logoutToken], $adminJar);
    check('replayed logout token is unauthenticated', $staleLogout['status'] === 302
        && str_contains($staleLogout['headers'], 'Location: /login'));

    // --- logout audit ---
    $log = $db->prepare("SELECT action, description FROM activity_logs WHERE user_id =
        (SELECT id FROM users WHERE username = 'it_admin') ORDER BY id");
    $log->execute();
    $events = $log->fetchAll(PDO::FETCH_KEY_PAIR);
    check('login is audited', isset($events['login']));
    check('logout is audited', isset($events['logout']));
    check('audit stores no password', !str_contains(json_encode($events) ?: '', $password));
    check('audit stores no session id', !preg_match('/[0-9a-f]{32,}/', (string) json_encode($events)));

    // --- static files / traversal ---
    $css = request($base, 'GET', '/assets/css/app.css');
    check('real asset is served', $css['status'] === 200 && str_contains($css['headers'], 'text/css'));
    $svg = request($base, 'GET', '/assets/icons/lucide/users.svg');
    check('svg asset is served', $svg['status'] === 200 && str_contains($svg['headers'], 'image/svg+xml'));
    $traversal = request($base, 'GET', '/../config/database.php');
    check('plain traversal does not leak config', !str_contains($traversal['body'], 'DB_PASSWORD'));
    $encoded = request($base, 'GET', '/%2e%2e/config/database.php');
    check('encoded traversal does not leak config', !str_contains($encoded['body'], 'DB_PASSWORD'));
    $dotfile = request($base, 'GET', '/.htaccess');
    check('dotfile is not served', !str_contains($dotfile['body'], 'RewriteEngine') && $dotfile['status'] !== 200);

    foreach (glob($tmp . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($tmp);
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $db->prepare("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE username IN ($in))")
        ->execute($usernames);
    $db->prepare("DELETE FROM users WHERE username IN ($in)")->execute($usernames);
}

printf("%d checks, %d failed\n", $checks, count($failures));
foreach ($failures as $failure) {
    fwrite(STDOUT, "FAIL {$failure}\n");
}
exit($failures === [] ? 0 : 1);
