<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function app_base_path(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    $configured = trim((string) env('APP_BASE_PATH', ''), "/ \\");
    if ($configured !== '') {
        $basePath = '/' . $configured;
        return $basePath;
    }

    $appUrl = env('APP_URL');
    if ($appUrl) {
        $parsed = parse_url($appUrl);
        if ($parsed && isset($parsed['path']) && $parsed['path'] !== '' && $parsed['path'] !== '/') {
            $basePath = '/' . trim($parsed['path'], '/');
            return $basePath;
        }
    }

    $basePath = '';
    return $basePath;
}

function app_host_url(): string
{
    static $host = null;
    if ($host !== null) {
        return $host;
    }

    $appUrl = (string) env('APP_URL', '');
    if ($appUrl === '') {
        $host = '';
        return $host;
    }

    $parsed = parse_url($appUrl);
    if ($parsed === false || !isset($parsed['host'])) {
        $host = rtrim($appUrl, '/');
        return $host;
    }

    $host = ($parsed['scheme'] ?? 'http') . '://' . $parsed['host'];
    if (isset($parsed['port'])) {
        $host .= ':' . $parsed['port'];
    }

    return $host;
}

function app_path(string $path = ''): string
{
    $base = app_base_path();
    $normalized = '/' . ltrim($path, '/');
    if ($path === '' && $base !== '') {
        return $base;
    }

    $combined = $base . $normalized;
    $combined = preg_replace('#//+#', '/', $combined) ?? '/';
    if ($combined !== '/' && str_ends_with($combined, '/')) {
        $combined = rtrim($combined, '/');
    }

    return $combined === '' ? '/' : $combined;
}

function app_url(string $path = '', bool $absolute = false): string
{
    $relative = app_path($path);
    if (!$absolute) {
        return $relative;
    }

    $host = app_host_url();
    if ($host === '') {
        return $relative;
    }

    if ($relative === '/') {
        return $host . '/';
    }

    return rtrim($host, '/') . $relative;
}

function asset_url(string $path, bool $absolute = false): string
{
    return app_url('assets/' . ltrim($path, '/'), $absolute);
}
