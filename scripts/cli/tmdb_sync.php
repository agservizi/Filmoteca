#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/env.php';
require_once __DIR__ . '/../../lib/tmdb.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../movies.php';

$options = getopt('', ['mode::', 'limit::', 'since::', 'help::']);

if (isset($options['help'])) {
    echo "Filmoteca TMDb Sync\n";
    echo "Usage: php tmdb_sync.php [--mode=full|delta] [--limit=50] [--since=2024-01-01]\n";
    exit(0);
}

$mode = $options['mode'] ?? 'full';
$limit = isset($options['limit']) ? (int) $options['limit'] : 50;
$limit = max(1, $limit);
$since = $options['since'] ?? null;

$pdo = pdo_connect_from_env();
if (!$pdo) {
    fwrite(STDERR, "Database connection unavailable. Ensure .env credentials are set.\n");
    exit(1);
}

$clauses = ['tmdb_id IS NOT NULL'];
$params = [];
if ($mode === 'delta' && $since) {
    $clauses[] = 'updated_at >= :since';
    $params['since'] = $since;
}
$where = 'WHERE ' . implode(' AND ', $clauses);

$stmt = $pdo->prepare("SELECT * FROM movies $where ORDER BY updated_at DESC LIMIT :limit");
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$movies = $stmt->fetchAll();

if (!$movies) {
    echo "No movies to sync.\n";
    exit(0);
}

$success = 0;
$failures = 0;

foreach ($movies as $movie) {
    $tmdbId = (int) $movie['tmdb_id'];
    $payload = tmdb_get_movie($tmdbId, 'credits,videos,images');
    if (!$payload) {
        $failures++;
        fwrite(STDERR, "Failed to fetch TMDb payload for {$movie['title']} ({$tmdbId}).\n");
        continue;
    }

    $crew = $payload['credits']['crew'] ?? [];
    $directorName = $movie['director'];
    foreach ($crew as $crewMember) {
        if (($crewMember['job'] ?? '') === 'Director') {
            $directorName = $crewMember['name'];
            break;
        }
    }

    $data = [
        'summary' => $payload['overview'] ?? $movie['summary'],
        'director' => $directorName,
        'duration' => $payload['runtime'] ?? $movie['duration'],
        'rating' => $payload['vote_average'] ?? $movie['rating'],
        'rating_count' => $payload['vote_count'] ?? $movie['rating_count'],
        'poster_path_remote' => $payload['poster_path'] ?? $movie['poster_path_remote'],
        'cast' => json_encode(array_slice(array_column($payload['credits']['cast'] ?? [], 'name'), 0, 8), JSON_THROW_ON_ERROR),
    ];

    $updateSql = 'UPDATE movies SET summary = :summary, director = :director, duration = :duration, rating = :rating, rating_count = :rating_count, poster_path_remote = :poster_path_remote, cast = :cast, updated_at = NOW() WHERE id = :id';
    $updateStmt = $pdo->prepare($updateSql);
    foreach ($data as $key => $value) {
        $updateStmt->bindValue(':' . $key, $value);
    }
    $updateStmt->bindValue(':id', $movie['id'], PDO::PARAM_INT);

    if ($updateStmt->execute()) {
        $success++;
        echo "Synced {$movie['title']}" . PHP_EOL;
    } else {
        $failures++;
        fwrite(STDERR, "Failed to update {$movie['title']}" . PHP_EOL);
    }
}

echo "Completed. Success: {$success}, Failures: {$failures}.\n";
exit($failures > 0 ? 2 : 0);
