<?php

declare(strict_types=1);

use JsonException;

require_once __DIR__ . '/env.php';

const CACHE_BASE_PATH = __DIR__ . '/../cache';

function cache_path(string $namespace, string $key): string
{
    $hashedKey = sha1($key);
    $dir = CACHE_BASE_PATH . '/' . trim($namespace, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create cache directory: ' . $dir);
    }
    return $dir . '/' . $hashedKey . '.cache';
}

function cache_get(string $namespace, string $key): mixed
{
    $file = cache_path($namespace, $key);
    if (!is_readable($file)) {
        return null;
    }
    $payload = file_get_contents($file);
    if ($payload === false) {
        return null;
    }
    $data = json_decode($payload, true);
    if (!is_array($data) || !isset($data['expires_at'])) {
        return null;
    }
    if ($data['expires_at'] !== 0 && $data['expires_at'] < time()) {
        unlink($file);
        return null;
    }
    return $data['value'] ?? null;
}

function cache_set(string $namespace, string $key, mixed $value, int $ttl = 0): void
{
    $file = cache_path($namespace, $key);
    $expiresAt = $ttl > 0 ? time() + $ttl : 0;
    try {
        $payload = json_encode([
            'value' => $value,
            'expires_at' => $expiresAt,
            'stored_at' => date(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        error_log('Failed to encode cache payload: ' . $exception->getMessage());
        return;
    }

    if (file_put_contents($file, $payload, LOCK_EX) === false) {
        error_log('Failed to write cache file: ' . $file);
    }
}

function cache_forget(string $namespace, string $key): void
{
    $file = cache_path($namespace, $key);
    if (is_file($file)) {
        unlink($file);
    }
}
