<?php

declare(strict_types=1);

// Router/session/CSRF/helper checks that need no database.
//   php tests/security_test.php
// Exit 0 = pass, 1 = fail.
require dirname(__DIR__) . '/app/autoload.php';

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

$root = dirname(__DIR__);

// --- Router ---------------------------------------------------------------
$router = new App\Core\Router();
$hit = null;
$router->get('/admin/dashboard', static function () use (&$hit): void {
    $hit = 'admin';
}, ['admin']);
$router->get('/peminjam/peminjaman/{id}', static function () use (&$hit): void {
    $hit = 'detail';
});

$match = $router->match('GET', '/admin/dashboard');
check('exact route matches', is_array($match) && $match['roles'] === ['admin']);
check('trailing slash normalizes', is_array($router->match('GET', '/admin/dashboard/')));
check('private route is not public', is_array($match) && $match['auth'] === true);
check('unknown route does not match', $router->match('GET', '/nope') === null);
check('wrong method does not match', $router->match('POST', '/admin/dashboard') === null);
check('allowedMethods reports GET only', $router->allowedMethods('/admin/dashboard') === ['GET']);
check('route params are captured', ($router->match('GET', '/peminjam/peminjaman/42')['params'] ?? []) === ['id' => '42']);
check('encoded param is decoded', ($router->match('GET', '/peminjam/peminjaman/a%20b')['params'] ?? []) === ['id' => 'a b']);

$public = new App\Core\Router();
$public->get('/login', static fn (): string => 'ok', public: true);
check('public route skips auth', $public->match('GET', '/login')['auth'] === false);

// --- Helpers --------------------------------------------------------------
check('e escapes html', e('<b>"x"</b>') === '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');
check('e null is empty', e(null) === '');
check('dashboard_path maps roles', dashboard_path('petugas') === '/petugas/dashboard');
check('dashboard_path falls back', dashboard_path('root') === '/peminjam/dashboard');
check('icon renders allowlisted svg', str_starts_with(icon('users'), '<svg'));
check('icon applies classes', str_contains(icon('users', 'size-4'), 'class="size-4"'));
check('icon rejects unknown name', icon('definitely-not-an-icon') === '');
check('icon rejects traversal', icon('../../etc/passwd') === '');
check('icon rejects injected class chars', !str_contains(icon('users', 'x" onload="'), 'onload="'));

// --- Session / CSRF -------------------------------------------------------
$session = new App\Core\Session();
$session->start();

$params = session_get_cookie_params();
check('session cookie httponly', $params['httponly'] === true);
check('session cookie samesite lax', ($params['samesite'] ?? '') === 'Lax');
check('session cookie not secure on plain http', $params['secure'] === false);

$token = $session->csrfToken();
check('csrf token is 64 hex chars', preg_match('/^[0-9a-f]{64}$/', $token) === 1);
check('csrf token is stable', $session->csrfToken() === $token);
check('csrf accepts matching token', $session->verifyCsrf($token));
check('csrf rejects wrong token', !$session->verifyCsrf(str_repeat('a', 64)));
check('csrf rejects empty token', !$session->verifyCsrf(''));
check('csrf rejects non-string token', !$session->verifyCsrf(['x']));

$session->set('user_id', 7);
$before = session_id();
$session->regenerate();
check('regenerate rotates session id', session_id() !== '' && session_id() !== $before);
check('regenerate keeps identity', $session->get('user_id') === 7);
check('regenerate drops csrf token', $session->csrfToken() !== $token);

$session->destroy();
check('destroy clears session data', !isset($_SESSION['user_id']));

// HTTPS requests must get a Secure cookie; plain HTTP must not.
$_SERVER['HTTPS'] = 'on';
check('isHttps reads the server flag', $session->isHttps());
$httpsSession = new App\Core\Session();
$httpsSession->start();
check('session cookie secure under https', session_get_cookie_params()['secure'] === true);
$httpsSession->destroy();
unset($_SERVER['HTTPS']);

// --- Router guard order (role check is server-side metadata) ---------------
$roleRouter = new App\Core\Router();
$roleRouter->get('/admin/dashboard', static fn (): string => 'ok', ['admin']);
$roleRoute = $roleRouter->match('GET', '/admin/dashboard');
check('admin route requires admin role', $roleRoute['roles'] === ['admin']);
check('role list excludes other roles', !in_array('peminjam', $roleRoute['roles'], true));

// --- View -----------------------------------------------------------------
$view = new App\Core\View($root . '/app/Views');
$viewFile = $root . '/app/Views/' . 'auth/login.php';
check('login view exists', is_file($viewFile));
check('dashboard view exists', is_file($root . '/app/Views/dashboard.php'));
check('403 view exists', is_file($root . '/app/Views/errors/403.php'));
check('500 view exists', is_file($root . '/app/Views/errors/500.php'));
check('view class loads', $view instanceof App\Core\View);

// --- Result ---------------------------------------------------------------
printf("%d checks, %d failed\n", $checks, count($failures));
foreach ($failures as $failure) {
    fwrite(STDOUT, "FAIL {$failure}\n");
}
exit($failures === [] ? 0 : 1);
