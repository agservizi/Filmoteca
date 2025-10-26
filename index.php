<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/seo.php';
require_once __DIR__ . '/lib/url.php';
require_once __DIR__ . '/movies.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePrefix = app_base_path();
if ($basePrefix !== '' && str_starts_with($uri, $basePrefix)) {
    $uri = substr($uri, strlen($basePrefix)) ?: '/';
}
$uri = rtrim($uri, '/') ?: '/';

if (preg_match('#^/film/(\d+)/(.*)$#', $uri, $matches)) {
    require __DIR__ . '/film.php';
    exit;
}

if ($uri === '/sitemap.xml' || $uri === '/sitemap.php') {
    require __DIR__ . '/sitemap.php';
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$searchTerm = $_GET['search'] ?? '';
$yearFilter = $_GET['year'] ?? null;
$genreFilter = $_GET['genre'] ?? null;
$genreSlug = null;

if ($uri === '/page') {
    header('Location: ' . app_path(''), true, 301);
    exit;
}

if (preg_match('#^/page/(\d+)$#', $uri, $matches)) {
    $page = max(1, (int) $matches[1]);
}

if (preg_match('#^/genere/([\w-]+)(?:/page/(\d+))?$#', $uri, $matches)) {
    $genreSlug = $matches[1];
    $genreFilter = str_replace('-', ' ', $genreSlug);
    if (!empty($matches[2])) {
        $page = max(1, (int) $matches[2]);
    }
}

if ($genreFilter && !isset($_GET['genre'])) {
    $_GET['genre'] = $genreFilter;
}

if (isset($_GET['page']) && $page > 1) {
    $canonicalPagePath = build_route_path($genreSlug ? '/genere/' . $genreSlug : '/', $page);
    header('Location: ' . $canonicalPagePath, true, 301);
    exit;
}

$filters = [
    'search' => $searchTerm,
    'genre' => $genreFilter,
    'year' => $yearFilter,
];

$results = movies_paginated($filters, $page, MOVIES_DEFAULT_PER_PAGE);
$movies = $results['data'];
$metaData = $results['meta'];

$basePath = $genreFilter ? '/genere/' . strtolower(str_replace(' ', '-', $genreFilter)) : '/';
$canonicalPath = build_route_path($basePath, $page);

$meta = seo_default_meta([
    'title' => 'Filmoteca Pro — Esplora il catalogo di film essenziale',
    'description' => 'Cerca, filtra e scopri film con metadati arricchiti, trailer e valutazioni dalla community Filmoteca Pro.',
    'url' => seo_build_canonical($canonicalPath),
    'canonical' => seo_build_canonical($canonicalPath),
]);

if ($page > 1) {
    $meta['robots'] = 'index,follow';
}

$linkHeaders = [];
if ($metaData['total_pages'] > 1) {
    if ($page > 1) {
        $linkHeaders[] = '<' . seo_build_canonical(build_route_path($basePath, $page - 1)) . '>; rel="prev"';
    }
    if ($page < $metaData['total_pages']) {
        $linkHeaders[] = '<' . seo_build_canonical(build_route_path($basePath, $page + 1)) . '>; rel="next"';
    }
}
if ($linkHeaders !== []) {
    header('Link: ' . implode(', ', $linkHeaders), false);
}

$structuredDataScripts = [
    seo_json_ld(seo_build_home_jsonld()),
];

include __DIR__ . '/header.php';
?>
<section class="hero is-medium is-primary hero-gradient">
    <div class="hero-body">
        <div class="container">
            <p class="subtitle is-uppercase is-size-6 has-text-weight-semibold">Catalogo ultra moderno</p>
            <h1 class="title is-1">Filmoteca Pro</h1>
            <p class="subtitle is-4">Il tuo hub SEO-first per scoprire, salvare e condividere i film che ami.</p>
            <form id="search-form" class="box search-box" method="get" action="<?= htmlspecialchars(app_path(''), ENT_QUOTES, 'UTF-8'); ?>" role="search">
                <div class="field has-addons">
                    <div class="control is-expanded">
                        <label class="is-sr-only" for="search">Cerca film</label>
                        <input class="input is-large" type="search" id="search" name="search" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cerca per titolo, regista o trama" aria-label="Cerca film">
                    </div>
                    <div class="control">
                        <button class="button is-large is-dark" type="submit">Cerca</button>
                    </div>
                </div>
                <div class="field is-grouped is-grouped-multiline filters" aria-label="Filtri catalogo">
                    <div class="control">
                        <div class="select is-medium">
                            <label for="genre" class="is-sr-only">Genere</label>
                            <select id="genre" name="genre">
                                <option value="">Tutti i generi</option>
                                <?php
                                $genres = ['Science Fiction', 'Crime', 'Action', 'Romance', 'Drama'];
                                foreach ($genres as $genre) {
                                    $selected = $genreFilter === $genre ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($genre, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars($genre, ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="control">
                        <label class="is-sr-only" for="year">Anno</label>
                        <input class="input is-medium" type="number" min="1900" max="<?= (int) date('Y'); ?>" step="1" id="year" name="year" value="<?= htmlspecialchars((string) ($yearFilter ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Anno">
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
<section class="section">
    <div class="container">
        <header class="section-header">
            <h2 class="title is-3">Suggeriti per te</h2>
            <p class="subtitle">Risultati: <?= (int) $metaData['total']; ?> film trovati</p>
        </header>
        <?php if ($movies === []): ?>
            <div class="notification is-warning" role="status">
                Nessun film corrisponde alla ricerca.
            </div>
        <?php else: ?>
            <div class="columns is-multiline">
                <?php foreach ($movies as $movie): ?>
                    <div class="column is-one-quarter-desktop is-one-third-tablet is-half-mobile">
                        <?php $movieCopy = $movie; include __DIR__ . '/movie-card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <nav class="pagination is-centered" role="navigation" aria-label="Pagination">
            <?php
            $currentPage = (int) $metaData['page'];
            $totalPages = (int) $metaData['total_pages'];
            $queryBase = $_GET;
            ?>
            <a class="pagination-previous" <?= $currentPage <= 1 ? 'disabled' : 'href="' . htmlspecialchars(build_page_url($currentPage - 1, $queryBase, $basePath), ENT_QUOTES, 'UTF-8') . '"'; ?>>Precedente</a>
            <a class="pagination-next" <?= $currentPage >= $totalPages ? 'disabled' : 'href="' . htmlspecialchars(build_page_url($currentPage + 1, $queryBase, $basePath), ENT_QUOTES, 'UTF-8') . '"'; ?>>Successiva</a>
            <ul class="pagination-list">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li><a class="pagination-link <?= $i === $currentPage ? 'is-current' : ''; ?>" aria-current="<?= $i === $currentPage ? 'page' : 'false'; ?>" href="<?= htmlspecialchars(build_page_url($i, $queryBase, $basePath), ENT_QUOTES, 'UTF-8'); ?>"><?= $i; ?></a></li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</section>
<?php include __DIR__ . '/footer.php';

function build_page_url(int $page, array $params, string $basePath): string
{
    unset($params['page']);
    if ($basePath !== '/') {
        unset($params['genre']);
    }
    $path = build_route_path($basePath, $page);
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    return $params ? $path . '?' . http_build_query($params) : $path;
}

function build_route_path(string $basePath, int $page): string
{
    $basePath = $basePath === '' ? '/' : $basePath;
    if ($basePath !== '/' && !str_starts_with($basePath, '/')) {
        $basePath = '/' . $basePath;
    }
    if ($page <= 1) {
        return $basePath;
    }
    if ($basePath === '/') {
        return '/page/' . $page;
    }
    return $basePath . '/page/' . $page;
}
