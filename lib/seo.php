<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/tmdb.php';

function seo_default_meta(array $overrides = []): array
{
    $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

    $defaults = [
        'title' => 'Filmoteca Pro — Catalogo film SEO-first',
        'description' => 'Filmoteca Pro unisce catalogo film, trailer e community con integrazione TMDb e ottimizzazioni SEO.',
        'robots' => 'index,follow',
        'url' => $appUrl . '/',
        'canonical' => $appUrl . '/',
        'image' => $appUrl . '/assets/images/icon.svg',
        'type' => 'website',
        'site_name' => 'Filmoteca Pro',
        'twitter_card' => 'summary_large_image',
        'locale' => 'it_IT',
    ];

    $merged = array_merge($defaults, array_filter($overrides, static fn ($value) => $value !== null && $value !== ''));
    if (empty($merged['url'])) {
        $merged['url'] = $merged['canonical'];
    }

    return $merged;
}

function seo_render_meta(array $meta): string
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $tags = [];
    $tags[] = '<title>' . $escape($meta['title']) . '</title>';
    $tags[] = '<meta name="description" content="' . $escape($meta['description']) . '">';
    $tags[] = '<meta name="robots" content="' . $escape($meta['robots']) . '">';
    $tags[] = '<link rel="canonical" href="' . $escape($meta['canonical']) . '">';
    $tags[] = '<meta property="og:title" content="' . $escape($meta['title']) . '">';
    $tags[] = '<meta property="og:description" content="' . $escape($meta['description']) . '">';
    $tags[] = '<meta property="og:type" content="' . $escape($meta['type']) . '">';
    $tags[] = '<meta property="og:url" content="' . $escape($meta['url']) . '">';
    $tags[] = '<meta property="og:image" content="' . $escape($meta['image']) . '">';
    $tags[] = '<meta property="og:site_name" content="' . $escape($meta['site_name']) . '">';
    $tags[] = '<meta property="og:locale" content="' . $escape($meta['locale']) . '">';
    $tags[] = '<meta name="twitter:card" content="' . $escape($meta['twitter_card']) . '">';
    $tags[] = '<meta name="twitter:title" content="' . $escape($meta['title']) . '">';
    $tags[] = '<meta name="twitter:description" content="' . $escape($meta['description']) . '">';
    $tags[] = '<meta name="twitter:image" content="' . $escape($meta['image']) . '">';

    return implode(PHP_EOL, $tags);
}

function seo_build_canonical(string $path): string
{
    $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
    $normalised = $path === '' ? '/' : $path;
    if ($normalised !== '/' && !str_starts_with($normalised, '/')) {
        $normalised = '/' . $normalised;
    }
    return $appUrl . $normalised;
}

function seo_json_ld(array $payload): string
{
    return '<script type="application/ld+json">' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

function seo_build_home_jsonld(): array
{
    $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $appUrl . '/#website',
        'url' => $appUrl . '/',
        'name' => 'Filmoteca Pro',
        'description' => 'Catalogo film SEO-first con watchlist, trailer, recensioni e integrazione TMDb.',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => $appUrl . '/?search={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Filmoteca Pro',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $appUrl . '/assets/images/icon.svg',
            ],
            'sameAs' => [
                'https://www.themoviedb.org/',
            ],
        ],
    ];
}

function seo_build_movie_jsonld(array $movie, array $videos = []): array
{
    $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
    $canonical = $appUrl . '/film/' . $movie['id'] . '/' . $movie['slug'];
    $duration = isset($movie['duration']) ? 'PT' . (int) $movie['duration'] . 'M' : null;

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Movie',
        '@id' => $canonical . '#movie',
        'url' => $canonical,
        'name' => $movie['title'],
        'description' => $movie['summary'] ?? '',
        'image' => $movie['poster']['url'] ?? tmdb_build_poster_url($movie['poster_path_remote'] ?? null, 'w780'),
        'genre' => $movie['genre'] ?? null,
        'datePublished' => isset($movie['year']) ? sprintf('%d-01-01', (int) $movie['year']) : null,
        'duration' => $duration,
    ];

    if (!empty($movie['director'])) {
        $data['director'] = [
            '@type' => 'Person',
            'name' => $movie['director'],
        ];
    }

    if (!empty($movie['cast'])) {
        $data['actor'] = array_map(static function ($name) {
            return [
                '@type' => 'Person',
                'name' => $name,
            ];
        }, array_slice($movie['cast'], 0, 10));
    }

    if (!empty($movie['rating']) && !empty($movie['rating_count'])) {
        $data['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => (float) $movie['rating'],
            'ratingCount' => (int) $movie['rating_count'],
        ];
    }

    if ($videos !== []) {
        $data['trailer'] = array_map(static function ($video) {
            return array_filter([
                '@type' => 'VideoObject',
                'name' => $video['name'] ?? null,
                'embedUrl' => $video['embed_url'] ?? null,
                'thumbnailUrl' => $video['thumbnail_url'] ?? null,
                'uploadDate' => $video['published_at'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');
        }, $videos);
    }

    $data['potentialAction'] = [
        '@type' => 'WatchAction',
        'target' => [
            '@type' => 'EntryPoint',
            'urlTemplate' => $canonical,
        ],
        'expectsAcceptanceOf' => [
            '@type' => 'Offer',
            'availability' => 'https://schema.org/InStock',
        ],
    ];

    return array_filter($data, static fn ($value) => $value !== null && $value !== '');
}

function seo_build_breadcrumb_jsonld(array $crumbs): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(static function ($crumb, $index) {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['item'],
            ];
        }, $crumbs, array_keys($crumbs)),
    ];
}
