<?php

declare(strict_types=1);

/**
 * Loads environment variables from a dotenv-style file into the process scope.
 */
function env_load(?string $path = null): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $path ??= dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $cache = [];

    if (is_readable($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, "\"'");
                if ($key === '') {
                    continue;
                }
                if (getenv($key) === false && !isset($_ENV[$key])) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
                $cache[$key] = $value;
            }
        }
    }

    foreach ($_ENV as $key => $value) {
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = $value;
        }
    }

    foreach ($_SERVER as $key => $value) {
        if (!array_key_exists($key, $cache) && is_string($value)) {
            $cache[$key] = $value;
        }
    }

    return $cache;
}

/**
 * Helper to fetch an environment variable with optional default.
 */
function env(string $key, mixed $default = null): mixed
{
    $vars = env_load();

    if (array_key_exists($key, $vars)) {
        return $vars[$key];
    }

    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    return $default;
}
