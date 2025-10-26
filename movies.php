<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/tmdb.php';

const MOVIES_DEFAULT_PER_PAGE = 12;

function movies_seed_data(): array
{
    return [
        [
            'id' => 1,
            'tmdb_id' => 27205,
            'title' => 'Inception',
            'slug' => 'inception-2010',
            'year' => 2010,
            'genre' => 'Science Fiction',
            'genres' => ['Science Fiction', 'Action'],
            'poster_path_local' => null,
            'poster_path_remote' => '/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg',
            'poster_cached_at' => null,
            'summary' => 'Un ladro capace di infiltrarsi nei sogni viene incaricato di impiantare un\'idea nella mente di un magnate.',
            'director' => 'Christopher Nolan',
            'cast' => ['Leonardo DiCaprio', 'Joseph Gordon-Levitt', 'Elliot Page'],
            'duration' => 148,
            'rating' => 8.3,
            'rating_count' => 32000,
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00',
        ],
        [
            'id' => 2,
            'tmdb_id' => 238,
            'title' => 'Il padrino',
            'slug' => 'il-padrino-1972',
            'year' => 1972,
            'genre' => 'Crime',
            'genres' => ['Crime', 'Drama'],
            'poster_path_local' => null,
            'poster_path_remote' => '/3bhkrj58Vtu7enYsRolD1fZdja1.jpg',
            'poster_cached_at' => null,
            'summary' => 'La saga dei Corleone racconta l\'ascesa e la trasformazione del potere criminale in America.',
            'director' => 'Francis Ford Coppola',
            'cast' => ['Marlon Brando', 'Al Pacino', 'James Caan'],
            'duration' => 175,
            'rating' => 9.2,
            'rating_count' => 42000,
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00',
        ],
        [
            'id' => 3,
            'tmdb_id' => 603,
            'title' => 'Matrix',
            'slug' => 'matrix-1999',
            'year' => 1999,
            'genre' => 'Action',
            'genres' => ['Action', 'Science Fiction'],
            'poster_path_local' => null,
            'poster_path_remote' => '/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'poster_cached_at' => null,
            'summary' => 'Thomas Anderson scopre la vera natura della realtà e abbraccia il suo destino come Neo.',
            'director' => 'Lana Wachowski, Lilly Wachowski',
            'cast' => ['Keanu Reeves', 'Carrie-Anne Moss', 'Laurence Fishburne'],
            'duration' => 136,
            'rating' => 8.2,
            'rating_count' => 28000,
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00',
        ],
        [
            'id' => 4,
            'tmdb_id' => 1891,
            'title' => 'Il favoloso mondo di Amélie',
            'slug' => 'il-favoloso-mondo-di-amelie-2001',
            'year' => 2001,
            'genre' => 'Romance',
            'genres' => ['Romance', 'Comedy'],
            'poster_path_local' => null,
            'poster_path_remote' => '/wnUAcUrMRGPPZUDroLezhz7kwR7.jpg',
            'poster_cached_at' => null,
            'summary' => 'Amélie decide di dedicarsi a migliorare la vita degli altri mentre scopre l\'amore.',
            'director' => 'Jean-Pierre Jeunet',
            'cast' => ['Audrey Tautou', 'Mathieu Kassovitz'],
            'duration' => 122,
            'rating' => 8.0,
            'rating_count' => 17000,
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00',
        ],
        [
            'id' => 5,
            'tmdb_id' => 424, 
            'title' => 'Schindler\'s List',
            'slug' => 'schindlers-list-1993',
            'year' => 1993,
            'genre' => 'Drama',
            'genres' => ['Drama', 'History'],
            'poster_path_local' => null,
            'poster_path_remote' => '/c8Ass7acuOe4za6DhSattE359gr.jpg',
            'poster_cached_at' => null,
            'summary' => 'La storia di Oskar Schindler e del suo piano per salvare centinaia di ebrei durante l\'olocausto.',
            'director' => 'Steven Spielberg',
            'cast' => ['Liam Neeson', 'Ben Kingsley', 'Ralph Fiennes'],
            'duration' => 195,
            'rating' => 8.6,
            'rating_count' => 25000,
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00',
        ],
    ];
}

