<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../movies.php';
require_once __DIR__ . '/../lib/seo.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/url.php';

auth_start_session();
auth_require();

$meta = seo_default_meta([
    'title' => 'Import CSV - Filmoteca',
    'description' => 'Carica film in blocco tramite file CSV.',
    'canonical' => seo_build_canonical('/admin/import_csv.php'),
    'url' => seo_build_canonical('/admin/import_csv.php'),
]);

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify('admin_import', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token CSRF non valido. Riprova.';
    } elseif (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Caricamento CSV non riuscito.';
    } else {
        $tmpName = $_FILES['csv']['tmp_name'];
        $handle = fopen($tmpName, 'rb');
        if ($handle === false) {
            $errors[] = 'Impossibile leggere il file CSV.';
        } else {
            $delimiter = ',';
            $header = fgetcsv($handle, 0, $delimiter);
            if (!$header) {
                $errors[] = 'CSV senza intestazione.';
            } else {
                $header = array_map('trim', $header);
                $required = ['title', 'slug', 'year'];
                $missing = array_diff($required, $header);
                if ($missing) {
                    $errors[] = 'Colonne obbligatorie assenti: ' . implode(', ', $missing);
                } else {
                    $rows = [];
                    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                        if (count($data) === 1 && trim((string) $data[0]) === '') {
                            continue;
                        }
                        $row = array_combine($header, array_map('trim', $data));
                        if (!$row) {
                            continue;
                        }
                        $row['year'] = (int) ($row['year'] ?? 0);
                        $row['duration'] = isset($row['duration']) ? (int) $row['duration'] : null;
                        $row['rating'] = isset($row['rating']) ? (float) $row['rating'] : null;
                        $row['rating_count'] = isset($row['rating_count']) ? (int) $row['rating_count'] : null;
                        if (!empty($row['genres'])) {
                            $row['genres'] = array_filter(array_map('trim', explode('|', $row['genres'])));
                        } elseif (!empty($row['genre'])) {
                            $row['genres'] = [$row['genre']];
                        } else {
                            $row['genres'] = [];
                        }
                        $row['cast'] = !empty($row['cast']) ? array_filter(array_map('trim', explode('|', $row['cast']))) : [];
                        $rows[] = $row;
                    }
                    fclose($handle);

                    if ($rows === []) {
                        $errors[] = 'Nessuna riga valida trovata.';
                    } elseif (movies_supports_database()) {
                        $pdo = pdo_connect_from_env();
                        $pdo->beginTransaction();
                        try {
                            $stmt = $pdo->prepare('INSERT INTO movies (tmdb_id, title, slug, year, genre, genres, poster_path_remote, summary, director, cast, duration, rating, rating_count, created_at, updated_at) VALUES (:tmdb_id, :title, :slug, :year, :genre, :genres, :poster_path_remote, :summary, :director, :cast, :duration, :rating, :rating_count, NOW(), NOW()) ON DUPLICATE KEY UPDATE tmdb_id = VALUES(tmdb_id), title = VALUES(title), slug = VALUES(slug), year = VALUES(year), genre = VALUES(genre), genres = VALUES(genres), poster_path_remote = VALUES(poster_path_remote), summary = VALUES(summary), director = VALUES(director), cast = VALUES(cast), duration = VALUES(duration), rating = VALUES(rating), rating_count = VALUES(rating_count), updated_at = NOW()');
                            $inserted = 0;
                            foreach ($rows as $row) {
                                $stmt->execute([
                                    ':tmdb_id' => $row['tmdb_id'] ?? null,
                                    ':title' => $row['title'] ?? '',
                                    ':slug' => $row['slug'] ?? '',
                                    ':year' => $row['year'] ?? null,
                                    ':genre' => $row['genre'] ?? null,
                                    ':genres' => json_encode($row['genres'], JSON_UNESCAPED_UNICODE),
                                    ':poster_path_remote' => $row['poster_path_remote'] ?? null,
                                    ':summary' => $row['summary'] ?? null,
                                    ':director' => $row['director'] ?? null,
                                    ':cast' => json_encode($row['cast'], JSON_UNESCAPED_UNICODE),
                                    ':duration' => $row['duration'] ?? null,
                                    ':rating' => $row['rating'] ?? null,
                                    ':rating_count' => $row['rating_count'] ?? null,
                                ]);
                                $inserted++;
                            }
                            $pdo->commit();
                            $messages[] = sprintf('Importazione completata: %d record elaborati.', $inserted);
                        } catch (Throwable $e) {
                            $pdo->rollBack();
                            $errors[] = 'Errore durante l\'import: ' . $e->getMessage();
                        }
                    } else {
                        $previewPath = __DIR__ . '/../logs/import-preview-' . date('Ymd-His') . '.json';
                        file_put_contents($previewPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $messages[] = 'Anteprima salvata in ' . basename($previewPath) . '. Abilita il database per importare definitivamente.';
                    }
                }
            }
        }
    }
}

$csrfToken = csrf_token('admin_import');
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
<div class="container" style="max-width: 720px;">
    <a class="button is-text" href="<?= htmlspecialchars(app_path('admin/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">← Torna alla dashboard</a>

    <h1 class="title">Importazione CSV</h1>
    <p class="subtitle">Carica un CSV con le colonne standard Filmoteca.</p>

    <?php foreach ($messages as $message): ?>
        <div class="notification is-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="notification is-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" class="box">
        <div class="field">
            <label class="label" for="csv">File CSV</label>
            <div class="control">
                <input class="input" type="file" name="csv" id="csv" accept="text/csv">
            </div>
            <p class="help">Separatore virgola, campi multipli separati da <code>|</code>. Intestazioni consigliate: title, slug, year, genre, genres, cast, tmdb_id, poster_path_remote, summary, director, duration, rating, rating_count.</p>
        </div>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="field">
            <button class="button is-primary" type="submit">Avvia importazione</button>
        </div>
    </form>
</div>
</body>
</html>
