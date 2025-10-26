<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/movies.php';

header('Content-Type: application/xml; charset=utf-8');

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$perPage = 60;
$page = 1;
$collected = [];

do {
    $result = movies_paginated([], $page, $perPage);
    $collected = array_merge($collected, $result['data']);
    $page++;
} while ($page <= ($result['meta']['total_pages'] ?? 1));

$now = date(DATE_W3C);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <url>
        <loc><?= htmlspecialchars($appUrl . '/', ENT_XML1); ?></loc>
        <lastmod><?= $now; ?></lastmod>
        <priority>1.0</priority>
    </url>
    <?php foreach ($collected as $movie): ?>
        <url>
            <loc><?= htmlspecialchars($appUrl . '/film/' . $movie['id'] . '/' . $movie['slug'], ENT_XML1); ?></loc>
            <?php if (!empty($movie['updated_at'])): ?>
                <lastmod><?= htmlspecialchars(date(DATE_W3C, strtotime($movie['updated_at'])), ENT_XML1); ?></lastmod>
            <?php else: ?>
                <lastmod><?= $now; ?></lastmod>
            <?php endif; ?>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
            <?php if (!empty($movie['poster']['url'])): ?>
                <image:image>
                    <image:loc><?= htmlspecialchars($movie['poster']['url'], ENT_XML1); ?></image:loc>
                    <image:caption><?= htmlspecialchars($movie['title'] . ' poster', ENT_XML1); ?></image:caption>
                </image:image>
            <?php endif; ?>
        </url>
    <?php endforeach; ?>
</urlset>
