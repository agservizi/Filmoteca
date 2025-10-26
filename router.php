<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && $path !== '' && file_exists($file) && !is_dir($file)) {
    return false; // Serve the requested resource as-is.
}

require __DIR__ . '/index.php';
