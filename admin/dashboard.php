<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../movies.php';
require_once __DIR__ . '/../lib/seo.php';

auth_start_session();
auth_require();

$meta = seo_default_meta([
    'title' => 'Dashboard admin - Filmoteca',
    'description' => 'Pannello di controllo per il catalogo Filmoteca.',
    'canonical' => seo_build_canonical('/admin/dashboard.php'),
    'url' => seo_build_canonical('/admin/dashboard.php'),
]);

$totalMovies = movies_count();
$genres = movies_distinct_genres();
$recentMovies = movies_recent(5);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?= htmlspecialchars($meta['canonical'], ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
</head>
<body class="section">
<div class="container">
    <div class="level">
        <div class="level-left">
            <div>
                <h1 class="title">Filmoteca - Dashboard</h1>
                <p class="subtitle">Gestisci catalogo e risorse multimediali.</p>
            </div>
        </div>
        <div class="level-right">
            <a class="button is-light" href="/admin/logout.php">Esci</a>
        </div>
    </div>

    <div class="columns is-multiline">
        <div class="column is-4">
            <div class="box has-text-centered">
                <p class="heading">Film a catalogo</p>
                <p class="title is-2"><?= number_format($totalMovies, 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="column is-4">
            <div class="box has-text-centered">
                <p class="heading">Generi disponibili</p>
                <p class="title is-2"><?= number_format(count($genres), 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="column is-4">
            <div class="box has-text-centered">
                <p class="heading">TMDb Sync</p>
                <p class="title is-5">Usa <code>php scripts/tmdb_sync.php</code></p>
            </div>
        </div>
    </div>

    <div class="columns">
        <div class="column is-6">
            <div class="box">
                <h2 class="title is-5">Ultimi aggiornamenti</h2>
                <table class="table is-fullwidth">
                    <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Aggiornato il</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentMovies as $movie): ?>
                        <tr>
                            <td>
                                <a href="/film/<?= htmlspecialchars($movie['id'], ENT_QUOTES, 'UTF-8'); ?>/<?= htmlspecialchars($movie['slug'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($movie['updated_at'] ?? 'n/d', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="column is-6">
            <div class="box">
                <h2 class="title is-5">Azioni rapide</h2>
                <div class="buttons">
                    <a class="button is-primary" href="/admin/import_csv.php">Importa CSV</a>
                    <a class="button is-link" href="/admin/upload_poster.php">Carica poster</a>
                    <span class="button is-warning is-light" title="Esegui da terminale: php scripts/cli/tmdb_sync.php">Sync TMDb (CLI)</span>
                </div>
                <p class="is-size-7 has-text-grey">
                    Per motivi di sicurezza esegui i comandi CLI dal server e limita l'accesso a questa sezione tramite VPN o IP allow list.
                </p>
            </div>
        </div>
    </div>

    <div class="box">
        <h2 class="title is-5">Generi principali</h2>
        <div class="tags">
            <?php foreach ($genres as $genre): ?>
                <span class="tag is-info is-light"><?= htmlspecialchars($genre, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
