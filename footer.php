<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
?>
</main>
<footer class="footer">
    <div class="content has-text-centered">
        <p>
            <strong>Filmoteca Pro</strong> — la tua libreria cinematografica SEO-first.
        </p>
        <p class="is-size-7">
            This product uses the TMDb API but is not endorsed or certified by TMDb.
            <a href="https://www.themoviedb.org/" rel="noopener" target="_blank">Scopri di più su TMDb</a>.
        </p>
        <p class="is-size-7">
            &copy; <?= date('Y'); ?> Filmoteca Pro. Tutti i diritti riservati.
        </p>
    </div>
</footer>
<script>
    window.appConfig = {
        baseUrl: '<?= htmlspecialchars(rtrim((string) env('APP_URL', 'http://localhost'), '/'), ENT_QUOTES, 'UTF-8'); ?>'
    };
</script>
<script src="/assets/js/main.js" type="module" defer></script>
</body>
</html>
