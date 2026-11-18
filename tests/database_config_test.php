<?php

declare(strict_types=1);

// Environment values must override local config, including an empty password.
putenv('DB_HOST=localhost');
putenv('DB_PORT=3307');
putenv('DB_NAME=config_test');
putenv('DB_USER=config_test');
putenv('DB_PASSWORD=');
$config = require dirname(__DIR__) . '/config/database.php';

if ($config['dsn'] !== 'mysql:host=localhost;port=3307;dbname=config_test;charset=utf8mb4'
    || $config['username'] !== 'config_test'
    || $config['password'] !== '') {
    fwrite(STDERR, "FAIL: database environment precedence\n");
    exit(1);
}

fwrite(STDOUT, "PASS: database environment precedence and empty password\n");
