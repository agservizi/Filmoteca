<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/tmdb.php';
require_once __DIR__ . '/../lib/seo.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../movies.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
$slug = $_GET['slug'] ?? null;

if ($id <= 0 && $slug === null) {
    http_json_response(['error' => 'invalid_request', 'message' => 'Specify id or slug'], 400);
    return;
}

$movie = $id > 0 ? movies_find($id) : movies_find_by_slug($slug);
if (!$movie) {
    http_json_response(['error' => 'not_found'], 404);
    return;
}

$includeTmdb = filter_var($_GET['tmdb'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$tmdbPayload = null;
$videoEmbeds = [];
if ($includeTmdb && !empty($movie['tmdb_id'])) {
    $tmdbPayload = tmdb_get_movie((int) $movie['tmdb_id'], 'credits,videos,images');
    if ($tmdbPayload && isset($tmdbPayload['videos']['results'])) {
        foreach ($tmdbPayload['videos']['results'] as $video) {
            if (($video['site'] ?? '') !== 'YouTube') {
                continue;
            }
            $videoEmbeds[] = [
                'name' => $video['name'] ?? null,
                'embed_url' => 'https://www.youtube.com/embed/' . $video['key'],
                'thumbnail_url' => $video['key'] ? 'https://i.ytimg.com/vi/' . $video['key'] . '/hqdefault.jpg' : null,
                'published_at' => $video['published_at'] ?? null,
            ];
        }
    }
}

$response = [
    'data' => [
        'id' => (int) $movie['id'],
        'tmdb_id' => $movie['tmdb_id'] ?? null,
        'title' => $movie['title'],
        'slug' => $movie['slug'],
        'year' => (int) $movie['year'],
        'genres' => $movie['genre'] ?? $movie['genres'] ?? null,
        'summary' => $movie['summary'],
        'director' => $movie['director'],
        'cast' => $movie['cast'],
        'duration' => $movie['duration'],
        'rating' => $movie['rating'],
        'rating_count' => $movie['rating_count'],
        'poster' => $movie['poster'],
        'poster_path_remote' => $movie['poster_path_remote'] ?? null,
        'poster_cached_at' => $movie['poster_cached_at'] ?? null,
        'links' => [
            'html' => app_url('film/' . $movie['id'] . '/' . $movie['slug']),
        ],
        'jsonld' => seo_build_movie_jsonld($movie, $videoEmbeds),
        'tmdb' => $tmdbPayload,
    ],
];

http_json_response($response, 200, [
    'Cache-Control' => 'public, max-age=300, stale-while-revalidate=120',
]);
