<?php

declare(strict_types=1);

/**
 * PHP built-in server router: php -S 127.0.0.1:8000 -t public public/router.php
 *
 * Serves only files that really exist inside public/. Everything else is handed
 * to the front controller. Dotfiles and directory traversal are refused outright.
 */
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

// Reject NUL bytes, traversal segments and dotfiles before touching the filesystem.
$blocked = $path === ''
    || str_contains($path, "\0")
    || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1
    || preg_match('#(^|/)\.#', $path) === 1;

$public = __DIR__;
$target = realpath($public . '/' . ltrim($path, '/'));

if ($blocked || $target === false || !str_starts_with($target, $public . DIRECTORY_SEPARATOR) || is_dir($target)) {
    require $public . '/index.php';

    return true;
}

$extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
$mime = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
];

if (!isset($mime[$extension])) {
    require $public . '/index.php';

    return true;
}

header('Content-Type: ' . $mime[$extension]);
header('X-Content-Type-Options: nosniff');
readfile($target);

return true;