defined('JSON_THROW_ON_ERROR') or define('JSON_THROW_ON_ERROR', 4194304);

function movies_supports_database(): bool
{
    return pdo_connect_from_env() instanceof PDO;
}

function movies_repository_fetch(array $filters, int $page, int $perPage): array
{
    $offset = ($page - 1) * $perPage;

    if (!movies_supports_database()) {
        return movies_repository_from_seed($filters, $page, $perPage);
    }

    $pdo = pdo_connect_from_env();
    [$where, $params] = movies_build_where($filters);

    $stmt = $pdo->prepare('SELECT SQL_CALC_FOUND_ROWS * FROM movies ' . $where . ' ORDER BY year DESC, title ASC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue(':' . $key, $value, $paramType);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    $total = (int) $pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

    $hydrated = array_map('movies_hydrate', $rows);

    return [
        'data' => $hydrated,
        'total' => $total,
    ];
}

function movies_build_where(array $filters): array
{
    $clauses = [];
    $params = [];

    if (!empty($filters['search'])) {
        $clauses[] = '(title LIKE :search OR summary LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['genre'])) {
        $clauses[] = '(genre = :genre OR JSON_CONTAINS(genres, :genre_json))';
        $params['genre'] = $filters['genre'];
        $params['genre_json'] = json_encode($filters['genre']);
    }
    if (!empty($filters['year'])) {
        $clauses[] = 'year = :year';
        $params['year'] = (int) $filters['year'];
    }

    $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

    return [$where, $params];
}

function movies_repository_from_seed(array $filters, int $page, int $perPage): array
{
    $all = array_map('movies_hydrate', movies_seed_data());

    $filtered = array_filter($all, static function ($movie) use ($filters) {
        $passes = true;
        if (!empty($filters['search'])) {
            $needle = mb_strtolower($filters['search']);
            $passes = str_contains(mb_strtolower($movie['title']), $needle)
                || str_contains(mb_strtolower($movie['summary']), $needle);
        }
        if ($passes && !empty($filters['genre'])) {
            $genreNeedle = mb_strtolower($filters['genre']);
            $haystack = array_map(static fn($item) => mb_strtolower($item), $movie['genres'] ?? []);
            if (isset($movie['genre'])) {
                $haystack[] = mb_strtolower($movie['genre']);
            }
            $passes = in_array($genreNeedle, $haystack, true);
        }
        if ($passes && !empty($filters['year'])) {
            $passes = (int) $movie['year'] === (int) $filters['year'];
        }
        return $passes;
    });

    usort($filtered, static function ($a, $b) {
        return [$b['year'], $a['title']] <=> [$a['year'], $b['title']];
    });

    $total = count($filtered);
    $chunks = array_chunk($filtered, $perPage);
    $pageIndex = max(0, $page - 1);
    $data = $chunks[$pageIndex] ?? [];

    return [
        'data' => $data,
        'total' => $total,
    ];
}

function movies_paginated(array $filters = [], int $page = 1, int $perPage = MOVIES_DEFAULT_PER_PAGE): array
{
    $page = max(1, $page);
    $perPage = max(1, min(60, $perPage));

    $result = movies_repository_fetch($filters, $page, $perPage);
    $total = $result['total'];
    $totalPages = (int) ceil($total / $perPage);

    return [
        'data' => $result['data'],
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
    ];
}

function movies_find(int $id): ?array
{
    if (movies_supports_database()) {
        $pdo = pdo_connect_from_env();
        $stmt = $pdo->prepare('SELECT * FROM movies WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            return movies_hydrate($row);
        }
        return null;
    }

    foreach (movies_seed_data() as $movie) {
        if ((int) $movie['id'] === $id) {
            return movies_hydrate($movie);
        }
    }
    return null;
}

function movies_find_by_slug(string $slug): ?array
{
    if (movies_supports_database()) {
        $pdo = pdo_connect_from_env();
        $stmt = $pdo->prepare('SELECT * FROM movies WHERE slug = :slug LIMIT 1');
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            return movies_hydrate($row);
        }
        return null;
    }

    foreach (movies_seed_data() as $movie) {
        if ($movie['slug'] === $slug) {
            return movies_hydrate($movie);
        }
    }
    return null;
}

