<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/tmdb.php';
require_once __DIR__ . '/../lib/cache.php';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'anonymous';
$rateLimitKey = 'movies:' . $ip;
$now = time();
$bucket = cache_get('api', $rateLimitKey);
if (!is_array($bucket) || ($bucket['reset'] ?? 0) <= $now) {
    $bucket = ['count' => 0, 'reset' => $now + 60];
}

$rateLimitMax = 120;
if ($bucket['count'] >= $rateLimitMax) {
    $retryAfter = max(1, $bucket['reset'] - $now);
    header('Retry-After: ' . $retryAfter);
    http_json_response([
        'error' => 'rate_limit_exceeded',
        'message' => 'API rate limit exceeded. Please retry later.',
    ], 429, [
        'X-RateLimit-Limit' => (string) $rateLimitMax,
        'X-RateLimit-Remaining' => '0',
        'X-RateLimit-Reset' => (string) $bucket['reset'],
    ]);
    exit;
}

$bucket['count']++;
cache_set('api', $rateLimitKey, $bucket, (int) max(1, $bucket['reset'] - $now));
require_once __DIR__ . '/../movies.php';

$appUrl = env('APP_URL', 'http://localhost');
$parsedApp = parse_url((string) $appUrl);
$originHost = $parsedApp ? ($parsedApp['scheme'] . '://' . $parsedApp['host'] . (isset($parsedApp['port']) ? ':' . $parsedApp['port'] : '')) : null;
$allowedOrigins = $originHost ? [$originHost] : [];
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin && http_origin_allowed($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? MOVIES_DEFAULT_PER_PAGE);
$perPage = max(1, min(30, $perPage));
$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'genre' => trim((string) ($_GET['genre'] ?? '')),
    'year' => $_GET['year'] ?? null,
];

$includeTmdb = filter_var($_GET['tmdb'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

$result = movies_paginated($filters, $page, $perPage);
$movies = array_map(static function ($movie) use ($includeTmdb) {
    $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
    return [
        'id' => (int) $movie['id'],
        'tmdb_id' => $movie['tmdb_id'] ?? null,
        'title' => $movie['title'],
        'slug' => $movie['slug'],
        'year' => (int) $movie['year'],
        'genre' => $movie['genre'],
        'summary' => $movie['summary'],
        'director' => $movie['director'] ?? null,
        'duration' => $movie['duration'] ?? null,
        'rating' => $movie['rating'] ?? null,
        'rating_count' => $movie['rating_count'] ?? null,
        'poster' => $movie['poster'] ?? null,
        'poster_srcset' => $movie['poster']['srcset'] ?? [],
        'poster_path_remote' => $movie['poster_path_remote'] ?? null,
        'aggregateRating' => (
            !empty($movie['rating']) && !empty($movie['rating_count'])
        ) ? [
            'ratingValue' => $movie['rating'],
            'ratingCount' => $movie['rating_count'],
        ] : null,
        'links' => [
            'self' => $appUrl . '/film/' . $movie['id'] . '/' . $movie['slug'],
        ],
        'tmdb' => ($includeTmdb && !empty($movie['tmdb_id'])) ? [
            'id' => (int) $movie['tmdb_id'],
            'url' => 'https://www.themoviedb.org/movie/' . $movie['tmdb_id'],
            'poster' => tmdb_build_poster_url($movie['poster_path_remote'] ?? null, 'w500'),
        ] : null,
    ];
}, $result['data']);

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

$query = $_GET;
$query['page'] = $page;
$self = $appUrl . '/api/movies.php?' . http_build_query($query);

$links = [
    'self' => $self,
    'prev' => $page > 1 ? $appUrl . '/api/movies.php?' . http_build_query(array_merge($query, ['page' => $page - 1])) : null,
    'next' => $page < $result['meta']['total_pages'] ? $appUrl . '/api/movies.php?' . http_build_query(array_merge($query, ['page' => $page + 1])) : null,
];

if ($links['prev'] || $links['next']) {
    $headerLinks = [];
    if ($links['prev']) {
        $headerLinks[] = '<' . $links['prev'] . '>; rel="prev"';
    }
    if ($links['next']) {
        $headerLinks[] = '<' . $links['next'] . '>; rel="next"';
    }
    header('Link: ' . implode(', ', $headerLinks), false);
}

$response = [
    'data' => $movies,
    'meta' => $result['meta'],
    'links' => $links,
];

http_json_response($response, 200, [
    'Cache-Control' => 'public, max-age=120, stale-while-revalidate=60',
    'X-Application-Name' => 'Filmoteca Pro',
    'X-RateLimit-Limit' => (string) $rateLimitMax,
    'X-RateLimit-Remaining' => (string) max(0, $rateLimitMax - $bucket['count']),
    'X-RateLimit-Reset' => (string) $bucket['reset'],
]);
