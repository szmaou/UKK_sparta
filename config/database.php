<?php

declare(strict_types=1);

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
