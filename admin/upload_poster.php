<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../movies.php';
require_once __DIR__ . '/../lib/seo.php';
require_once __DIR__ . '/../lib/images.php';
require_once __DIR__ . '/../lib/url.php';

auth_start_session();
auth_require();

$meta = seo_default_meta([
    'title' => 'Carica poster - Filmoteca',
    'description' => 'Gestisci manualmente i poster del catalogo.',
    'canonical' => seo_build_canonical('/admin/upload_poster.php'),
    'url' => seo_build_canonical('/admin/upload_poster.php'),
]);

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify('admin_poster', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token CSRF non valido. Riprova.';
    } else {
        $movieId = (int) ($_POST['movie_id'] ?? 0);
        $movie = $movieId > 0 ? movies_find($movieId) : null;
        if (!$movie) {
            $errors[] = 'Film non trovato.';
        } elseif (!isset($_FILES['poster']) || $_FILES['poster']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Caricamento poster non riuscito.';
        } else {
            $fileType = mime_content_type($_FILES['poster']['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($fileType, $allowed, true)) {
                $errors[] = 'Formato non supportato. Carica JPEG, PNG o WebP.';
            } elseif ($_FILES['poster']['size'] > 6 * 1024 * 1024) {
                $errors[] = 'File troppo grande. Limite 6MB.';
            } else {
                $binary = file_get_contents($_FILES['poster']['tmp_name']);
                if ($binary === false) {
                    $errors[] = 'Impossibile leggere il file caricato.';
                } else {
                    $sanitized = preg_replace('/[^a-z0-9-]+/i', '-', $movie['slug'] ?? ($movie['title'] ?? 'poster'));
                    $sanitized = trim($sanitized, '-');
                    $posterDir = __DIR__ . '/../assets/posters/' . $movie['id'];
                    if (!is_dir($posterDir) && !mkdir($posterDir, 0775, true) && !is_dir($posterDir)) {
                        $errors[] = 'Impossibile creare la cartella di destinazione.';
                    } else {
                        $basePath = $posterDir . '/' . $sanitized;
                        $sizes = [
                            'w154' => 154,
                            'w342' => 342,
                            'w500' => 500,
                            'w780' => 780,
                        ];
                        $variants = images_save_variants($binary, $basePath, $sizes);
                        if ($variants === null) {
                            $errors[] = 'Impossibile generare le varianti del poster.';
                        } else {
                            $baseAbsolute = $variants['_base'] ?? ($basePath . '.webp');
                            $projectRoot = realpath(__DIR__ . '/..');
                            $baseReal = realpath($baseAbsolute) ?: $baseAbsolute;
                            $relativeBase = $baseReal;
                            if ($projectRoot) {
                                $normalizedBase = str_replace('\\', '/', $baseReal);
                                $normalizedRoot = str_replace('\\', '/', $projectRoot);
                                if (strncmp($normalizedBase, $normalizedRoot, strlen($normalizedRoot)) === 0) {
                                    $relativeBase = ltrim(substr($normalizedBase, strlen($normalizedRoot)), '/');
                                }
                            }
                            $relativeBase = str_replace('\\', '/', $relativeBase);
                            if (movies_supports_database()) {
                                $pdo = pdo_connect_from_env();
                                $stmt = $pdo->prepare('UPDATE movies SET poster_path_local = :path, poster_cached_at = NOW(), updated_at = NOW() WHERE id = :id');
                                $stmt->execute([
                                    ':path' => $relativeBase,
                                    ':id' => $movie['id'],
                                ]);
                            } else {
                                $logPath = __DIR__ . '/../logs/manual-poster-' . $movie['id'] . '-' . date('Ymd-His') . '.json';
                                        $variantData = $variants;
                                        unset($variantData['_base']);
                                        $variantKeys = array_keys($variantData);
                                file_put_contents($logPath, json_encode([
                                    'movie_id' => $movie['id'],
                                    'poster_path_local' => $relativeBase,
                                        'generated' => $variantKeys,
                                ], JSON_PRETTY_PRINT));
                            }
                            $messages[] = 'Poster aggiornato per "' . $movie['title'] . '".';
                        }
                    }
                }
            }
        }
    }
}

$csrfToken = csrf_token('admin_poster');
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

    <h1 class="title">Carica poster manualmente</h1>
    <p class="subtitle">Sovrascrive l'eventuale poster remoto TMDb.</p>

    <?php foreach ($messages as $message): ?>
        <div class="notification is-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="notification is-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" class="box">
        <div class="field">
            <label class="label" for="movie_id">ID Film</label>
            <div class="control">
                <input class="input" type="number" min="1" name="movie_id" id="movie_id" required value="<?= isset($_POST['movie_id']) ? htmlspecialchars((string) $_POST['movie_id'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
        </div>

        <div class="field">
            <label class="label" for="poster">Poster (JPEG/PNG/WebP)</label>
            <div class="control">
                <input class="input" type="file" name="poster" id="poster" accept="image/jpeg,image/png,image/webp" required>
            </div>
        </div>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="field">
            <button class="button is-link" type="submit">Carica poster</button>
        </div>
    </form>
</div>
</body>
</html>
