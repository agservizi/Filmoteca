<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/cache.php';

const TMDB_API_BASE = 'https://api.themoviedb.org/3';
const TMDB_API_BASE_V4 = 'https://api.themoviedb.org/4';

function tmdb_cache_ttl(): int
{
    return (int) env('TMDB_CACHE_TTL', 86400);
}

function tmdb_request(string $method, string $path, array $params = [], bool $useV4 = false): ?array
{
    $apiKey = env('TMDB_API_KEY');
    $readToken = env('TMDB_READ_ACCESS_TOKEN');

    if (!$apiKey && !$readToken) {
        return null;
    }

    if ($useV4 && !$readToken) {
        return null;
    }

    $base = $useV4 ? TMDB_API_BASE_V4 : TMDB_API_BASE;
    $url = $base . $path;

    $cacheKey = $method . ':' . $url . ':' . json_encode($params);
    $cached = cache_get('tmdb', $cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $queryParams = $params;
    if (!$useV4 && $apiKey) {
        $queryParams = array_merge(['api_key' => $apiKey], $queryParams);
    }

    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $headers = [
        'Accept: application/json',
        'User-Agent: FilmotecaPro/1.0 (+'. env('APP_URL', 'http://localhost') .')',
    ];

    if ($readToken) {
        $headers[] = 'Authorization: Bearer ' . $readToken;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $response === false) {
        error_log('TMDb request error for ' . $url . ': ' . curl_strerror($errno));
        return null;
    }

    if ($status >= 400) {
        error_log('TMDb responded with HTTP ' . $status . ' for ' . $url . ': ' . $response);
        return null;
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return null;
    }

    cache_set('tmdb', $cacheKey, $json, tmdb_cache_ttl());

    return $json;
}

function tmdb_configuration(): ?array
{
    return tmdb_request('GET', '/configuration');
}

function tmdb_search_movie(string $query, ?int $year = null, int $page = 1): ?array
{
    $params = ['query' => $query, 'page' => $page];
    if ($year) {
        $params['year'] = $year;
    }
    return tmdb_request('GET', '/search/movie', $params);
}

function tmdb_get_movie(int $tmdbId, string $append = 'credits,videos,images'): ?array
{
    $params = [];
    if ($append !== '') {
        $params['append_to_response'] = $append;
    }
    return tmdb_request('GET', '/movie/' . $tmdbId, $params);
}

function tmdb_get_movie_videos(int $tmdbId): ?array
{
    return tmdb_request('GET', '/movie/' . $tmdbId . '/videos');
}

function tmdb_build_poster_url(?string $posterPath, string $size = 'w500'): ?string
{
    if (!$posterPath) {
        return null;
    }
    $config = tmdb_configuration();
    if (!$config || empty($config['images']['secure_base_url'])) {
        return null;
    }
    $baseUrl = rtrim($config['images']['secure_base_url'], '/');
    return $baseUrl . '/' . $size . $posterPath;
}
