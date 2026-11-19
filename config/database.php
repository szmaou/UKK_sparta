<?php

declare(strict_types=1);

// Fill missing DB_* from the repository .env. Real environment variables always
// win, so `export DB_*` and disposable-test overrides still take precedence.
$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1 || getenv($key) !== false) {
            continue;
        }
        $value = trim($value);
        if (preg_match('/^(["\'])(.*)\1$/', $value, $quoted) === 1) {
            $value = $quoted[2];
        }
        putenv($key . '=' . $value);
    }
}

// Returns the connection contract used by bootstrap/app.php and seeders.
// Values come from the environment; defaults target the local demo database.
$localPath = __DIR__ . '/database.local.php';
$local = is_file($localPath) ? require $localPath : [];
$env = static function (string $key, string $default) use ($local): string {
    $value = getenv($key);

    return $value === false ? ($local[$key] ?? $default) : $value;
};

return [
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env('DB_HOST', '127.0.0.1'),
        $env('DB_PORT', '3306'),
        $env('DB_NAME', 'sparta')
    ),
    'username' => $env('DB_USER', 'sparta'),
    'password' => $env('DB_PASSWORD', ''),
];
