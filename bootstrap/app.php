<?php

declare(strict_types=1);

// Minimal service container: ['db' => PDO].
$config = require dirname(__DIR__) . '/config/database.php';

return [
    'db' => new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]),
];
