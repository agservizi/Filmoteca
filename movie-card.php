<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/url.php';

/** @var array $movie */
?>
<article class="movie-card" data-movie-id="<?= (int) $movie['id']; ?>">
    <a class="movie-card__link" href="<?= htmlspecialchars(app_path('film/' . (int) $movie['id'] . '/' . $movie['slug']), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Vai alla scheda di <?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?>">
        <figure class="movie-card__poster">
            <?php if (!empty($movie['poster']['url'])): ?>
                <img
                    src="<?= htmlspecialchars($movie['poster']['url'], ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Poster di <?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                    decoding="async"
                    width="342"
                    height="513"
                    <?php if (!empty($movie['poster']['srcset'])): ?>
                        srcset="<?= htmlspecialchars(implode(', ', array_map(static function ($entry) {
                            return $entry['url'] . ' ' . preg_replace('/\\D+/', '', $entry['size']) . 'w';
                        }, $movie['poster']['srcset'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>"
                        sizes="(max-width: 768px) 50vw, 342px"
                    <?php endif; ?>
                >
            <?php else: ?>
                <div class="movie-card__poster-placeholder" role="img" aria-label="Poster non disponibile"></div>
            <?php endif; ?>
        </figure>
        <div class="movie-card__body">
            <h2 class="movie-card__title"><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="movie-card__meta">
                <span class="movie-card__year"><?= htmlspecialchars((string) $movie['year'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="movie-card__genre"><?= htmlspecialchars($movie['genre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </p>
            <p class="movie-card__excerpt"><?= htmlspecialchars(mb_strimwidth($movie['summary'] ?? '', 0, 160, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="movie-card__rating" aria-label="Valutazione media">
                <span class="icon" aria-hidden="true">⭐</span>
                <strong><?= htmlspecialchars(number_format((float) ($movie['rating'] ?? 0), 1, ',', ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="movie-card__rating-count">(<?= number_format((int) ($movie['rating_count'] ?? 0), 0, ',', '.'); ?>)</span>
            </p>
            <span class="movie-card__cta" aria-hidden="true">Dettagli</span>
        </div>
    </a>
</article>
