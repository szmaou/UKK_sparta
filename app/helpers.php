<?php

declare(strict_types=1);

/**
 * Escape a value for HTML output. Every dynamic value in a view goes through this.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Inline an allowlisted local Lucide icon. Unknown names render nothing.
 */
function icon(string $name, string $classes = ''): string
{
    static $allowed = null;
    static $svg = [];

    $name = strtolower($name);
    if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
        return '';
    }

    if ($allowed === null) {
        $dir = dirname(__DIR__) . '/public/assets/icons/lucide';
        $meta = json_decode((string) @file_get_contents($dir . '/METADATA.json'), true);
        $allowed = [];
        foreach ((array) ($meta['icons'] ?? []) as $file) {
            $allowed[pathinfo((string) $file, PATHINFO_FILENAME)] = true;
        }
    }

    if (!isset($allowed[$name])) {
        return '';
    }

    if (!isset($svg[$name])) {
        $file = dirname(__DIR__) . '/public/assets/icons/lucide/' . $name . '.svg';
        $content = @file_get_contents($file);
        $svg[$name] = $content === false ? '' : (preg_replace('/\s+/', ' ', $content) ?? '');
    }

    if ($svg[$name] === '') {
        return '';
    }

    if ($classes !== '') {
        $classes = trim(preg_replace('/[^a-zA-Z0-9 _-]/', '', $classes) ?? '');
    }

    return $classes === ''
        ? $svg[$name]
        : (preg_replace('/<svg\b/', '<svg class="' . $classes . '"', $svg[$name], 1) ?? '');
}

/**
 * Dashboard URL for a role. Unknown roles fall back to the least privileged area.
 */
function dashboard_path(string $role): string
{
    $role = strtolower($role);

    return '/' . (in_array($role, ['admin', 'petugas', 'peminjam'], true) ? $role : 'peminjam') . '/dashboard';
}

/**
 * Send a redirect response and stop execution.
 */
function redirect(string $to): never
{
    header('Location: ' . $to, true, 302);
    exit;
}
