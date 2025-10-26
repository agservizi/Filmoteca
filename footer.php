<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/url.php';
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
        basePath: '<?= htmlspecialchars(app_base_path(), ENT_QUOTES, 'UTF-8'); ?>',
        absoluteBase: '<?= htmlspecialchars(rtrim(app_url('', true), '/'), ENT_QUOTES, 'UTF-8'); ?>'
    };
</script>
<script src="<?= htmlspecialchars(asset_url('js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" type="module" defer></script>
</body>
</html>
