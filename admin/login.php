<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/seo.php';

auth_start_session();

if (auth_check()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify('admin_login', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token CSRF non valido, riprova.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (!$username || !$password) {
            $errors[] = 'Inserisci credenziali complete.';
        } elseif (auth_login($username, $password)) {
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $errors[] = 'Credenziali non corrette.';
        }
    }
}

$csrfToken = csrf_token('admin_login');

$meta = seo_default_meta([
    'title' => 'Accesso amministratore - Filmoteca',
    'description' => 'Area riservata per la gestione del catalogo Filmoteca.',
    'canonical' => seo_build_canonical('/admin/login.php'),
    'url' => seo_build_canonical('/admin/login.php'),
]);
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
<div class="container" style="max-width: 420px;">
    <h1 class="title has-text-centered">Filmoteca - Admin</h1>
    <p class="subtitle has-text-centered">Accedi per gestire il catalogo.</p>

    <?php foreach ($errors as $error): ?>
        <div class="notification is-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <form method="post" class="box" novalidate>
        <div class="field">
            <label class="label" for="username">Username</label>
            <div class="control">
                <input class="input" type="text" name="username" id="username" required autocomplete="username">
            </div>
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <div class="control">
                <input class="input" type="password" name="password" id="password" required autocomplete="current-password">
            </div>
        </div>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="field">
            <button class="button is-primary is-fullwidth" type="submit">Accedi</button>
        </div>
    </form>

    <p class="has-text-centered is-size-7 has-text-grey">
        Imposta le credenziali tramite `ADMIN_USERNAME` + `ADMIN_PASSWORD` o usa `ADMIN_HASHED_PASSWORD` con <code>password_hash</code>.
    </p>
</div>
</body>
</html>
