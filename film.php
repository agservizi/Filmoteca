<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/seo.php';
require_once __DIR__ . '/lib/tmdb.php';
require_once __DIR__ . '/movies.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '';
if (!preg_match('#^/film/(\d+)/(.*)$#', $uri, $segments)) {
    http_response_code(404);
    echo 'Pagina non trovata';
    return;
}

$movieId = (int) $segments[1];
$requestedSlug = trim($segments[2]) ?: '';

$movie = movies_find($movieId);
if (!$movie) {
    http_response_code(404);
    echo 'Film non trovato';
    return;
}

$canonicalPath = '/film/' . $movie['id'] . '/' . $movie['slug'];
if ($requestedSlug !== $movie['slug']) {
    header('Location: ' . seo_build_canonical($canonicalPath), true, 301);
    exit;
}

$tmdbPayload = null;
$videoEmbeds = [];

if (!empty($movie['tmdb_id'])) {
    $tmdbPayload = tmdb_get_movie((int) $movie['tmdb_id'], 'credits,videos,images');
    if ($tmdbPayload && ($tmdbPayload['videos']['results'] ?? []) !== []) {
        foreach ($tmdbPayload['videos']['results'] as $video) {
            if (($video['site'] ?? '') !== 'YouTube') {
                continue;
            }
            $videoEmbeds[] = [
                'name' => $video['name'] ?? ($movie['title'] . ' Trailer'),
                'embed_url' => 'https://www.youtube.com/embed/' . $video['key'],
                'thumbnail_url' => $video['key'] ? 'https://i.ytimg.com/vi/' . $video['key'] . '/hqdefault.jpg' : null,
                'published_at' => $video['published_at'] ?? null,
            ];
        }
    }
}

$meta = seo_default_meta([
    'title' => $movie['title'] . ' — Scheda Film | Filmoteca Pro',
    'description' => mb_strimwidth($movie['summary'] ?? '', 0, 155, '…', 'UTF-8'),
    'image' => $movie['poster']['url'] ?? tmdb_build_poster_url($movie['poster_path_remote'] ?? null, 'w780'),
    'url' => seo_build_canonical($canonicalPath),
    'canonical' => seo_build_canonical($canonicalPath),
    'type' => $videoEmbeds !== [] ? 'video.movie' : 'movie',
]);

$structuredDataScripts = [
    seo_json_ld(seo_build_movie_jsonld($movie, $videoEmbeds)),
    seo_json_ld(seo_build_breadcrumb_jsonld([
        ['name' => 'Home', 'item' => seo_build_canonical('/')],
        ['name' => 'Catalogo', 'item' => seo_build_canonical('/page/1')],
        ['name' => $movie['title'], 'item' => seo_build_canonical($canonicalPath)],
    ])),
];

include __DIR__ . '/header.php';
?>
<section class="section">
    <div class="container">
        <div class="columns is-variable is-8">
            <div class="column is-one-third-desktop is-full-tablet">
                <figure class="movie-hero__poster">
                    <?php if (!empty($movie['poster']['url'])): ?>
                        <img src="<?= htmlspecialchars($movie['poster']['url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Poster di <?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="500" height="750">
                    <?php else: ?>
                        <div class="movie-hero__poster-placeholder" role="img" aria-label="Poster non disponibile"></div>
                    <?php endif; ?>
                </figure>
                <div class="buttons is-flex is-flex-direction-column gap-sm">
                    <button class="button is-link is-fullwidth" type="button" data-action="watchlist" data-movie-id="<?= (int) $movie['id']; ?>">
                        <span class="icon" aria-hidden="true">➕</span>
                        <span>Aggiungi alla watchlist</span>
                    </button>
                    <button class="button is-light is-fullwidth" type="button" data-action="share" data-share-url="<?= htmlspecialchars($meta['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="icon" aria-hidden="true">🔗</span>
                        <span>Condividi</span>
                    </button>
                </div>
            </div>
            <div class="column">
                <header class="movie-hero__header">
                    <h1 class="title is-2"><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="subtitle is-4">
                        <span><?= htmlspecialchars((string) $movie['year'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span aria-hidden="true">•</span>
                        <span><?= htmlspecialchars($movie['genre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($movie['duration'])): ?>
                            <span aria-hidden="true">•</span>
                            <span><?= (int) $movie['duration']; ?> min</span>
                        <?php endif; ?>
                    </p>
                    <p class="movie-hero__rating" aria-label="Valutazione degli utenti">
                        <span class="icon" aria-hidden="true">⭐</span>
                        <strong><?= htmlspecialchars(number_format((float) ($movie['rating'] ?? 0), 1, ',', ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <small>(<?= number_format((int) ($movie['rating_count'] ?? 0), 0, ',', '.'); ?> voti)</small>
                    </p>
                </header>
                <section aria-labelledby="trama-heading">
                    <h2 class="title is-4" id="trama-heading">Trama</h2>
                    <p class="content is-size-5"><?= nl2br(htmlspecialchars($movie['summary'], ENT_QUOTES, 'UTF-8')); ?></p>
                </section>
                <?php if (!empty($movie['director'])): ?>
                    <p><strong>Regia:</strong> <?= htmlspecialchars($movie['director'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if (!empty($movie['cast'])): ?>
                    <p><strong>Cast:</strong> <?= htmlspecialchars(implode(', ', $movie['cast']), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if ($videoEmbeds !== []): ?>
                    <section class="section is-small" aria-label="Trailer">
                        <h2 class="title is-4">Trailer</h2>
                        <div class="video-grid">
                            <?php foreach ($videoEmbeds as $video): ?>
                                <article class="video-item">
                                    <iframe
                                        src="<?= htmlspecialchars($video['embed_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                        title="<?= htmlspecialchars($video['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        loading="lazy"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                        referrerpolicy="strict-origin-when-cross-origin"></iframe>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/footer.php';
