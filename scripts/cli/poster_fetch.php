#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/env.php';
require_once __DIR__ . '/../../lib/tmdb.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../movies.php';

$pdo = pdo_connect_from_env();
if (!$pdo) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$sizes = ['w154', 'w342', 'w500', 'w780'];
$useWebp = function_exists('imagecreatefromstring') && function_exists('imagewebp');
$extension = $useWebp ? 'webp' : 'jpg';
$baseDir = realpath(__DIR__ . '/../../assets/posters/cache') ?: __DIR__ . '/../../assets/posters/cache';

foreach ($sizes as $size) {
    $dir = $baseDir . '/' . $size;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "Unable to create directory {$dir}\n");
        exit(1);
    }
}

$stmt = $pdo->query('SELECT id, slug, tmdb_id, poster_path_remote FROM movies WHERE tmdb_id IS NOT NULL');
$movies = $stmt->fetchAll();

if (!$movies) {
    echo "No movies with TMDb references to process.\n";
    exit(0);
}

$downloaded = 0;
$failed = 0;

foreach ($movies as $movie) {
    if (empty($movie['poster_path_remote'])) {
        fwrite(STDERR, "Skipping {$movie['slug']} — no poster_path_remote.\n");
        $failed++;
        continue;
    }

    $posterPath = $movie['poster_path_remote'];
    $slug = $movie['slug'];

    foreach ($sizes as $size) {
        $url = tmdb_build_poster_url($posterPath, $size) ?? null;
        if (!$url) {
            fwrite(STDERR, "Failed to build poster URL for {$slug} size {$size}.\n");
            $failed++;
            continue 2;
        }

    $targetPath = $baseDir . '/' . $size . '/' . $slug . '.' . $extension;
        if (file_exists($targetPath) && filemtime($targetPath) > strtotime('-7 days')) {
            continue;
        }

        $binary = file_get_contents($url);
        if ($binary === false) {
            fwrite(STDERR, "Download failed for {$url}\n");
            $failed++;
            continue 2;
        }

        if (!$useWebp) {
            if (file_put_contents($targetPath, $binary) === false) {
                fwrite(STDERR, "Failed writing fallback image for {$slug}.\n");
                $failed++;
                continue 2;
            }
            $downloaded++;
            continue;
        }

        $image = imagecreatefromstring($binary);
        if (!$image) {
            fwrite(STDERR, "GD could not parse image for {$slug}.\n");
            $failed++;
            continue 2;
        }

        if (!imagewebp($image, $targetPath, 85)) {
            fwrite(STDERR, "Failed writing WebP for {$slug}.\n");
            imagedestroy($image);
            $failed++;
            continue 2;
        }
        imagedestroy($image);
        $downloaded++;
    }

    $posterLocal = 'assets/posters/cache/w500/' . $slug . '.' . $extension;
    $update = $pdo->prepare('UPDATE movies SET poster_path_local = :local, poster_cached_at = NOW() WHERE id = :id');
    $update->execute([
        'local' => $posterLocal,
        'id' => $movie['id'],
    ]);
}

echo "Poster processing complete. Generated {$downloaded} assets, {$failed} failures.\n";
exit($failed > 0 ? 2 : 0);