function movies_hydrate(array $movie): array
{
    if (is_string($movie['cast'] ?? null)) {
        $decoded = json_decode($movie['cast'], true);
        $movie['cast'] = is_array($decoded) ? $decoded : [];
    }

    if (is_string($movie['genres'] ?? null)) {
        $decodedGenres = json_decode($movie['genres'], true);
        $movie['genres'] = is_array($decodedGenres) ? $decodedGenres : [];
    }
    if (!isset($movie['genres']) && isset($movie['genre'])) {
        $movie['genres'] = [$movie['genre']];
    }

    $movie['poster'] = movies_build_poster_payload($movie);

    return $movie;
}

function movies_build_poster_payload(array $movie): array
{
    $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
    $useRemote = filter_var(env('TMDB_USE_REMOTE_IMAGES', 'true'), FILTER_VALIDATE_BOOLEAN);

    $posterLocal = $movie['poster_path_local'] ?? null;
    $posterRemote = $movie['poster_path_remote'] ?? null;

    $url = null;
    if ($posterLocal) {
        $url = $appUrl . '/' . ltrim($posterLocal, '/');
    } elseif ($useRemote && $posterRemote) {
        $url = tmdb_build_poster_url($posterRemote, 'w500');
    }

    $srcset = [];
    $sizes = ['w154', 'w342', 'w500', 'w780'];
    foreach ($sizes as $size) {
        $localVariant = $posterLocal ? preg_replace('#(\.[^.]+)$#', sprintf('.%s$1', $size), $posterLocal) : null;
        $src = null;
        if ($localVariant && file_exists(dirname(__DIR__) . '/' . $localVariant)) {
            $src = $appUrl . '/' . ltrim($localVariant, '/');
        } elseif ($posterRemote) {
            $src = tmdb_build_poster_url($posterRemote, $size);
        }
        if ($src) {
            $srcset[] = ['size' => $size, 'url' => $src];
        }
    }

    return [
        'url' => $url,
        'srcset' => $srcset,
        'alt' => $movie['title'] . ' poster',
    ];
}

function movies_count(array $filters = []): int
{
    if (movies_supports_database()) {
        $pdo = pdo_connect_from_env();
        [$where, $params] = movies_build_where($filters);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM movies ' . $where);
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $paramType);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    $seed = movies_repository_from_seed($filters, 1, PHP_INT_MAX);
    return (int) ($seed['total'] ?? count($seed['data'] ?? []));
}

function movies_distinct_genres(): array
{
    if (movies_supports_database()) {
        $pdo = pdo_connect_from_env();
        $rows = $pdo->query('SELECT DISTINCT genre FROM movies WHERE genre IS NOT NULL AND genre <> "" ORDER BY genre ASC')->fetchAll(PDO::FETCH_COLUMN);
        $jsonRows = $pdo->query('SELECT genres FROM movies WHERE genres IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        $genres = is_array($rows) ? $rows : [];
        foreach ($jsonRows as $json) {
            $decoded = json_decode((string) $json, true);
            if (is_array($decoded)) {
                $genres = array_merge($genres, $decoded);
            }
        }
        $genres = array_filter(array_unique(array_map('trim', $genres)));
        sort($genres, SORT_NATURAL | SORT_FLAG_CASE);
        return $genres;
    }

    $all = array_map(static fn($movie) => $movie['genres'] ?? [], movies_seed_data());
    $flat = $all === [] ? [] : array_merge(...$all);
    $unique = array_unique($flat);
    sort($unique, SORT_NATURAL | SORT_FLAG_CASE);
    return $unique;
}

function movies_recent(int $limit = 5): array
{
    $limit = max(1, $limit);
    if (movies_supports_database()) {
        $pdo = pdo_connect_from_env();
        $stmt = $pdo->prepare('SELECT * FROM movies ORDER BY updated_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map('movies_hydrate', $stmt->fetchAll());
    }

    $seed = array_map('movies_hydrate', movies_seed_data());
    usort($seed, static fn($a, $b) => ($b['updated_at'] ?? '') <=> ($a['updated_at'] ?? ''));
    return array_slice($seed, 0, $limit);
}
