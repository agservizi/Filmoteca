<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/seo.php';
require_once __DIR__ . '/lib/url.php';

/** @var array $meta */
$meta = $meta ?? seo_default_meta();
/** @var array<int, string> $structuredDataScripts */
$structuredDataScripts = $structuredDataScripts ?? [];
?>
<!DOCTYPE html>
<html lang="it" class="has-navbar-fixed-top">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="theme-color" content="#111827">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css" as="style">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css" integrity="sha384-srJXuJ4E26lzz1poLHZtyd2h/bQc3rGfj/SBIZb2mIfAuwRWLofJQpuAJkAo8VUF" crossorigin="anonymous">
    <link rel="preload" href="<?= htmlspecialchars(asset_url('css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" as="style">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" media="all">
    <?= seo_render_meta($meta); ?>
    <?php foreach ($structuredDataScripts as $jsonLd): ?>
        <?= $jsonLd; ?>
    <?php endforeach; ?>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(asset_url('images/icon.svg'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="manifest" href="<?= htmlspecialchars(app_path('manifest.webmanifest'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body data-app-env="<?= htmlspecialchars(env('APP_ENV', 'development'), ENT_QUOTES, 'UTF-8'); ?>" data-base-path="<?= htmlspecialchars(app_base_path(), ENT_QUOTES, 'UTF-8'); ?>" data-absolute-base="<?= htmlspecialchars(rtrim(app_url('', true), '/'), ENT_QUOTES, 'UTF-8'); ?>">
<nav class="navbar is-fixed-top" role="navigation" aria-label="Main navigation">
    <div class="navbar-brand">
        <a class="navbar-item" href="<?= htmlspecialchars(app_path(''), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="logo" aria-hidden="true">🎬</span>
            <span class="logo-text">Filmoteca Pro</span>
        </a>
        <a role="button" class="navbar-burger" data-target="navMenu" aria-label="menu" aria-expanded="false">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>
    <div id="navMenu" class="navbar-menu">
        <div class="navbar-start">
            <a class="navbar-item" href="<?= htmlspecialchars(app_path(''), ENT_QUOTES, 'UTF-8'); ?>">Home</a>
            <a class="navbar-item" href="<?= htmlspecialchars(app_path('page/1'), ENT_QUOTES, 'UTF-8'); ?>">Catalogo</a>
            <a class="navbar-item" href="<?= htmlspecialchars(app_path('genere/science-fiction'), ENT_QUOTES, 'UTF-8'); ?>">Fantascienza</a>
            <a class="navbar-item" href="<?= htmlspecialchars(app_path('genere/drama'), ENT_QUOTES, 'UTF-8'); ?>">Drama</a>
        </div>
        <div class="navbar-end">
            <div class="navbar-item">
                <button class="button is-light" id="theme-toggle" type="button" aria-pressed="false">
                    <span class="icon" aria-hidden="true">🌓</span>
                    <span>Modalità</span>
                </button>
            </div>
        </div>
    </div>
</nav>
<main id="main" class="main-content" tabindex="-1">
